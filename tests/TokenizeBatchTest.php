<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Iceberg\Subzero\Models\TokenizeBatchItem;
use Iceberg\Subzero\SubzeroClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class TokenizeBatchTest extends TestCase
{
    public function testTokenizeBatchReturnsPerItemResults(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(200, [
                'items' => [
                    ['index' => 0, 'token' => '[SSN_a]', 'entity_type' => 'SSN'],
                    ['index' => 1, 'error' => 'entity_type_not_found'],
                ],
            ]),
        ], TestClientFactory::TOKENIZE_KEY, null);

        $results = $setup['client']->tokenizeBatch([
            new TokenizeBatchItem(0, 'SSN', '123-45-6789'),
            new TokenizeBatchItem(1, 'MISSING', 'x'),
        ]);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->ok());
        $this->assertSame('[SSN_a]', $results[0]->token);
        $this->assertFalse($results[1]->ok());
        $this->assertSame('entity_type_not_found', $results[1]->error);
        $this->assertSame(
            'Bearer ' . TestClientFactory::TOKENIZE_KEY,
            TestClientFactory::lastAuthorizationHeader($setup['log']->transactions),
        );
    }

    public function testTokenizeBatchAutoChunks(): void
    {
        $chunkSizes = [];
        $mock = new MockHandler([
            static function (RequestInterface $request, array $options) use (&$chunkSizes): Response {
                $payload = $options['json'] ?? json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $chunkSizes[] = count($payload['items']);
                $items = array_map(
                    static fn (array $item): array => [
                        'index' => $item['index'],
                        'token' => '[SSN_' . $item['index'] . ']',
                        'entity_type' => 'SSN',
                    ],
                    $payload['items'],
                );

                return TestClientFactory::jsonResponse(200, ['items' => $items]);
            },
            static function (RequestInterface $request, array $options) use (&$chunkSizes): Response {
                $payload = $options['json'] ?? json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $chunkSizes[] = count($payload['items']);
                $items = array_map(
                    static fn (array $item): array => [
                        'index' => $item['index'],
                        'token' => '[SSN_' . $item['index'] . ']',
                        'entity_type' => 'SSN',
                    ],
                    $payload['items'],
                );

                return TestClientFactory::jsonResponse(200, ['items' => $items]);
            },
        ]);

        $httpClient = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => TestClientFactory::BASE,
            'http_errors' => false,
        ]);

        $client = new SubzeroClient(
            tokenizeKey: TestClientFactory::TOKENIZE_KEY,
            baseUrl: TestClientFactory::BASE,
            httpClient: $httpClient,
        );

        $items = [];
        for ($i = 0; $i < 150; ++$i) {
            $items[] = new TokenizeBatchItem($i, 'SSN', "value-{$i}");
        }

        $results = $client->tokenizeBatch($items, chunkSize: 100);
        $this->assertSame([100, 50], $chunkSizes);
        $this->assertCount(150, $results);
        $this->assertSame('[SSN_149]', $results[149]->token);
    }
}
