<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Models;

final class ProxyParsers
{
    /** @param array<string, mixed> $data */
    public static function parseScanPreviewResult(array $data): ScanPreviewResult
    {
        return new ScanPreviewResult(
            entityTypesScanned: self::stringList($data, 'entity_types_scanned'),
            matchCounts: self::intMap($data, 'match_counts'),
            messages: array_map(
                static fn (array $item): MessageScanPreview => self::parseMessageScanPreview($item),
                self::list($data, 'messages'),
            ),
            fields: array_map(
                static fn (array $item): FieldScanPreview => self::parseFieldScanPreview($item),
                self::list($data, 'fields'),
            ),
            body: isset($data['body']) && is_array($data['body']) ? $data['body'] : null,
        );
    }

    /** @param array<string, mixed> $data */
    public static function parseDiscoverPreviewResult(array $data): DiscoverPreviewResult
    {
        return new DiscoverPreviewResult(
            entityTypesScanned: self::stringList($data, 'entity_types_scanned'),
            matchCounts: self::intMap($data, 'match_counts'),
            messages: array_map(
                static fn (array $item): MessageDiscoverPreview => self::parseMessageDiscoverPreview($item),
                self::list($data, 'messages'),
            ),
            fields: array_map(
                static fn (array $item): FieldDiscoverPreview => self::parseFieldDiscoverPreview($item),
                self::list($data, 'fields'),
            ),
        );
    }

    /** @param array<string, mixed> $data */
    public static function parseRestructurePreviewResult(array $data): RestructurePreviewResult
    {
        return new RestructurePreviewResult(
            tokensFound: (int) $data['tokens_found'],
            resolvedCount: (int) $data['resolved_count'],
            deniedCount: (int) $data['denied_count'],
            messages: array_map(
                static fn (array $item): MessageRestructurePreview => self::parseMessageRestructurePreview($item),
                self::list($data, 'messages'),
            ),
            fields: array_map(
                static fn (array $item): FieldRestructurePreview => self::parseFieldRestructurePreview($item),
                self::list($data, 'fields'),
            ),
            body: isset($data['body']) && is_array($data['body']) ? $data['body'] : null,
        );
    }

    /** @param array<string, mixed> $data */
    private static function parseMessageScanPreview(array $data): MessageScanPreview
    {
        return new MessageScanPreview(
            role: self::nullableString($data, 'role'),
            original: (string) $data['original'],
            tokenized: (string) $data['tokenized'],
            matches: array_map(
                static fn (array $item): MatchPreview => self::parseMatchPreview($item),
                self::list($data, 'matches'),
            ),
            skippedOverlaps: array_map(
                static fn (array $item): SkippedMatchPreview => self::parseSkippedMatchPreview($item),
                self::list($data, 'skipped_overlaps'),
            ),
        );
    }

    /** @param array<string, mixed> $data */
    private static function parseFieldScanPreview(array $data): FieldScanPreview
    {
        return new FieldScanPreview(
            path: (string) $data['path'],
            original: (string) $data['original'],
            tokenized: (string) $data['tokenized'],
            matches: array_map(
                static fn (array $item): MatchPreview => self::parseMatchPreview($item),
                self::list($data, 'matches'),
            ),
            skippedOverlaps: array_map(
                static fn (array $item): SkippedMatchPreview => self::parseSkippedMatchPreview($item),
                self::list($data, 'skipped_overlaps'),
            ),
        );
    }

    /** @param array<string, mixed> $data */
    private static function parseMatchPreview(array $data): MatchPreview
    {
        return new MatchPreview(
            entityType: (string) $data['entity_type'],
            start: (int) $data['start'],
            end: (int) $data['end'],
            matchedText: (string) $data['matched_text'],
            token: (string) $data['token'],
        );
    }

    /** @param array<string, mixed> $data */
    private static function parseSkippedMatchPreview(array $data): SkippedMatchPreview
    {
        return new SkippedMatchPreview(
            entityType: (string) $data['entity_type'],
            start: (int) $data['start'],
            end: (int) $data['end'],
            matchedText: (string) $data['matched_text'],
            reason: (string) $data['reason'],
        );
    }

    /** @param array<string, mixed> $data */
    private static function parseMessageDiscoverPreview(array $data): MessageDiscoverPreview
    {
        return new MessageDiscoverPreview(
            role: self::nullableString($data, 'role'),
            text: (string) $data['text'],
            matches: array_map(
                static fn (array $item): MatchDiscoverPreview => self::parseMatchDiscoverPreview($item),
                self::list($data, 'matches'),
            ),
            skippedOverlaps: array_map(
                static fn (array $item): SkippedDiscoverPreview => self::parseSkippedDiscoverPreview($item),
                self::list($data, 'skipped_overlaps'),
            ),
        );
    }

    /** @param array<string, mixed> $data */
    private static function parseFieldDiscoverPreview(array $data): FieldDiscoverPreview
    {
        return new FieldDiscoverPreview(
            path: (string) $data['path'],
            text: (string) $data['text'],
            matches: array_map(
                static fn (array $item): MatchDiscoverPreview => self::parseMatchDiscoverPreview($item),
                self::list($data, 'matches'),
            ),
            skippedOverlaps: array_map(
                static fn (array $item): SkippedDiscoverPreview => self::parseSkippedDiscoverPreview($item),
                self::list($data, 'skipped_overlaps'),
            ),
        );
    }

    /** @param array<string, mixed> $data */
    private static function parseMatchDiscoverPreview(array $data): MatchDiscoverPreview
    {
        return new MatchDiscoverPreview(
            entityType: (string) $data['entity_type'],
            start: (int) $data['start'],
            end: (int) $data['end'],
            matchedText: (string) $data['matched_text'],
            score: (float) $data['score'],
        );
    }

    /** @param array<string, mixed> $data */
    private static function parseSkippedDiscoverPreview(array $data): SkippedDiscoverPreview
    {
        return new SkippedDiscoverPreview(
            entityType: (string) $data['entity_type'],
            start: (int) $data['start'],
            end: (int) $data['end'],
            matchedText: (string) $data['matched_text'],
            score: (float) $data['score'],
            reason: (string) $data['reason'],
        );
    }

    /** @param array<string, mixed> $data */
    private static function parseMessageRestructurePreview(array $data): MessageRestructurePreview
    {
        return new MessageRestructurePreview(
            role: self::nullableString($data, 'role'),
            original: (string) $data['original'],
            restructured: (string) $data['restructured'],
            tokens: array_map(
                static fn (array $item): TokenResolvePreview => self::parseTokenResolvePreview($item),
                self::list($data, 'tokens'),
            ),
        );
    }

    /** @param array<string, mixed> $data */
    private static function parseFieldRestructurePreview(array $data): FieldRestructurePreview
    {
        return new FieldRestructurePreview(
            path: (string) $data['path'],
            original: (string) $data['original'],
            restructured: (string) $data['restructured'],
            tokens: array_map(
                static fn (array $item): TokenResolvePreview => self::parseTokenResolvePreview($item),
                self::list($data, 'tokens'),
            ),
        );
    }

    /** @param array<string, mixed> $data */
    private static function parseTokenResolvePreview(array $data): TokenResolvePreview
    {
        return new TokenResolvePreview(
            token: (string) $data['token'],
            start: (int) $data['start'],
            end: (int) $data['end'],
            resolved: (bool) $data['resolved'],
            entityType: self::nullableString($data, 'entity_type'),
            value: self::nullableString($data, 'value'),
        );
    }

    /** @param array<string, mixed> $data */
    /** @return list<string> */
    private static function stringList(array $data, string $key): array
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            return [];
        }

        return array_values(array_map(static fn ($value): string => (string) $value, $data[$key]));
    }

    /** @param array<string, mixed> $data */
    /** @return array<string, int> */
    private static function intMap(array $data, string $key): array
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            return [];
        }

        $result = [];
        foreach ($data[$key] as $name => $value) {
            $result[(string) $name] = (int) $value;
        }

        return $result;
    }

    /** @param array<string, mixed> $data */
    /** @return list<array<string, mixed>> */
    private static function list(array $data, string $key): array
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            return [];
        }

        $result = [];
        foreach ($data[$key] as $item) {
            if (is_array($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $data */
    private static function nullableString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return (string) $data[$key];
    }
}
