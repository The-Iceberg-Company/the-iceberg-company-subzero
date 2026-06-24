<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Iceberg\Subzero\Models\TokenizeBatchItem;
use Iceberg\Subzero\SubzeroClient;

try {
    $client = SubzeroClient::fromEnv();
} catch (\InvalidArgumentException) {
    fwrite(STDERR, "Set SUBZERO_API_KEY (or SUBZERO_TOKENIZE_API_KEY / SUBZERO_REVEAL_API_KEY) and optionally SUBZERO_BASE_URL.\n");
    exit(1);
}

$client->ready();

$token = $client->tokenize('SSN', '123-45-6789');
echo "Tokenized: {$token}\n";

$found = $client->search('SSN', '123-45-6789');
echo "Search hit: {$found}\n";

$value = $client->reveal($token);
echo "Revealed: {$value}\n";

$batch = $client->tokenizeBatch([
    new TokenizeBatchItem(0, 'SSN', '111-11-1111'),
    new TokenizeBatchItem(1, 'SSN', '222-22-2222'),
]);

foreach ($batch as $item) {
    if ($item->ok()) {
        echo "Batch {$item->index}: {$item->token}\n";
    } else {
        echo "Batch {$item->index} error: {$item->error}\n";
    }
}
