<?php

namespace Copot\Core;

final class SystemManagerPackageUpload
{
    public function __construct(private string $root)
    {
        if (is_link($root) || (!is_dir($root) && !@mkdir($root, 0700, true)) || !is_writable($root)) {
            throw new \RuntimeException('Private package staging is unavailable.');
        }
    }

    public function stage(?array $upload): string
    {
        if (!is_array($upload) || ($upload['error'] ?? null) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('A valid Webcore package upload is required.');
        }
        $source = $upload['tmp_name'] ?? null;
        if (!is_string($source) || $source === '' || !is_uploaded_file($source)) {
            throw new \InvalidArgumentException('The uploaded package source is invalid.');
        }
        $destination = $this->root . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.zip';
        if (!@copy($source, $destination)) {
            throw new \RuntimeException('The uploaded package could not be staged.');
        }
        @chmod($destination, 0600);
        return $destination;
    }

    public function sourcePath(?array $upload): string
    {
        if (!is_array($upload) || ($upload['error'] ?? null) !== UPLOAD_ERR_OK) throw new \InvalidArgumentException('A valid Webcore package upload is required.');
        $source = $upload['tmp_name'] ?? null;
        if (!is_string($source) || $source === '' || !is_uploaded_file($source)) throw new \InvalidArgumentException('The uploaded package source is invalid.');
        return $source;
    }

    public function cleanup(string $path): void
    {
        if (is_file($path) && !is_link($path)) @unlink($path);
    }
}
