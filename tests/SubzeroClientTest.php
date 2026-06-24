<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Iceberg\Subzero\Exceptions\AuthorizationException;
use Iceberg\Subzero\Exceptions\NotFoundException;
use Iceberg\Subzero\Exceptions\PolicyDeniedException;
use Iceberg\Subzero\Exceptions\SubzeroNotReadyException;
use Iceberg\Subzero\Models\ResolveSource;
use Iceberg\Subzero\SubzeroClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class SubzeroClientTest extends TestCase
{
    public function testTokenizeSendsTokenizeKey(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(200, [
                'token' => '[SSN_abc12345]',
                'entity_type' => 'SSN',
            ]),
        ]);

        $token = $setup['client']->tokenize('SSN', '123-45-6789');
        $this->assertSame('[SSN_abc12345]', $token);
        $this->assertSame(
            'Bearer ' . TestClientFactory::TOKENIZE_KEY,
            TestClientFactory::lastAuthorizationHeader($setup['log']->transactions),
        );
    }

    public function testSearchReturnsToken(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(200, ['token' => '[SSN_abc12345]']),
        ]);

        $token = $setup['client']->search('SSN', '123-45-6789');
        $this->assertSame('[SSN_abc12345]', $token);
    }

    public function testRevealSendsRevealKey(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(200, [
                'token' => '[SSN_abc12345]',
                'entity_type' => 'SSN',
                'value' => '123-45-6789',
            ]),
        ]);

        $value = $setup['client']->reveal('[SSN_abc12345]');
        $this->assertSame('123-45-6789', $value);
        $this->assertSame(
            'Bearer ' . TestClientFactory::REVEAL_KEY,
            TestClientFactory::lastAuthorizationHeader($setup['log']->transactions),
        );
    }

    public function testResolvePassthrough(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(200, [
                'value' => '123-45-6789',
                'source' => 'passthrough',
                'token' => null,
                'entity_type' => null,
            ]),
        ]);

        $result = $setup['client']->resolve('123-45-6789');
        $this->assertSame('123-45-6789', $result->value);
        $this->assertSame(ResolveSource::Passthrough, $result->source);
        $this->assertNull($result->token);
        $this->assertStringContainsString('"passthrough_on_miss":false', TestClientFactory::lastPayloadJson($setup['log']->transactions));
    }

    public function testResolveVault(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(200, [
                'value' => '123-45-6789',
                'source' => 'vault',
                'token' => '[SSN_abc12345]',
                'entity_type' => 'SSN',
            ]),
        ]);

        $result = $setup['client']->resolve('[SSN_abc12345]');
        $this->assertSame(ResolveSource::Vault, $result->source);
        $this->assertSame('[SSN_abc12345]', $result->token);
        $this->assertSame('SSN', $result->entityType);
    }

    public function testResolvePassthroughOnMiss(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(200, [
                'value' => '[SSN_deadbeef]',
                'source' => 'passthrough',
                'token' => null,
                'entity_type' => null,
            ]),
        ]);

        $result = $setup['client']->resolve('[SSN_deadbeef]', passthroughOnMiss: true);
        $this->assertSame(ResolveSource::Passthrough, $result->source);
        $this->assertStringContainsString('"passthrough_on_miss":true', TestClientFactory::lastPayloadJson($setup['log']->transactions));
    }

    public function testSingleApiKeyUsedForBothOperations(): void
    {
        $key = 'sz_live_sharedkey123456789012345';
        $log = new TransactionLog();
        $mock = new MockHandler([
            function (RequestInterface $request, array $options) use (&$log): Response {
                $log->transactions[] = ['request' => $request, 'options' => $options];

                return TestClientFactory::jsonResponse(200, ['token' => '[SSN_abc12345]', 'entity_type' => 'SSN']);
            },
            function (RequestInterface $request, array $options) use (&$log): Response {
                $log->transactions[] = ['request' => $request, 'options' => $options];

                return TestClientFactory::jsonResponse(200, [
                    'token' => '[SSN_abc12345]',
                    'entity_type' => 'SSN',
                    'value' => '123-45-6789',
                ]);
            },
        ]);
        $stack = HandlerStack::create($mock);
        $httpClient = new Client([
            'handler' => $stack,
            'base_uri' => TestClientFactory::BASE,
            'http_errors' => false,
        ]);

        $client = new SubzeroClient(apiKey: $key, baseUrl: TestClientFactory::BASE, httpClient: $httpClient);
        $client->tokenize('SSN', '123-45-6789');
        $client->reveal('[SSN_abc12345]');

        foreach ($log->transactions as $entry) {
            $this->assertSame('Bearer ' . $key, TestClientFactory::lastAuthorizationHeader([$entry]));
        }
    }

    public function testRevealPolicyDeniedMapsReason(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(403, [
                'detail' => 'Policy denied',
                'reason' => 'policy_denied',
            ]),
        ]);

        try {
            $setup['client']->reveal('[SSN_abc12345]');
            $this->fail('Expected PolicyDeniedException');
        } catch (PolicyDeniedException $exception) {
            $this->assertSame('policy_denied', $exception->reason);
            $this->assertStringContainsStringIgnoringCase('dashboard', $exception->getMessage());
        }
    }

    public function testRevealInsufficientScopeMapsReason(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(403, [
                'detail' => 'Insufficient scope',
                'reason' => 'insufficient_scope',
            ]),
        ]);

        $this->expectException(AuthorizationException::class);
        $setup['client']->reveal('[SSN_abc12345]');
    }

    public function testSearchNotFound(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(404, ['detail' => 'Token not found']),
        ]);

        $this->expectException(NotFoundException::class);
        $setup['client']->search('SSN', '000-00-0000');
    }

    public function testReadySuccess(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(200, ['status' => 'ready']),
        ]);

        $body = $setup['client']->ready();
        $this->assertSame('ready', $body['status']);
    }

    public function testReadyNotReady(): void
    {
        $setup = TestClientFactory::create([
            TestClientFactory::jsonResponse(503, ['status' => 'not_ready']),
        ]);

        $this->expectException(SubzeroNotReadyException::class);
        $setup['client']->ready();
    }

    public function testConstructorRequiresCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SubzeroClient(baseUrl: TestClientFactory::BASE);
    }
}
