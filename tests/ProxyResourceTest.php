<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Tests;

use Iceberg\Subzero\SubzeroClient;
use PHPUnit\Framework\TestCase;

final class ProxyResourceTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function scanResponse(): array
    {
        return [
            'entity_types_scanned' => ['SSN', 'EMAIL'],
            'match_counts' => ['SSN' => 1],
            'messages' => [[
                'role' => 'user',
                'original' => 'Patient SSN 123-45-6789',
                'tokenized' => 'Patient SSN [SSN_abc12345]',
                'matches' => [[
                    'entity_type' => 'SSN',
                    'start' => 12,
                    'end' => 23,
                    'matched_text' => '123-45-6789',
                    'token' => '[SSN_abc12345]',
                ]],
                'skipped_overlaps' => [],
            ]],
            'fields' => [[
                'path' => 'messages[0].content',
                'original' => 'Patient SSN 123-45-6789',
                'tokenized' => 'Patient SSN [SSN_abc12345]',
                'matches' => [[
                    'entity_type' => 'SSN',
                    'start' => 12,
                    'end' => 23,
                    'matched_text' => '123-45-6789',
                    'token' => '[SSN_abc12345]',
                ]],
                'skipped_overlaps' => [],
            ]],
            'body' => null,
        ];
    }

    /** @return array<string, mixed> */
    private static function discoverResponse(): array
    {
        return [
            'entity_types_scanned' => ['SSN', 'EMAIL'],
            'match_counts' => ['SSN' => 1],
            'messages' => [[
                'role' => 'user',
                'text' => 'Patient SSN 123-45-6789',
                'matches' => [[
                    'entity_type' => 'SSN',
                    'start' => 12,
                    'end' => 23,
                    'matched_text' => '123-45-6789',
                    'score' => 1.0,
                ]],
                'skipped_overlaps' => [],
            ]],
            'fields' => [[
                'path' => 'messages[0].content',
                'text' => 'Patient SSN 123-45-6789',
                'matches' => [[
                    'entity_type' => 'SSN',
                    'start' => 12,
                    'end' => 23,
                    'matched_text' => '123-45-6789',
                    'score' => 1.0,
                ]],
                'skipped_overlaps' => [],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private static function restructureResponse(): array
    {
        return [
            'tokens_found' => 1,
            'resolved_count' => 1,
            'denied_count' => 0,
            'messages' => [[
                'role' => 'assistant',
                'original' => 'SSN on file: [SSN_abc12345]',
                'restructured' => 'SSN on file: 123-45-6789',
                'tokens' => [[
                    'token' => '[SSN_abc12345]',
                    'start' => 14,
                    'end' => 28,
                    'resolved' => true,
                    'entity_type' => 'SSN',
                    'value' => '123-45-6789',
                ]],
            ]],
            'fields' => [[
                'path' => 'messages[0].content',
                'original' => 'SSN on file: [SSN_abc12345]',
                'restructured' => 'SSN on file: 123-45-6789',
                'tokens' => [[
                    'token' => '[SSN_abc12345]',
                    'start' => 14,
                    'end' => 28,
                    'resolved' => true,
                    'entity_type' => 'SSN',
                    'value' => '123-45-6789',
                ]],
            ]],
            'body' => null,
        ];
    }

    public function testScanMessages(): void
    {
        $setup = TestClientFactory::create(
            [TestClientFactory::jsonResponse(200, self::scanResponse())],
            null,
            null,
            TestClientFactory::PROXY_KEY,
        );

        $result = $setup['client']->proxy->scan(
            messages: [['role' => 'user', 'content' => 'Patient SSN 123-45-6789']],
        );

        $this->assertSame(1, $result->matchCounts['SSN']);
        $this->assertSame('Patient SSN [SSN_abc12345]', $result->messages[0]->tokenized);
        $this->assertSame('messages[0].content', $result->fields[0]->path);
        $this->assertSame(
            'Bearer ' . TestClientFactory::PROXY_KEY,
            TestClientFactory::lastAuthorizationHeader($setup['log']->transactions),
        );
        $this->assertStringContainsString('"messages"', TestClientFactory::lastPayloadJson($setup['log']->transactions));
    }

    public function testScanBody(): void
    {
        $response = self::scanResponse();
        $response['messages'] = [];
        $response['fields'] = [[
            'path' => 'messages[0].content[0].text',
            'original' => 'SSN 123-45-6789',
            'tokenized' => 'SSN [SSN_abc12345]',
            'matches' => self::scanResponse()['fields'][0]['matches'],
            'skipped_overlaps' => [],
        ]];
        $response['body'] = [
            'messages' => [[
                'role' => 'user',
                'content' => [['type' => 'text', 'text' => 'SSN [SSN_abc12345]']],
            ]],
        ];

        $setup = TestClientFactory::create(
            [TestClientFactory::jsonResponse(200, $response)],
            null,
            null,
            TestClientFactory::PROXY_KEY,
        );

        $result = $setup['client']->proxy->scan(body: [
            'messages' => [[
                'role' => 'user',
                'content' => [['type' => 'text', 'text' => 'SSN 123-45-6789']],
            ]],
        ]);

        $this->assertSame('messages[0].content[0].text', $result->fields[0]->path);
        $this->assertNotNull($result->body);
        $this->assertStringContainsString('"body"', TestClientFactory::lastPayloadJson($setup['log']->transactions));
    }

    public function testDiscoverMessages(): void
    {
        $setup = TestClientFactory::create(
            [TestClientFactory::jsonResponse(200, self::discoverResponse())],
            null,
            null,
            TestClientFactory::PROXY_KEY,
        );

        $result = $setup['client']->proxy->discover(
            messages: [['role' => 'user', 'content' => 'Patient SSN 123-45-6789']],
        );

        $this->assertSame(1.0, $result->messages[0]->matches[0]->score);
        $this->assertSame('messages[0].content', $result->fields[0]->path);
    }

    public function testRestructureMessages(): void
    {
        $setup = TestClientFactory::create(
            [TestClientFactory::jsonResponse(200, self::restructureResponse())],
            null,
            null,
            TestClientFactory::PROXY_KEY,
        );

        $result = $setup['client']->proxy->restructure(
            messages: [['role' => 'assistant', 'content' => 'SSN on file: [SSN_abc12345]']],
        );

        $this->assertSame(1, $result->resolvedCount);
        $this->assertSame('SSN on file: 123-45-6789', $result->messages[0]->restructured);
        $this->assertSame('SSN', $result->messages[0]->tokens[0]->entityType);
    }

    public function testScanRequiresPayload(): void
    {
        $client = new SubzeroClient(proxyKey: TestClientFactory::PROXY_KEY, baseUrl: TestClientFactory::BASE);
        $this->expectException(\InvalidArgumentException::class);
        $client->proxy->scan();
    }
}
