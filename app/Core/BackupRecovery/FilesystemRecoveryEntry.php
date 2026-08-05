<?php

namespace Copot\Core\BackupRecovery;

final class FilesystemRecoveryEntry
{
    public const EXISTING_FILE = 'EXISTING_FILE';
    public const ABSENT_BEFORE_OPERATION = 'ABSENT_BEFORE_OPERATION';

    public function __construct(
        private string $path,
        private int $targetSize,
        private string $targetHash,
        private ?string $preOperationState = null,
        private ?int $preOperationSize = null,
        private ?string $preOperationHash = null,
        private ?int $preOperationMode = null,
        private ?string $preImageBytes = null
    ) {
        try {
            $this->path = \Copot\Core\PackageInventoryEntry::normalizePath($path);
        } catch (\Throwable $exception) {
            throw new FilesystemRecoveryException('Filesystem recovery path is invalid.', 0, $exception);
        }
        if (\Copot\Core\PackageOwnership::classify($this->path) !== \Copot\Core\PackageOwnership::PACKAGE_OWNED
            || $targetSize < 0 || preg_match('/^[a-f0-9]{64}$/D', strtolower($targetHash)) !== 1) {
            throw new FilesystemRecoveryException('Filesystem recovery target identity is invalid.');
        }
        $this->targetHash = strtolower($targetHash);
        if ($preOperationState !== null && !in_array($preOperationState, [self::EXISTING_FILE, self::ABSENT_BEFORE_OPERATION], true)) {
            throw new FilesystemRecoveryException('Filesystem recovery pre-operation state is invalid.');
        }
        if ($preOperationState === self::EXISTING_FILE) {
            if ($preOperationSize === null || $preOperationSize < 0 || $preOperationHash === null || preg_match('/^[a-f0-9]{64}$/D', strtolower($preOperationHash)) !== 1 || $preImageBytes === null || strlen($preImageBytes) !== $preOperationSize || hash('sha256', $preImageBytes) !== strtolower($preOperationHash)) {
                throw new FilesystemRecoveryException('Existing-file recovery evidence is invalid.');
            }
            $this->preOperationHash = strtolower($preOperationHash);
        } elseif ($preOperationState === self::ABSENT_BEFORE_OPERATION) {
            if ($preOperationSize !== null || $preOperationHash !== null || $preImageBytes !== null || $preOperationMode !== null) {
                throw new FilesystemRecoveryException('Absent-file recovery evidence contains redundant state.');
            }
        } elseif ($preOperationSize !== null || $preOperationHash !== null || $preImageBytes !== null || $preOperationMode !== null) {
            throw new FilesystemRecoveryException('Unclassified recovery evidence is not allowed.');
        }
        if ($preOperationMode !== null && ($preOperationMode < 0 || $preOperationMode > 0777)) {
            throw new FilesystemRecoveryException('Filesystem recovery mode is invalid.');
        }
    }

    public function path(): string { return $this->path; }
    public function targetSize(): int { return $this->targetSize; }
    public function targetHash(): string { return $this->targetHash; }
    public function preOperationState(): ?string { return $this->preOperationState; }
    public function preOperationSize(): ?int { return $this->preOperationSize; }
    public function preOperationHash(): ?string { return $this->preOperationHash; }
    public function preOperationMode(): ?int { return $this->preOperationMode; }
    public function preImageBytes(): ?string { return $this->preImageBytes; }

    public static function existing(string $path, int $targetSize, string $targetHash, string $bytes, ?int $mode): self
    {
        return new self($path, $targetSize, $targetHash, self::EXISTING_FILE, strlen($bytes), hash('sha256', $bytes), $mode, $bytes);
    }

    public static function absent(string $path, int $targetSize, string $targetHash): self
    {
        return new self($path, $targetSize, $targetHash, self::ABSENT_BEFORE_OPERATION);
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'pre_operation_state' => $this->preOperationState,
            'pre_operation_size' => $this->preOperationSize,
            'pre_operation_hash' => $this->preOperationHash,
            'pre_operation_mode' => $this->preOperationMode,
            'target_size' => $this->targetSize,
            'target_hash' => $this->targetHash,
            'pre_image_base64' => $this->preImageBytes === null ? null : base64_encode($this->preImageBytes),
        ];
    }
}
