<?php

namespace Copot\Core;

final class DatabaseTableOwner
{
    public const WEBCORE = 'webcore';
    public const MODULE = 'module';

    private function __construct(private string $type, private ?ModuleIdentity $module = null)
    {
        if ($type === self::WEBCORE && $module !== null) {
            throw new \InvalidArgumentException('Webcore ownership cannot carry a Module identity.');
        }
        if ($type === self::MODULE && !$module instanceof ModuleIdentity) {
            throw new \InvalidArgumentException('Module ownership requires exactly one Module identity.');
        }
        if (!in_array($type, [self::WEBCORE, self::MODULE], true)) {
            throw new \InvalidArgumentException('Database table owner type is invalid.');
        }
    }

    public static function webcore(): self { return new self(self::WEBCORE); }
    public static function module(ModuleIdentity|string $module): self
    {
        return new self(self::MODULE, $module instanceof ModuleIdentity ? $module : new ModuleIdentity($module));
    }
    public function type(): string { return $this->type; }
    public function isWebcore(): bool { return $this->type === self::WEBCORE; }
    public function isModule(): bool { return $this->type === self::MODULE; }
    public function moduleIdentity(): ?ModuleIdentity { return $this->module; }
    public function key(): string { return $this->isWebcore() ? self::WEBCORE : self::MODULE . ':' . $this->module?->value(); }
}
