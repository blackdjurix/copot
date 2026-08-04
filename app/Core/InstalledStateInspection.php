<?php

namespace Copot\Core;

final class InstalledStateInspection
{
    private function __construct(
        private string $status,
        private ?InstalledStateSnapshot $snapshot,
        private string $reason = ''
    ) {
        if (!in_array($status, [
            InstalledStateStatus::FRESH,
            InstalledStateStatus::LEGACY,
            InstalledStateStatus::COMMITTED,
            InstalledStateStatus::INCONSISTENT,
            InstalledStateStatus::INVALID,
        ], true)) {
            throw new \InvalidArgumentException('Installed-state status is invalid.');
        }

        if ($status === InstalledStateStatus::COMMITTED && $snapshot === null) {
            throw new \InvalidArgumentException('Committed state requires a snapshot.');
        }
    }

    public static function fresh(): self
    {
        return new self(InstalledStateStatus::FRESH, null);
    }

    public static function legacy(InstalledStateSnapshot $snapshot): self
    {
        return new self(InstalledStateStatus::LEGACY, $snapshot);
    }

    public static function committed(InstalledStateSnapshot $snapshot): self
    {
        return new self(InstalledStateStatus::COMMITTED, $snapshot);
    }

    public static function inconsistent(string $reason): self
    {
        return new self(InstalledStateStatus::INCONSISTENT, null, $reason);
    }

    public static function invalid(string $reason): self
    {
        return new self(InstalledStateStatus::INVALID, null, $reason);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function snapshot(): ?InstalledStateSnapshot
    {
        return $this->snapshot;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
