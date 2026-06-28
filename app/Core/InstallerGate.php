<?php

namespace Copot\Core;

class InstallerGate
{
    public const NORMAL_APPLICATION = 'normal_application';
    public const INSTALLER = 'installer';
    public const REDIRECT_TO_INSTALLER = 'redirect_to_installer';
    public const BLOCK_INSTALLER = 'block_installer';
    public const INSTALLATION_STATE_ERROR = 'installation_state_error';

    public function __construct(private InstallationState $state)
    {
    }

    public function decide(Request $request): string
    {
        try {
            $installed = $this->state->isInstalled();
        } catch (InstallationException) {
            return $request->path() === '/install'
                ? self::INSTALLATION_STATE_ERROR
                : self::REDIRECT_TO_INSTALLER;
        }

        if ($installed) {
            return $request->path() === '/install'
                ? self::BLOCK_INSTALLER
                : self::NORMAL_APPLICATION;
        }

        return $request->path() === '/install'
            ? self::INSTALLER
            : self::REDIRECT_TO_INSTALLER;
    }
}
