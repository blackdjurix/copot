<?php

namespace Copot\Core;

final class PackageRemovalSet
{
    public const EXPLICIT_REMOVAL = 0x01;
    public const RENAME_SOURCE_REMOVAL = 0x02;
    public const OBSOLETE = 0x01;
    public const REPLACED = 0x02;
    public const RENAMED = 0x03;

    public static function serialize(string $planIdentity, array $actions): string
    {
        self::text($planIdentity, 'Plan identity');
        if ($actions !== [] && array_keys($actions) !== range(0, count($actions) - 1)) { throw new \InvalidArgumentException('Removal action ordinals must be contiguous.'); }
        $bytes = "COPOT-REMOVAL-SET\0" . chr(1) . self::frame($planIdentity) . pack('N', count($actions));
        foreach ($actions as $ordinal => $action) {
            if (!is_array($action) || array_keys($action) !== ['kind', 'reason', 'source_root', 'source_path', 'target_root', 'target_path', 'expected_source_byte_size', 'expected_source_sha256']) { throw new \InvalidArgumentException('Removal action shape is invalid.'); }
            $kind = $action['kind']; $reason = $action['reason'];
            if (!in_array($kind, [self::EXPLICIT_REMOVAL, self::RENAME_SOURCE_REMOVAL], true) || !in_array($reason, [self::OBSOLETE, self::REPLACED, self::RENAMED], true)) { throw new \InvalidArgumentException('Removal action enum is invalid.'); }
            if (($kind === self::RENAME_SOURCE_REMOVAL && $reason !== self::RENAMED) || ($kind === self::EXPLICIT_REMOVAL && $reason === self::RENAMED)) { throw new \InvalidArgumentException('Removal action kind and reason do not agree.'); }
            self::rootPath($action['source_root'], $action['source_path']);
            if ($kind === self::RENAME_SOURCE_REMOVAL) { self::rootPath($action['target_root'], $action['target_path']); }
            elseif ($action['target_root'] !== '' || $action['target_path'] !== '') { throw new \InvalidArgumentException('Explicit removals cannot carry a target.'); }
            if (!is_int($action['expected_source_byte_size']) || $action['expected_source_byte_size'] < 0 || preg_match('/^[a-f0-9]{64}$/D', (string) $action['expected_source_sha256']) !== 1) { throw new \InvalidArgumentException('Removal source identity is invalid.'); }
            $bytes .= pack('N', $ordinal) . chr($kind) . chr($reason) . self::frame($action['source_root']) . self::frame($action['source_path']) . self::frame($action['target_root']) . self::frame($action['target_path']) . self::u64($action['expected_source_byte_size']) . hex2bin($action['expected_source_sha256']);
        }
        return $bytes;
    }

    public static function digest(string $planIdentity, array $actions): string { return hash('sha256', self::serialize($planIdentity, $actions)); }

    private static function rootPath(mixed $root, mixed $path): void
    { if (!is_string($root) || !in_array($root, [CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PUBLIC_ROOT], true) || !is_string($path)) { throw new \InvalidArgumentException('Removal path is invalid.'); } CanonicalTargetInventoryEntry::assertPath($path); ProtectedResourcePolicy::assertMutable($root, $path); }
    private static function text(mixed $value, string $label): void { if (!is_string($value) || $value === '' || trim($value) !== $value) { throw new \InvalidArgumentException($label . ' is invalid.'); } CanonicalText::assert($value, $label); }
    private static function frame(string $value): string { CanonicalText::assert($value); return pack('N', strlen($value)) . $value; }
    private static function u64(int $value): string { if ($value < 0 || $value > PHP_INT_MAX) { throw new \InvalidArgumentException('Integer is outside serialization bounds.'); } return pack('N2', intdiv($value, 4294967296), $value % 4294967296); }
}
