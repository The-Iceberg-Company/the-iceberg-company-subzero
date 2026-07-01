<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Models;

final class MatchPreview
{
    public function __construct(
        public readonly string $entityType,
        public readonly int $start,
        public readonly int $end,
        public readonly string $matchedText,
        public readonly string $token,
    ) {
    }
}

final class SkippedMatchPreview
{
    public function __construct(
        public readonly string $entityType,
        public readonly int $start,
        public readonly int $end,
        public readonly string $matchedText,
        public readonly string $reason,
    ) {
    }
}

final class FieldScanPreview
{
    /** @param list<MatchPreview> $matches */
    /** @param list<SkippedMatchPreview> $skippedOverlaps */
    /** @param array<string, list<string>> $tokensByEntityType */
    public function __construct(
        public readonly string $path,
        public readonly string $original,
        public readonly string $tokenized,
        public readonly array $matches,
        public readonly array $skippedOverlaps,
        public readonly array $tokensByEntityType = [],
    ) {
    }
}

final class MessageScanPreview
{
    /** @param list<MatchPreview> $matches */
    /** @param list<SkippedMatchPreview> $skippedOverlaps */
    /** @param array<string, list<string>> $tokensByEntityType */
    public function __construct(
        public readonly ?string $role,
        public readonly string $original,
        public readonly string $tokenized,
        public readonly array $matches,
        public readonly array $skippedOverlaps,
        public readonly array $tokensByEntityType = [],
    ) {
    }
}

final class ScanPreviewResult
{
    /** @param list<string> $entityTypesScanned */
    /** @param array<string, int> $matchCounts */
    /** @param list<MessageScanPreview> $messages */
    /** @param list<FieldScanPreview> $fields */
    public function __construct(
        public readonly array $entityTypesScanned,
        public readonly array $matchCounts,
        public readonly array $messages,
        public readonly array $fields,
        public readonly ?array $body,
    ) {
    }
}

final class TokenResolvePreview
{
    public function __construct(
        public readonly string $token,
        public readonly int $start,
        public readonly int $end,
        public readonly bool $resolved,
        public readonly ?string $entityType = null,
        public readonly ?string $value = null,
    ) {
    }
}

final class FieldRestructurePreview
{
    /** @param list<TokenResolvePreview> $tokens */
    public function __construct(
        public readonly string $path,
        public readonly string $original,
        public readonly string $restructured,
        public readonly array $tokens,
    ) {
    }
}

final class MessageRestructurePreview
{
    /** @param list<TokenResolvePreview> $tokens */
    public function __construct(
        public readonly ?string $role,
        public readonly string $original,
        public readonly string $restructured,
        public readonly array $tokens,
    ) {
    }
}

final class RestructurePreviewResult
{
    /** @param list<MessageRestructurePreview> $messages */
    /** @param list<FieldRestructurePreview> $fields */
    public function __construct(
        public readonly int $tokensFound,
        public readonly int $resolvedCount,
        public readonly int $deniedCount,
        public readonly array $messages,
        public readonly array $fields,
        public readonly ?array $body,
    ) {
    }
}

final class MatchDiscoverPreview
{
    public function __construct(
        public readonly string $entityType,
        public readonly int $start,
        public readonly int $end,
        public readonly string $matchedText,
        public readonly float $score,
    ) {
    }
}

final class SkippedDiscoverPreview
{
    public function __construct(
        public readonly string $entityType,
        public readonly int $start,
        public readonly int $end,
        public readonly string $matchedText,
        public readonly float $score,
        public readonly string $reason,
    ) {
    }
}

final class FieldDiscoverPreview
{
    /** @param list<MatchDiscoverPreview> $matches */
    /** @param list<SkippedDiscoverPreview> $skippedOverlaps */
    public function __construct(
        public readonly string $path,
        public readonly string $text,
        public readonly array $matches,
        public readonly array $skippedOverlaps,
    ) {
    }
}

final class MessageDiscoverPreview
{
    /** @param list<MatchDiscoverPreview> $matches */
    /** @param list<SkippedDiscoverPreview> $skippedOverlaps */
    public function __construct(
        public readonly ?string $role,
        public readonly string $text,
        public readonly array $matches,
        public readonly array $skippedOverlaps,
    ) {
    }
}

final class DiscoverPreviewResult
{
    /** @param list<string> $entityTypesScanned */
    /** @param array<string, int> $matchCounts */
    /** @param list<MessageDiscoverPreview> $messages */
    /** @param list<FieldDiscoverPreview> $fields */
    public function __construct(
        public readonly array $entityTypesScanned,
        public readonly array $matchCounts,
        public readonly array $messages,
        public readonly array $fields,
    ) {
    }
}
