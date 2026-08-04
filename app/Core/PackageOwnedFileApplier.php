<?php

namespace Copot\Core;

final class PackageOwnedFileApplier
{
    public function __construct(
        private LiveTreePathGuard $guard,
        private LiveFileActivationCapability $capability,
        private string $temporaryRoot
    ) {
        $this->temporaryRoot = rtrim($temporaryRoot, '/\\');
        if ($this->temporaryRoot === '' || str_contains($this->temporaryRoot, "\0")) {
            throw new \InvalidArgumentException('Apply temporary root is invalid.');
        }
        $liveRoot = $guard->liveRoot();
        $temporaryCandidate = realpath($this->temporaryRoot);
        $temporaryParent = realpath(dirname($this->temporaryRoot));
        $temporaryCandidate ??= ($temporaryParent === false ? $this->temporaryRoot : $temporaryParent . DIRECTORY_SEPARATOR . basename($this->temporaryRoot));
        if ($temporaryParent === false || self::overlaps($temporaryCandidate, $liveRoot) || self::overlaps($liveRoot, $temporaryCandidate)) {
            throw new \InvalidArgumentException('Apply temporary root must be separate from the live Webcore root.');
        }
    }

    public function apply(WebcoreApplyPlan $plan, ?callable $progress = null): WebcoreApplyResult
    {
        $files = $plan->files();
        $hasReplacement = false;

        foreach ($files as $file) {
            if (!$file instanceof StagedFile) {
                return new WebcoreApplyResult(WebcoreApplyResult::FAILED, [], 'Apply plan contains an invalid staged file.');
            }
            $destination = $this->guard->destination($file->path());
            $exists = file_exists($destination) || is_link($destination);
            $this->guard->verifyDestination($file->path(), false);
            if ($exists) {
                $hasReplacement = true;
            }
        }

        if (!$this->capability->supportsCreation() || ($hasReplacement && !$this->capability->supportsReplacement())) {
            return new WebcoreApplyResult(WebcoreApplyResult::FAILED, [], $hasReplacement
                ? 'Safe live-file replacement is unavailable on this platform.'
                : 'Safe live-file creation is unavailable on this platform.');
        }

        if (!file_exists($this->temporaryRoot)
            && (!mkdir($this->temporaryRoot, 0700) || !is_dir($this->temporaryRoot))) {
            return new WebcoreApplyResult(WebcoreApplyResult::FAILED, [], 'Apply temporary storage could not be created.');
        }

        if (is_link($this->temporaryRoot) || !is_dir($this->temporaryRoot) || !is_writable($this->temporaryRoot)) {
            return new WebcoreApplyResult(WebcoreApplyResult::FAILED, [], 'Apply temporary storage is invalid.');
        }

        $workspace = $this->temporaryRoot . DIRECTORY_SEPARATOR . 'apply-' . bin2hex(random_bytes(16));
        if (!mkdir($workspace, 0700)) {
            return new WebcoreApplyResult(WebcoreApplyResult::FAILED, [], 'Apply workspace could not be created.');
        }

        $applied = [];

        try {
            foreach ($files as $file) {
                $destination = $this->guard->destination($file->path());
                $source = $plan->payload()->payloadPath() . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $file->path());

                if (!is_file($source) || is_link($source)) {
                    return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, $applied, 'Staged source identity is unavailable.');
                }

                $this->guard->ensureParent($file->path());
                $existing = file_exists($destination) || is_link($destination);
                $this->guard->verifyDestination($file->path(), $existing);
                $temporary = $workspace . DIRECTORY_SEPARATOR . 'file-' . bin2hex(random_bytes(16));
                $input = @fopen($source, 'rb');
                $output = @fopen($temporary, 'xb');

                if (!is_resource($input) || !is_resource($output)) {
                    if (is_resource($input)) { fclose($input); }
                    if (is_resource($output)) { fclose($output); }
                    return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, $applied, 'Apply file stream could not be opened.');
                }

                $hash = hash_init('sha256');
                $written = 0;

                try {
                    while (!feof($input)) {
                        $chunk = fread($input, 8192);
                        if ($chunk === false) {
                            throw new \RuntimeException('Staged source could not be read.');
                        }
                        if ($chunk === '') {
                            continue;
                        }
                        $written += strlen($chunk);
                        hash_update($hash, $chunk);
                        $offset = 0;
                        while ($offset < strlen($chunk)) {
                            $count = fwrite($output, substr($chunk, $offset));
                            if (!is_int($count) || $count < 1) {
                                throw new \RuntimeException('Apply file could not be written.');
                            }
                            $offset += $count;
                        }
                    }
                    if (!fflush($output) || (function_exists('fsync') && !fsync($output))) {
                        throw new \RuntimeException('Apply file could not be finalized.');
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }

                $actualHash = hash_final($hash);
                if ($written !== $file->byteSize() || $actualHash !== $file->sha256()) {
                    @unlink($temporary);
                    return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, $applied, 'Staged file identity changed during apply.');
                }

                $mode = $existing ? ((int) @fileperms($destination) & 0777) : 0644;
                @chmod($temporary, $mode > 0 ? $mode : 0644);
                $this->guard->verifyDestination($file->path(), $existing);

                if (!@rename($temporary, $destination)) {
                    @unlink($temporary);
                    return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, $applied, 'Live-file activation failed.');
                }

                $this->guard->verifyDestination($file->path(), true);
                if (filesize($destination) !== $written || hash_file('sha256', $destination) !== $actualHash) {
                    return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, $applied, 'Activated live-file identity could not be verified.');
                }

                $applied[] = $file->path();
                if ($progress !== null) {
                    $progress(count($applied), $file->path());
                }
            }
        } catch (\Throwable $exception) {
            return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, $applied, $exception->getMessage());
        } finally {
            $this->removeWorkspace($workspace);
        }

        return new WebcoreApplyResult(WebcoreApplyResult::COMPLETED, $applied);
    }

    private function removeWorkspace(string $workspace): void
    {
        if (!is_dir($workspace) || is_link($workspace)) {
            return;
        }
        foreach (scandir($workspace) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $workspace . DIRECTORY_SEPARATOR . $name;
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir($workspace);
    }

    private static function overlaps(string $path, string $root): bool
    {
        $path = strtolower(rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR));
        $root = strtolower(rtrim(str_replace('/', DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR));

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }
}
