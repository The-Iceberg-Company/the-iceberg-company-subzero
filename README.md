# Subzero PHP SDK

Thin PHP client for the [Subzero](../subzero-api/) tokenization vault.

Configure entity types, API keys, and reveal policies in the **dashboard** first. This SDK is for server-side vault operations only.

## Requirements

- PHP 8.1+
- [Composer](https://getcomposer.org/)

## Install

```bash
cd php-sdk
composer install
```

In another project:

```bash
composer require the-iceberg-company/subzero
```

## Quick start

```php
use Iceberg\Subzero\SubzeroClient;

$client = new SubzeroClient(
    tokenizeKey: 'sz_live_...',   // tokenize + search + batch
    revealKey: 'sz_live_...',     // reveal
);
$client->ready();

$token = $client->tokenize('SSN', '123-45-6789');
$found = $client->search('SSN', '123-45-6789');
$value = $client->reveal($token);
$resolved = $client->resolve('123-45-6789');
```

Or from environment variables:

```php
$client = SubzeroClient::fromEnv();
$client->ready();
```

A single `apiKey` works when one key has both scopes (e.g. `admin`):

```php
$client = new SubzeroClient(apiKey: 'sz_live_...');
```

## API key scopes

| Scope | SDK methods |
|-------|-------------|
| `tokenize` | `tokenize`, `search`, `tokenizeBatch`, `proxy->scan`, `proxy->discover`, `proxy->restructure` |
| `proxy` | `proxy->scan`, `proxy->discover`, `proxy->restructure` |
| `reveal` | `reveal`, `resolve` (requires a matching reveal policy in the dashboard) |
| `reveal_grant` | `createRevealGrant` (BFF for Elements click-to-reveal) |
| `admin` | All of the above (bypasses reveal policy) |

Use separate `tokenizeKey`, `revealKey`, and `proxyKey` in production for least privilege.

## Reveal caller context (audit)

By default, `reveal`, `resolve`, and `createRevealGrant` attach optional **`caller_context`** for audit logging. Auto-capture uses the first frame outside `Iceberg\Subzero\` when enabled (default).

```php
use Iceberg\Subzero\Models\RevealCallerContext;

$client = new SubzeroClient(revealKey: '...', captureCallerContext: false);
$client->reveal($token, new RevealCallerContext(file: 'Billing.php', line: 42, sdk: 'php'));
```

**Privacy:** auto-capture may include file paths in audit logs — disable or pass opaque refs if needed.

## Environment variables

| Variable | Purpose |
|----------|---------|
| `SUBZERO_API_KEY` | Single key when one key covers multiple scopes |
| `SUBZERO_TOKENIZE_API_KEY` | Tokenize/search/batch only |
| `SUBZERO_REVEAL_API_KEY` | Reveal/resolve only |
| `SUBZERO_PROXY_API_KEY` | LLM proxy scan/discover/restructure only |
| `SUBZERO_BASE_URL` | API host (no trailing `/v1`) |

## LLM proxy preview

Dry-run scan, discover, and restructure. No upstream LLM call.

```php
$scan = $client->proxy->scan(
    messages: [['role' => 'user', 'content' => 'Patient SSN 123-45-6789']],
);
// $scan->messages[0]->tokensByEntityType['SSN'] — grouped tokens by entity type

$discover = $client->proxy->discover(
    messages: [['role' => 'user', 'content' => 'Patient SSN 123-45-6789']],
);

$result = $client->proxy->restructure(
    messages: [['role' => 'assistant', 'content' => 'SSN on file: [SSN_abc12345]']],
);
```

## Batch tokenize

For ETL pipelines. Max **100** items per API request; the SDK auto-chunks larger lists.

```php
use Iceberg\Subzero\Models\TokenizeBatchContext;
use Iceberg\Subzero\Models\TokenizeBatchItem;
use Iceberg\Subzero\Models\TokenizeBatchSource;

$results = $client->tokenizeBatch(
    [
        new TokenizeBatchItem(0, 'SSN', '123-45-6789'),
        new TokenizeBatchItem(1, 'SSN', '987-65-4321'),
    ],
    context: new TokenizeBatchContext(source: TokenizeBatchSource::Dbt, pipelineId: 'contacts'),
);

foreach ($results as $item) {
    if ($item->ok()) {
        echo "{$item->index}: {$item->token}\n";
    } else {
        echo "{$item->index} error: {$item->error}\n";
    }
}
```

## Errors

The SDK throws typed exceptions from API `reason` codes — match on `$exception->reason`, not message text:

| Exception | Typical cause |
|-----------|---------------|
| `PolicyDeniedException` | Missing reveal policy |
| `AuthorizationException` | Wrong key scope |
| `NotFoundException` | Search miss or unknown token |
| `AuthenticationException` | Invalid or revoked key |
| `SubzeroNotReadyException` | API down or migrations pending |

## Example

With the API running and keys configured in the dashboard:

```bash
export SUBZERO_TOKENIZE_API_KEY=sz_live_...
export SUBZERO_REVEAL_API_KEY=sz_live_...
php examples/vault_demo.php
```

## Tests

```bash
composer test
```

## Related

- [Python SDK](../python-sdk/)
- [Node SDK](../node-sdk/)
- [C# SDK](../c%23-sdk/)
- [Vault API](../subzero-api/docs/vault-api.md)
