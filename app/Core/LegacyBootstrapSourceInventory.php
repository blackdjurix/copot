<?php

namespace Copot\Core;

final class LegacyBootstrapSourceInventory
{
    /** @var list<LegacyBootstrapSourceEntry> */
    private array $entries;

    public function __construct(array $entries)
    {
        $seen = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof LegacyBootstrapSourceEntry) throw new \InvalidArgumentException('Legacy bootstrap source entries are invalid.');
            $key = strtolower($entry->root() . "\0" . $entry->path());
            if (isset($seen[$key])) throw new \InvalidArgumentException('Legacy bootstrap source inventory contains a collision.');
            $seen[$key] = true;
        }
        usort($entries, static fn (LegacyBootstrapSourceEntry $a, LegacyBootstrapSourceEntry $b): int => strcmp($a->root()."\0".$a->path(), $b->root()."\0".$b->path()));
        $this->entries = array_values($entries);
    }

    public function entries(): array { return $this->entries; }
    public function fingerprint(): string
    {
        $bytes = "COPOT-LEGACY-BOOTSTRAP-SOURCE\0" . chr(1) . pack('N', count($this->entries));
        foreach ($this->entries as $entry) {
            foreach ([$entry->root(), $entry->path(), $entry->provenance(), $entry->ownershipEvidence()] as $value) $bytes .= pack('N', strlen($value)) . $value;
            $bytes .= pack('N2', intdiv($entry->byteSize(), 4294967296), $entry->byteSize() % 4294967296) . hex2bin($entry->sha256());
        }
        return hash('sha256', $bytes);
    }
}
