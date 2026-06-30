<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Internal;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Iceberg\Subzero\Exceptions\AuthenticationException;
use Iceberg\Subzero\Exceptions\AuthorizationException;
use Iceberg\Subzero\Exceptions\ConflictException;
use Iceberg\Subzero\Exceptions\NotFoundException;
use Iceberg\Subzero\Exceptions\PolicyDeniedException;
use Iceberg\Subzero\Exceptions\SubzeroApiException;
use Iceberg\Subzero\Exceptions\SubzeroNotReadyException;
use Psr\Http\Message\ResponseInterface;

final class HttpClient
{
    /** @var array<string, string> */
    private const REASON_HINTS = [
        'entity_type_not_found' => 'Create the entity type in the Subzero dashboard first.',
        'policy_denied' => 'Add a reveal policy for this API key in the dashboard.',
        'insufficient_scope' => 'Use an API key with the correct scope for this operation.',
        'pattern_mismatch' => "Value does not match the entity type's match_pattern.",
        'not_found' => 'No token exists for this value (search) or token string (reveal).',
    ];

    private readonly Client $client;
    private readonly string $baseUrl;

    public function __construct(
        private readonly ?string $tokenizeKey,
        private readonly ?string $revealKey,
        private readonly ?string $revealGrantKey,
        private readonly ?string $proxyKey,
        string $baseUrl,
        float $timeout = 60.0,
        ?Client $client = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client = $client ?? new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $timeout,
            'http_errors' => false,
        ]);
    }

    /** @param array<string, mixed>|null $body */
    /** @return array<string, mixed>|null */
    public function postJson(string $path, ?array $body = null, AuthMode $auth = AuthMode::TokenizeKey): ?array
    {
        $options = ['headers' => $this->headers($auth)];
        if ($body !== null) {
            $options['json'] = $body;
        }

        $response = $this->client->request('POST', $path, $options);
        $this->ensureSuccess($response);

        $content = (string) $response->getBody();
        if ($content === '') {
            return null;
        }

        /** @var array<string, mixed> */
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed>|null */
    public function getJson(string $path, AuthMode $auth = AuthMode::None): ?array
    {
        $response = $this->client->request('GET', $path, [
            'headers' => $this->headers($auth),
        ]);
        $this->ensureSuccess($response);

        $content = (string) $response->getBody();
        if ($content === '') {
            return null;
        }

        /** @var array<string, mixed> */
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    public function health(): array
    {
        return $this->getJson('/v1/health', AuthMode::None) ?? [];
    }

    /** @return array<string, mixed> */
    public function ready(): array
    {
        try {
            $response = $this->client->request('GET', '/v1/ready');
        } catch (GuzzleException $exception) {
            throw new SubzeroNotReadyException(
                "Subzero API not reachable at {$this->baseUrl} — is the API running and migrations applied?",
                previous: $exception,
            );
        }

        if ($response->getStatusCode() !== 200) {
            throw new SubzeroNotReadyException(
                "Subzero API not ready at {$this->baseUrl} — is the API running and migrations applied?",
            );
        }

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (($body['status'] ?? null) !== 'ready') {
            throw new SubzeroNotReadyException(
                "Subzero API not ready at {$this->baseUrl} — is the API running and migrations applied?",
            );
        }

        return $body;
    }

    /** @return array<string, string> */
    private function headers(AuthMode $auth): array
    {
        $headers = [];
        $key = match ($auth) {
            AuthMode::TokenizeKey => $this->tokenizeKey,
            AuthMode::RevealKey => $this->revealKey,
            AuthMode::RevealGrantKey => $this->revealGrantKey,
            AuthMode::ProxyKey => $this->proxyKey,
            AuthMode::None => null,
        };

        if ($key !== null) {
            $headers['Authorization'] = 'Bearer ' . $key;
        }

        return $headers;
    }

    private function ensureSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $rawBody = (string) $response->getBody();
        $detail = $rawBody;
        $reason = null;

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                if (array_key_exists('detail', $decoded)) {
                    $detailValue = $decoded['detail'];
                    $detail = is_string($detailValue) ? $detailValue : json_encode($detailValue, JSON_THROW_ON_ERROR);
                }
                if (isset($decoded['reason']) && is_string($decoded['reason'])) {
                    $reason = $decoded['reason'];
                }
            }
        } catch (\JsonException) {
            // Keep raw response text as detail.
        }

        $detail = self::messageWithHint($detail, $reason);
        self::throwForStatus($statusCode, $detail, $reason);
    }

    private static function messageWithHint(string $detail, ?string $reason): string
    {
        if ($reason === null || !isset(self::REASON_HINTS[$reason])) {
            return $detail;
        }

        return $detail . ' — ' . self::REASON_HINTS[$reason];
    }

    private static function throwForStatus(int $statusCode, string $detail, ?string $reason): never
    {
        throw match (true) {
            $statusCode === 401 => new AuthenticationException($detail, $statusCode, $reason),
            $statusCode === 403 && $reason === 'policy_denied' => new PolicyDeniedException($detail, $statusCode, $reason),
            $statusCode === 403 && in_array($reason, ['insufficient_scope', 'tenant_mismatch'], true)
                => new AuthorizationException($detail, $statusCode, $reason),
            $statusCode === 404 => new NotFoundException($detail, $statusCode, $reason),
            $statusCode === 409 => new ConflictException($detail, $statusCode, $reason),
            default => new SubzeroApiException($detail, $statusCode, $reason),
        };
    }
}
