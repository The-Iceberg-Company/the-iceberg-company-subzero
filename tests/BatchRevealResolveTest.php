<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Iceberg\Subzero\Models\ResolveBatchItem;
use Iceberg\Subzero\Models\WarehouseRevealContext;
use Iceberg\Subzero\Models\WarehouseRevealSource;
use Iceberg\Subzero\SubzeroClient;
use PHPUnit\Framework\TestCase;

final class BatchRevealResolveTest extends TestCase
{
    private const BASE = 'http://testserver';
    private const REVEAL_KEY = 'sz_live_revealkey1234567890123456';
    private const WAREHOUSE_KEY = 'sz_live_warehousekey123456789012345';

    public function testRevealBatchReturnsPlaintextRows(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [[0, '123-45-6789'], [1, '[SSN_missing]']],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $httpClient = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => self::BASE,
        ]);

        $client = new SubzeroClient(
            warehouseKey: self::WAREHOUSE_KEY,
            baseUrl: self::BASE,
            httpClient: $httpClient,
        );

        $results = $client->revealBatch(
            [[0, '[SSN_a]'], [1, '[SSN_missing]']],
            new WarehouseRevealContext(
                principal: 'snowflake_role:ANALYST',
                source: WarehouseRevealSource::Snowflake,
            ),
        );

        self::assertSame([[0, '123-45-6789'], [1, '[SSN_missing]']], $results);
    }

    public function testResolveBatchReturnsPerItemResults(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'items' => [
                    [
                        'index' => 0,
                        'value' => '123-45-6789',
                        'source' => 'passthrough',
                        'token' => null,
                        'entity_type' => null,
                    ],
                    [
                        'index' => 1,
                        'value' => '123-45-6789',
                        'source' => 'vault',
                        'token' => '[SSN_a]',
                        'entity_type' => 'SSN',
                    ],
                    ['index' => 2, 'error' => 'not_found'],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $httpClient = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => self::BASE,
        ]);

        $client = new SubzeroClient(
            revealKey: self::REVEAL_KEY,
            baseUrl: self::BASE,
            httpClient: $httpClient,
        );

        $results = $client->resolveBatch([
            new ResolveBatchItem(0, '123-45-6789'),
            new ResolveBatchItem(1, '[SSN_a]'),
            new ResolveBatchItem(2, '[SSN_missing]'),
        ]);

        self::assertCount(3, $results);
        self::assertTrue($results[0]->ok());
        self::assertFalse($results[2]->ok());
        self::assertSame('not_found', $results[2]->error);
    }
}
