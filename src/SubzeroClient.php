<?php

declare(strict_types=1);

namespace Iceberg\Subzero;

use GuzzleHttp\Client;
use Iceberg\Subzero\Exceptions\SubzeroApiException;
use Iceberg\Subzero\Internal\AuthMode;
use Iceberg\Subzero\Internal\CallerContextCapture;
use Iceberg\Subzero\Internal\HttpClient;
use Iceberg\Subzero\Models\ResolveBatchItem;
use Iceberg\Subzero\Models\ResolveBatchResultItem;
use Iceberg\Subzero\Models\ResolveResult;
use Iceberg\Subzero\Models\ResolveSource;
use Iceberg\Subzero\Models\RevealCallerContext;
use Iceberg\Subzero\Models\RevealGrantResult;
use Iceberg\Subzero\Models\SubzeroConstants;
use Iceberg\Subzero\Models\TokenizeBatchContext;
use Iceberg\Subzero\Models\TokenizeBatchItem;
use Iceberg\Subzero\Models\TokenizeBatchResultItem;
use Iceberg\Subzero\Models\WarehouseRevealContext;

final class SubzeroClient
{
    private readonly HttpClient $http;
    private readonly bool $captureCallerContext;
    private readonly ?RevealCallerContext $deploymentContext;
    private readonly bool $captureDeploymentContext;
    public readonly ProxyResource $proxy;

    public function __construct(
        ?string $apiKey = null,
        ?string $tokenizeKey = null,
        ?string $revealKey = null,
        ?string $revealGrantKey = null,
        ?string $proxyKey = null,
        ?string $warehouseKey = null,
        string $baseUrl = 'https://api.subzero-data.com',
        float $timeout = 60.0,
        bool $captureCallerContext = true,
        ?RevealCallerContext $deploymentContext = null,
        bool $captureDeploymentContext = true,
        ?Client $httpClient = null,
    ) {
        $resolvedTokenize = $tokenizeKey ?? $apiKey;
        $resolvedReveal = $revealKey ?? $apiKey;
        $resolvedRevealGrant = $revealGrantKey ?? $apiKey;
        $resolvedProxy = $proxyKey ?? $apiKey ?? $tokenizeKey;
        $resolvedWarehouse = $warehouseKey ?? $apiKey;

        if (
            $resolvedTokenize === null
            && $resolvedReveal === null
            && $resolvedProxy === null
            && $resolvedRevealGrant === null
            && $resolvedWarehouse === null
        ) {
            throw new \InvalidArgumentException(
                'Provide apiKey, or tokenizeKey and/or revealKey and/or proxyKey and/or revealGrantKey and/or warehouseKey for scoped credentials.',
            );
        }

        $this->captureCallerContext = $captureCallerContext;
        $this->deploymentContext = $deploymentContext;
        $this->captureDeploymentContext = $captureDeploymentContext;
        $this->http = new HttpClient(
            $resolvedTokenize,
            $resolvedReveal,
            $resolvedRevealGrant,
            $resolvedProxy,
            $resolvedWarehouse,
            $baseUrl,
            $timeout,
            $httpClient,
        );
        $this->proxy = new ProxyResource($this->http);
    }

    public static function fromEnv(?string $baseUrl = null, float $timeout = 60.0): self
    {
        $resolvedBaseUrl = $baseUrl
            ?? getenv('SUBZERO_BASE_URL')
            ?: 'https://api.subzero-data.com';

        return new self(
            apiKey: self::envString('SUBZERO_API_KEY'),
            tokenizeKey: self::envString('SUBZERO_TOKENIZE_API_KEY'),
            revealKey: self::envString('SUBZERO_REVEAL_API_KEY'),
            revealGrantKey: self::envString('SUBZERO_REVEAL_GRANT_API_KEY'),
            proxyKey: self::envString('SUBZERO_PROXY_API_KEY'),
            warehouseKey: self::envString('SUBZERO_WAREHOUSE_API_KEY'),
            baseUrl: $resolvedBaseUrl,
            timeout: $timeout,
        );
    }

    /** @return array<string, mixed> */
    public function health(): array
    {
        return $this->http->health();
    }

    /** @return array<string, mixed> */
    public function ready(): array
    {
        return $this->http->ready();
    }

    public function tokenize(string $entityType, string $value): string
    {
        /** @var array<string, mixed> $body */
        $body = $this->http->postJson('/v1/tokenize', [
            'entity_type' => $entityType,
            'value' => $value,
        ], AuthMode::TokenizeKey);

        return (string) $body['token'];
    }

    public function search(string $entityType, string $value): string
    {
        /** @var array<string, mixed> $body */
        $body = $this->http->postJson('/v1/search', [
            'entity_type' => $entityType,
            'value' => $value,
        ], AuthMode::TokenizeKey);

        return (string) $body['token'];
    }

    public function reveal(string $token, ?RevealCallerContext $callerContext = null): string
    {
        $payload = ['token' => $token];
        $context = CallerContextCapture::resolveRevealCallerContext(
            $callerContext,
            $this->captureCallerContext,
            $this->deploymentContext,
            $this->captureDeploymentContext,
        );
        if ($context !== null) {
            $payload['caller_context'] = $context;
        }

        /** @var array<string, mixed> $body */
        $body = $this->http->postJson('/v1/reveal', $payload, AuthMode::RevealKey);

        return (string) $body['value'];
    }

    public function resolve(
        string $value,
        bool $passthroughOnMiss = false,
        ?RevealCallerContext $callerContext = null,
    ): ResolveResult {
        $payload = [
            'value' => $value,
            'passthrough_on_miss' => $passthroughOnMiss,
        ];
        $context = CallerContextCapture::resolveRevealCallerContext(
            $callerContext,
            $this->captureCallerContext,
            $this->deploymentContext,
            $this->captureDeploymentContext,
        );
        if ($context !== null) {
            $payload['caller_context'] = $context;
        }

        /** @var array<string, mixed> $body */
        $body = $this->http->postJson('/v1/reveal/resolve', $payload, AuthMode::RevealKey);

        return new ResolveResult(
            value: (string) $body['value'],
            source: self::parseResolveSource((string) $body['source']),
            token: self::nullableString($body, 'token'),
            entityType: self::nullableString($body, 'entity_type'),
        );
    }

    /** @param array<string, mixed> $clientPublicKeyJwk */
    public function createRevealGrant(
        string $token,
        array $clientPublicKeyJwk,
        ?string $allowedOrigin = null,
        ?RevealCallerContext $callerContext = null,
    ): RevealGrantResult {
        $payload = [
            'token' => $token,
            'client_public_key_jwk' => $clientPublicKeyJwk,
        ];
        if ($allowedOrigin !== null) {
            $payload['allowed_origin'] = $allowedOrigin;
        }
        $context = CallerContextCapture::resolveRevealCallerContext(
            $callerContext,
            $this->captureCallerContext,
            $this->deploymentContext,
            $this->captureDeploymentContext,
        );
        if ($context !== null) {
            $payload['caller_context'] = $context;
        }

        /** @var array<string, mixed> $body */
        $body = $this->http->postJson('/v1/reveal/grants', $payload, AuthMode::RevealGrantKey);

        return new RevealGrantResult(
            grantId: (string) $body['grant_id'],
            expiresAt: (string) $body['expires_at'],
        );
    }

    /** @param list<TokenizeBatchItem> $items */
    /** @return list<TokenizeBatchResultItem> */
    public function tokenizeBatch(
        array $items,
        ?TokenizeBatchContext $context = null,
        int $chunkSize = SubzeroConstants::TOKENIZE_BATCH_MAX_ROWS,
    ): array {
        if ($items === []) {
            throw new \InvalidArgumentException('items must not be empty');
        }

        if ($chunkSize < 1 || $chunkSize > SubzeroConstants::TOKENIZE_BATCH_MAX_ROWS) {
            throw new \InvalidArgumentException(
                'chunkSize must be between 1 and ' . SubzeroConstants::TOKENIZE_BATCH_MAX_ROWS,
            );
        }

        $results = [];
        foreach (array_chunk($items, $chunkSize) as $chunk) {
            array_push($results, ...$this->tokenizeBatchChunk($chunk, $context));
        }

        return $results;
    }

    /** @param list<TokenizeBatchItem> $items */
    /** @return list<TokenizeBatchResultItem> */
    private function tokenizeBatchChunk(array $items, ?TokenizeBatchContext $context): array
    {
        $payload = [
            'items' => array_map(
                static fn (TokenizeBatchItem $item): array => [
                    'index' => $item->index,
                    'entity_type' => $item->entityType,
                    'value' => $item->value,
                ],
                $items,
            ),
        ];

        if ($context !== null) {
            $contextBody = [];
            if ($context->source !== null) {
                $contextBody['source'] = $context->source->value;
            }
            if ($context->pipelineId !== null) {
                $contextBody['pipeline_id'] = $context->pipelineId;
            }
            if ($context->syncId !== null) {
                $contextBody['sync_id'] = $context->syncId;
            }
            if ($contextBody !== []) {
                $payload['context'] = $contextBody;
            }
        }

        /** @var array<string, mixed> $body */
        $body = $this->http->postJson('/v1/tokenize/batch', $payload, AuthMode::TokenizeKey);

        $resultItems = [];
        foreach ($body['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $resultItems[] = new TokenizeBatchResultItem(
                index: (int) $item['index'],
                token: self::nullableString($item, 'token'),
                entityType: self::nullableString($item, 'entity_type'),
                error: self::nullableString($item, 'error'),
            );
        }

        return $resultItems;
    }

    /**
     * @param list<array{0: int, 1: string}> $data
     * @return list<array{0: int, 1: string}>
     */
    public function revealBatch(
        array $data,
        WarehouseRevealContext $context,
        int $chunkSize = SubzeroConstants::REVEAL_BATCH_MAX_ROWS,
    ): array {
        if ($data === []) {
            throw new \InvalidArgumentException('data must not be empty');
        }

        if ($chunkSize < 1 || $chunkSize > SubzeroConstants::REVEAL_BATCH_MAX_ROWS) {
            throw new \InvalidArgumentException(
                'chunkSize must be between 1 and ' . SubzeroConstants::REVEAL_BATCH_MAX_ROWS,
            );
        }

        $results = [];
        foreach (array_chunk($data, $chunkSize) as $chunk) {
            array_push($results, ...$this->revealBatchChunk($chunk, $context));
        }

        return $results;
    }

    /**
     * @param list<array{0: int, 1: string}> $data
     * @return list<array{0: int, 1: string}>
     */
    private function revealBatchChunk(array $data, WarehouseRevealContext $context): array
    {
        $contextBody = ['principal' => $context->principal];
        if ($context->source !== null) {
            $contextBody['source'] = $context->source->value;
        }
        if ($context->queryId !== null) {
            $contextBody['query_id'] = $context->queryId;
        }
        if ($context->warehouse !== null) {
            $contextBody['warehouse'] = $context->warehouse;
        }
        if ($context->role !== null) {
            $contextBody['role'] = $context->role;
        }
        if ($context->jobId !== null) {
            $contextBody['job_id'] = $context->jobId;
        }

        /** @var array<string, mixed> $body */
        $body = $this->http->postJson(
            '/v1/reveal/batch',
            ['data' => $data, 'context' => $contextBody],
            AuthMode::WarehouseKey,
        );

        $rows = [];
        foreach ($body['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [(int) $row[0], (string) $row[1]];
        }

        return $rows;
    }

    /** @param list<ResolveBatchItem> $items */
    /** @return list<ResolveBatchResultItem> */
    public function resolveBatch(
        array $items,
        bool $passthroughOnMiss = false,
        ?RevealCallerContext $callerContext = null,
        int $chunkSize = SubzeroConstants::RESOLVE_BATCH_MAX_ROWS,
    ): array {
        if ($items === []) {
            throw new \InvalidArgumentException('items must not be empty');
        }

        if ($chunkSize < 1 || $chunkSize > SubzeroConstants::RESOLVE_BATCH_MAX_ROWS) {
            throw new \InvalidArgumentException(
                'chunkSize must be between 1 and ' . SubzeroConstants::RESOLVE_BATCH_MAX_ROWS,
            );
        }

        $results = [];
        foreach (array_chunk($items, $chunkSize) as $chunk) {
            array_push(
                $results,
                ...$this->resolveBatchChunk($chunk, $passthroughOnMiss, $callerContext),
            );
        }

        return $results;
    }

    /** @param list<ResolveBatchItem> $items */
    /** @return list<ResolveBatchResultItem> */
    private function resolveBatchChunk(
        array $items,
        bool $passthroughOnMiss,
        ?RevealCallerContext $callerContext,
    ): array {
        $payload = [
            'items' => array_map(
                static function (ResolveBatchItem $item): array {
                    $row = [
                        'index' => $item->index,
                        'value' => $item->value,
                    ];
                    if ($item->passthroughOnMiss !== null) {
                        $row['passthrough_on_miss'] = $item->passthroughOnMiss;
                    }

                    return $row;
                },
                $items,
            ),
            'passthrough_on_miss' => $passthroughOnMiss,
        ];

        $context = CallerContextCapture::resolveRevealCallerContext(
            $callerContext,
            $this->captureCallerContext,
            $this->deploymentContext,
            $this->captureDeploymentContext,
        );
        if ($context !== null) {
            $payload['caller_context'] = $context;
        }

        /** @var array<string, mixed> $body */
        $body = $this->http->postJson('/v1/reveal/resolve/batch', $payload, AuthMode::RevealKey);

        $resultItems = [];
        foreach ($body['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sourceRaw = self::nullableString($item, 'source');
            $resultItems[] = new ResolveBatchResultItem(
                index: (int) $item['index'],
                value: self::nullableString($item, 'value'),
                source: $sourceRaw === null ? null : self::parseResolveSource($sourceRaw),
                token: self::nullableString($item, 'token'),
                entityType: self::nullableString($item, 'entity_type'),
                error: self::nullableString($item, 'error'),
            );
        }

        return $resultItems;
    }

    private static function parseResolveSource(string $source): ResolveSource
    {
        return match ($source) {
            'vault' => ResolveSource::Vault,
            'passthrough' => ResolveSource::Passthrough,
            default => throw new SubzeroApiException("Unexpected resolve source: {$source}"),
        };
    }

    /** @param array<string, mixed> $data */
    private static function nullableString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return (string) $data[$key];
    }

    private static function envString(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return null;
        }

        return $value;
    }
}
