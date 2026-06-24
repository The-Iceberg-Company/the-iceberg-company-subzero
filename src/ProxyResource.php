<?php

declare(strict_types=1);

namespace Iceberg\Subzero;

use Iceberg\Subzero\Internal\AuthMode;
use Iceberg\Subzero\Internal\HttpClient;
use Iceberg\Subzero\Models\DiscoverPreviewResult;
use Iceberg\Subzero\Models\ProxyParsers;
use Iceberg\Subzero\Models\RestructurePreviewResult;
use Iceberg\Subzero\Models\ScanPreviewResult;

final class ProxyResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /** @param list<array<string, mixed>>|null $messages */
    /** @param array<string, mixed>|null $body */
    public function scan(?array $messages = null, ?array $body = null): ScanPreviewResult
    {
        $payload = self::buildProxyPayload($messages, $body);
        /** @var array<string, mixed> $data */
        $data = $this->http->postJson('/v1/proxy/scan', $payload, AuthMode::ProxyKey);

        return ProxyParsers::parseScanPreviewResult($data);
    }

    /** @param list<array<string, mixed>>|null $messages */
    /** @param array<string, mixed>|null $body */
    public function discover(?array $messages = null, ?array $body = null): DiscoverPreviewResult
    {
        $payload = self::buildProxyPayload($messages, $body);
        /** @var array<string, mixed> $data */
        $data = $this->http->postJson('/v1/proxy/discover', $payload, AuthMode::ProxyKey);

        return ProxyParsers::parseDiscoverPreviewResult($data);
    }

    /** @param list<array<string, mixed>>|null $messages */
    /** @param array<string, mixed>|null $body */
    public function restructure(?array $messages = null, ?array $body = null): RestructurePreviewResult
    {
        $payload = self::buildProxyPayload($messages, $body);
        /** @var array<string, mixed> $data */
        $data = $this->http->postJson('/v1/proxy/restructure', $payload, AuthMode::ProxyKey);

        return ProxyParsers::parseRestructurePreviewResult($data);
    }

    /** @param list<array<string, mixed>>|null $messages */
    /** @param array<string, mixed>|null $body */
    /** @return array<string, mixed> */
    private static function buildProxyPayload(?array $messages, ?array $body): array
    {
        if ($body !== null) {
            return ['body' => $body];
        }

        if ($messages !== null) {
            return ['messages' => $messages];
        }

        throw new \InvalidArgumentException('messages or body is required');
    }
}
