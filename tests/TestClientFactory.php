<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Iceberg\Subzero\SubzeroClient;
use Psr\Http\Message\RequestInterface;

final class TestClientFactory
{
    public const BASE = 'http://testserver';
    public const TOKENIZE_KEY = 'sz_live_tokenizekey123456789012345';
    public const REVEAL_KEY = 'sz_live_revealkey123456789012345';
    public const PROXY_KEY = 'sz_live_proxykey123456789012345';

    /** @param list<Response|callable(RequestInterface, array<string, mixed>): Response> $responses */
    /** @return array{client: SubzeroClient, log: TransactionLog} */
    public static function create(
        array $responses,
        ?string $tokenizeKey = self::TOKENIZE_KEY,
        ?string $revealKey = self::REVEAL_KEY,
        ?string $proxyKey = null,
        ?string $revealGrantKey = null,
        bool $captureCallerContext = true,
    ): array {
        $log = new TransactionLog();
        $queue = [];

        foreach ($responses as $response) {
            if ($response instanceof Response) {
                $queue[] = function (RequestInterface $request, array $options) use ($response, $log): Response {
                    $log->transactions[] = ['request' => $request, 'options' => $options];

                    return $response;
                };
                continue;
            }

            $queue[] = function (RequestInterface $request, array $options) use ($response, $log): Response {
                $log->transactions[] = ['request' => $request, 'options' => $options];

                return $response($request, $options);
            };
        }

        $mock = new MockHandler($queue);
        $httpClient = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => self::BASE,
            'http_errors' => false,
        ]);

        $client = new SubzeroClient(
            tokenizeKey: $tokenizeKey,
            revealKey: $revealKey,
            revealGrantKey: $revealGrantKey,
            proxyKey: $proxyKey,
            baseUrl: self::BASE,
            captureCallerContext: $captureCallerContext,
            httpClient: $httpClient,
        );

        return ['client' => $client, 'log' => $log];
    }

    public static function jsonResponse(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    /** @param list<array{request: RequestInterface, options: array<string, mixed>}> $transactions */
    public static function lastAuthorizationHeader(array $transactions): ?string
    {
        if ($transactions === []) {
            return null;
        }

        $transaction = $transactions[array_key_last($transactions)];
        $request = $transaction['request'];
        foreach (['Authorization', 'authorization'] as $name) {
            if ($request->hasHeader($name)) {
                return $request->getHeaderLine($name);
            }
        }

        $headers = $transaction['options']['headers'] ?? [];
        if (is_array($headers)) {
            foreach (['Authorization', 'authorization'] as $name) {
                if (!isset($headers[$name])) {
                    continue;
                }

                $value = $headers[$name];

                return is_array($value) ? (string) $value[0] : (string) $value;
            }
        }

        return null;
    }

    /** @param list<array{request: RequestInterface, options: array<string, mixed>}> $transactions */
    public static function lastPayloadJson(array $transactions): string
    {
        if ($transactions === []) {
            return '';
        }

        $transaction = $transactions[array_key_last($transactions)];
        if (isset($transaction['options']['json'])) {
            return json_encode($transaction['options']['json'], JSON_THROW_ON_ERROR);
        }

        $body = (string) $transaction['request']->getBody();
        if ($body !== '') {
            return $body;
        }

        return '';
    }
}

final class TransactionLog
{
    /** @var list<array{request: RequestInterface, options: array<string, mixed>}> */
    public array $transactions = [];
}
