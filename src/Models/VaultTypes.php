<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Models;

final class SubzeroConstants
{
    public const TOKENIZE_BATCH_MAX_ROWS = 100;
    public const REVEAL_BATCH_MAX_ROWS = 100;
    public const RESOLVE_BATCH_MAX_ROWS = 100;
}

enum TokenizeBatchSource: string
{
    case Fivetran = 'fivetran';
    case Airbyte = 'airbyte';
    case Dbt = 'dbt';
    case Spark = 'spark';
    case Dataflow = 'dataflow';
}

final class TokenizeBatchItem
{
    public function __construct(
        public readonly int $index,
        public readonly string $entityType,
        public readonly string $value,
    ) {
    }
}

final class TokenizeBatchContext
{
    public function __construct(
        public readonly ?TokenizeBatchSource $source = null,
        public readonly ?string $pipelineId = null,
        public readonly ?string $syncId = null,
    ) {
    }
}

final class TokenizeBatchResultItem
{
    public function __construct(
        public readonly int $index,
        public readonly ?string $token = null,
        public readonly ?string $entityType = null,
        public readonly ?string $error = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->error === null && $this->token !== null;
    }
}

enum WarehouseRevealSource: string
{
    case Snowflake = 'snowflake';
    case Databricks = 'databricks';
    case Bigquery = 'bigquery';
}

final class WarehouseRevealContext
{
    public function __construct(
        public readonly string $principal,
        public readonly ?WarehouseRevealSource $source = null,
        public readonly ?string $queryId = null,
        public readonly ?string $warehouse = null,
        public readonly ?string $role = null,
        public readonly ?string $jobId = null,
    ) {
    }
}

final class ResolveBatchItem
{
    public function __construct(
        public readonly int $index,
        public readonly string $value,
        public readonly ?bool $passthroughOnMiss = null,
    ) {
    }
}

final class ResolveBatchResultItem
{
    public function __construct(
        public readonly int $index,
        public readonly ?string $value = null,
        public readonly ?ResolveSource $source = null,
        public readonly ?string $token = null,
        public readonly ?string $entityType = null,
        public readonly ?string $error = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->error === null;
    }
}

enum ResolveSource: string
{
    case Passthrough = 'passthrough';
    case Vault = 'vault';
}

final class ResolveResult
{
    public function __construct(
        public readonly string $value,
        public readonly ResolveSource $source,
        public readonly ?string $token = null,
        public readonly ?string $entityType = null,
    ) {
    }
}
