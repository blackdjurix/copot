<?php

namespace Copot\Core;

class InstallationLock
{
    private $handle;
    private bool $released = false;

    public function __construct($handle)
    {
        if (!is_resource($handle)) {
            throw new InstallationException('Installer lock handle is invalid.');
        }

        $this->handle = $handle;
    }

    public function release(): void
    {
        if ($this->released) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->released = true;
    }

    public function __destruct()
    {
        $this->release();
    }
}
