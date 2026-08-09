<?php

namespace Copot\Core;

final class InstallationIdentityStore
{
    private string $path;

    public function __construct(string $storagePath)
    {
        if (is_link($storagePath) || !is_dir($storagePath) || !is_writable($storagePath)) {
            throw new \RuntimeException('Installation identity storage is unavailable.');
        }

        $root = rtrim(realpath($storagePath) ?: '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.copot-lifecycle';
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new \RuntimeException('Installation identity storage could not be created.');
        }
        if (is_link($root) || !is_dir($root) || !is_writable($root)) {
            throw new \RuntimeException('Installation identity storage is invalid.');
        }
        $this->path = $root . DIRECTORY_SEPARATOR . 'installation-identity.json';
    }

    public function read(): ?InstallationIdentity
    {
        if (!file_exists($this->path)) {
            return null;
        }
        if (is_link($this->path) || !is_file($this->path) || !is_readable($this->path)) {
            throw new \RuntimeException('Installation identity record is invalid.');
        }
        $data = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($data) || !is_string($data['installation_id'] ?? null)) {
            throw new \RuntimeException('Installation identity record is invalid.');
        }

        return new InstallationIdentity($data['installation_id']);
    }

    public function getOrCreate(): InstallationIdentity
    {
        $existing = $this->read();
        if ($existing instanceof InstallationIdentity) {
            return $existing;
        }

        $identity = InstallationIdentity::generate();
        $temporary = $this->path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        file_put_contents($temporary, json_encode(['installation_id' => $identity->value()], JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
        @chmod($temporary, 0600);
        if (file_exists($this->path) || !@rename($temporary, $this->path)) {
            @unlink($temporary);
            return $this->read() ?? throw new \RuntimeException('Installation identity could not be established.');
        }

        return $this->read() ?? throw new \RuntimeException('Installation identity could not be verified.');
    }
}
