<?php

namespace Copot\Core;

/** Non-mutating Installer decision derived from an Adoption orchestration result. */
final class InstallerAdoptionDecision
{
    public const TERMINAL_ADOPT = 'terminal_adopt';
    public const CONTINUE_RESOLUTION = 'continue_resolution';
    public const SUSPENDED = 'suspended';
    public const BLOCKED = 'blocked';
    public const STALE = 'stale';

    public function __construct(
        private string $state,
        private string $nextAction,
        private string $namespace,
        private ?string $installationIdentity,
        private bool $preserveExistingState,
        private bool $administratorInputAllowed,
        private string $detail
    ) {
        if (!in_array($state, [self::TERMINAL_ADOPT, self::CONTINUE_RESOLUTION, self::SUSPENDED, self::BLOCKED, self::STALE], true)) {
            throw new \InvalidArgumentException('Installer Adoption decision state is unsupported.');
        }
        foreach ([$nextAction, $namespace, $detail] as $value) {
            if (trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new \InvalidArgumentException('Installer Adoption decision text is invalid.');
            }
        }
        if ($installationIdentity !== null && (trim($installationIdentity) !== $installationIdentity || preg_match('/[\x00-\x1F\x7F]/', $installationIdentity) === 1)) {
            throw new \InvalidArgumentException('Installer Adoption installation identity is invalid.');
        }
    }

    public function state(): string { return $this->state; }
    public function nextAction(): string { return $this->nextAction; }
    public function namespace(): string { return $this->namespace; }
    public function installationIdentity(): ?string { return $this->installationIdentity; }
    public function preservesExistingState(): bool { return $this->preserveExistingState; }
    public function administratorInputAllowed(): bool { return $this->administratorInputAllowed; }
    public function detail(): string { return $this->detail; }
    public function terminal(): bool { return $this->state === self::TERMINAL_ADOPT; }
}
