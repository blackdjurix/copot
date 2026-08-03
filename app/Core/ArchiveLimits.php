<?php

namespace Copot\Core;

final class ArchiveLimits
{
    public const DEFAULT_MAX_ARCHIVE_BYTES = 67108864;
    public const DEFAULT_MAX_ENTRIES = 5000;
    public const DEFAULT_MAX_TOTAL_EXTRACTED_BYTES = 268435456;
    public const DEFAULT_MAX_FILE_BYTES = 67108864;
    public const DEFAULT_MAX_COMPRESSION_RATIO = 100;
    public const DEFAULT_MAX_PATH_BYTES = 240;
    public const DEFAULT_MAX_NESTING = 32;

    public function __construct(
        private int $maxArchiveBytes = self::DEFAULT_MAX_ARCHIVE_BYTES,
        private int $maxEntries = self::DEFAULT_MAX_ENTRIES,
        private int $maxTotalExtractedBytes = self::DEFAULT_MAX_TOTAL_EXTRACTED_BYTES,
        private int $maxFileBytes = self::DEFAULT_MAX_FILE_BYTES,
        private int $maxCompressionRatio = self::DEFAULT_MAX_COMPRESSION_RATIO,
        private int $maxPathBytes = self::DEFAULT_MAX_PATH_BYTES,
        private int $maxNesting = self::DEFAULT_MAX_NESTING
    ) {
        foreach ([$maxArchiveBytes, $maxEntries, $maxTotalExtractedBytes, $maxFileBytes, $maxCompressionRatio, $maxPathBytes, $maxNesting] as $limit) {
            if ($limit < 1) {
                throw new \InvalidArgumentException('Archive limits must be positive.');
            }
        }
    }

    public function maxArchiveBytes(): int { return $this->maxArchiveBytes; }
    public function maxEntries(): int { return $this->maxEntries; }
    public function maxTotalExtractedBytes(): int { return $this->maxTotalExtractedBytes; }
    public function maxFileBytes(): int { return $this->maxFileBytes; }
    public function maxCompressionRatio(): int { return $this->maxCompressionRatio; }
    public function maxPathBytes(): int { return $this->maxPathBytes; }
    public function maxNesting(): int { return $this->maxNesting; }
}
