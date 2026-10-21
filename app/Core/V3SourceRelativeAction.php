<?php

namespace Copot\Core;

final class V3SourceRelativeAction
{
    public const DELIVER = 'deliver';
    public const RETAIN = 'retain';
    public const REPLACE = 'replace';
    public const REMOVE = 'remove';
    public const RENAME = 'rename';

    public function __construct(
        private string $action,
        private string $root,
        private string $path,
        private int $byteSize,
        private string $sha256,
        private ?string $sourceRoot = null,
        private ?string $sourcePath = null,
        private ?string $reason = null,
        private ?string $expectedTargetState = null,
        private ?int $expectedTargetByteSize = null,
        private ?string $expectedTargetSha256 = null
    ) {
        if (!in_array($action, [self::DELIVER, self::RETAIN, self::REPLACE, self::REMOVE, self::RENAME], true)
            || !in_array($root, [CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PUBLIC_ROOT], true)
            || $byteSize < 0 || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw new \InvalidArgumentException('V3 source-relative action is invalid.');
        }
        CanonicalTargetInventoryEntry::assertPath($path);
        if ($sourceRoot !== null || $sourcePath !== null) {
            if (!in_array($sourceRoot, [CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PUBLIC_ROOT], true) || !is_string($sourcePath)) { throw new \InvalidArgumentException('V3 source-relative source identity is invalid.'); }
            CanonicalTargetInventoryEntry::assertPath($sourcePath);
        }
        if ($reason !== null && !in_array($reason, ['obsolete', 'replaced', 'renamed'], true)) { throw new \InvalidArgumentException('V3 source-relative reason is invalid.'); }
        if ($expectedTargetState !== null && !in_array($expectedTargetState, ['ABSENT', 'PRESENT'], true)) { throw new \InvalidArgumentException('Expected target state is invalid.'); }
        if ($expectedTargetState === 'ABSENT' && ($expectedTargetByteSize !== null || $expectedTargetSha256 !== null)) { throw new \InvalidArgumentException('Absent target state cannot carry an existing target hash.'); }
        if ($expectedTargetState === 'PRESENT' && ($expectedTargetByteSize === null || $expectedTargetByteSize < 0 || !is_string($expectedTargetSha256) || preg_match('/^[a-f0-9]{64}$/D', $expectedTargetSha256) !== 1)) { throw new \InvalidArgumentException('Present target state requires an original byte identity.'); }
    }

    public function action(): string { return $this->action; }
    public function root(): string { return $this->root; }
    public function path(): string { return $this->path; }
    public function byteSize(): int { return $this->byteSize; }
    public function sha256(): string { return $this->sha256; }
    public function sourceRoot(): ?string { return $this->sourceRoot; }
    public function sourcePath(): ?string { return $this->sourcePath; }
    public function reason(): ?string { return $this->reason; }
    public function expectedTargetState(): ?string { return $this->expectedTargetState; }
    public function expectedTargetByteSize(): ?int { return $this->expectedTargetByteSize; }
    public function expectedTargetSha256(): ?string { return $this->expectedTargetSha256; }

    public function toArray(): array
    {
        $result = [
            'action' => $this->action,
            'root' => $this->root,
            'path' => $this->path,
            'byte_size' => $this->byteSize,
            'sha256' => $this->sha256,
            'source_root' => $this->sourceRoot,
            'source_path' => $this->sourcePath,
            'reason' => $this->reason,
        ];
        if ($this->expectedTargetState !== null) {
            $result['expected_target_state'] = $this->expectedTargetState;
            $result['expected_target_byte_size'] = $this->expectedTargetByteSize;
            $result['expected_target_sha256'] = $this->expectedTargetSha256;
        }
        return $result;
    }
}
