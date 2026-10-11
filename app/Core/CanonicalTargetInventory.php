<?php

namespace Copot\Core;

final class CanonicalTargetInventory
{
    /** @var list<CanonicalTargetInventoryEntry> */
    private array $entries;

    public function __construct(array $entries)
    {
        if ($entries === []) { throw new \InvalidArgumentException('Target inventory cannot be empty.'); }
        $seen = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof CanonicalTargetInventoryEntry) { throw new \InvalidArgumentException('Target inventory entries are invalid.'); }
            $key = $entry->root() . "\0" . $entry->path();
            $collision = strtolower($key);
            if (isset($seen[$collision])) { throw new \InvalidArgumentException('Target inventory contains a duplicate or portable collision.'); }
            $seen[$collision] = true;
        }
        $this->entries = array_values($entries);
    }

    public function entries(): array { return $this->entries; }
    public function serialize(): string { return self::serializeEntries($this->entries); }
    public function fingerprint(): string { return hash('sha256', $this->serialize()); }

    public static function serializeEntries(array $entries): string
    {
        usort($entries, static fn (CanonicalTargetInventoryEntry $a, CanonicalTargetInventoryEntry $b): int => strcmp($a->root() . "\0" . $a->ownership() . "\0" . $a->path(), $b->root() . "\0" . $b->ownership() . "\0" . $b->path()));
        $bytes = "COPOT-TARGET-INVENTORY\0" . chr(1) . chr(1) . pack('N', count($entries));
        foreach ($entries as $entry) {
            foreach ([$entry->root(), $entry->ownership(), $entry->path()] as $value) { $raw = $value; $bytes .= pack('N', strlen($raw)) . $raw; }
            $bytes .= self::u64($entry->byteSize()) . hex2bin($entry->sha256());
        }
        return $bytes;
    }

    private static function u64(int $value): string
    {
        if ($value < 0 || $value > PHP_INT_MAX) { throw new \InvalidArgumentException('Integer is outside serialization bounds.'); }
        $high = intdiv($value, 4294967296); $low = $value % 4294967296;
        return pack('N2', $high, $low);
    }
}
