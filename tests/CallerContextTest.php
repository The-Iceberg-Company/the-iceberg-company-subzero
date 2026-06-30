<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Tests;

use Iceberg\Subzero\Models\RevealCallerContext;
use PHPUnit\Framework\TestCase;

final class CallerContextTest extends TestCase
{
    public function testRevealIncludesAutoCallerContext(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(200, [
                'token' => '[SSN_abc12345]',
                'entity_type' => 'SSN',
                'value' => '123-45-6789',
            ]),
        ]);

        $setup['client']->reveal('[SSN_abc12345]');
        $payload = TestClientFactory::lastPayloadJson($setup['log']->transactions);
        $body = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('caller_context', $body);
        $this->assertSame('php', $body['caller_context']['sdk']);
    }

    public function testRevealOptOutCallerContext(): void
    {
        $setup = TestClientFactory::create(
            responses: [
                TestClientFactory::jsonResponse(200, [
                    'token' => '[SSN_abc12345]',
                    'entity_type' => 'SSN',
                    'value' => '123-45-6789',
                ]),
            ],
            captureCallerContext: false,
        );

        $setup['client']->reveal('[SSN_abc12345]');
        $payload = TestClientFactory::lastPayloadJson($setup['log']->transactions);
        $body = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('caller_context', $body);
    }

    public function testRevealManualCallerContextOverride(): void
    {
        $setup = TestClientFactory::create(
            responses: [
                TestClientFactory::jsonResponse(200, [
                    'token' => '[SSN_abc12345]',
                    'entity_type' => 'SSN',
                    'value' => '123-45-6789',
                ]),
            ],
            captureCallerContext: false,
        );

        $setup['client']->reveal(
            '[SSN_abc12345]',
            new RevealCallerContext(file: 'custom.php', line: 7, sdk: 'php'),
        );
        $payload = TestClientFactory::lastPayloadJson($setup['log']->transactions);
        $body = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('custom.php', $body['caller_context']['file']);
    }
}
