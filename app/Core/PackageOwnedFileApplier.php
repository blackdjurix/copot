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

    public function apply(WebcoreApplyPlan $plan, ?callable $progress = null, ?callable $beforeActivation = null): WebcoreApplyResult
    {
        $files = $plan->files();
        $hasReplacement = false;

        $payloadRoot = realpath($plan->payload()->payloadPath());

        if ($payloadRoot === false || is_link($plan->payload()->payloadPath()) || !is_dir($payloadRoot)) {
            return new WebcoreApplyResult(WebcoreApplyResult::FAILED, [], 'Staged payload root is unavailable.');
        }

        foreach ($files as $file) {
            if (!$file instanceof StagedFile || !$this->verifyStagedSource($payloadRoot, $file)) {
                return new WebcoreApplyResult(WebcoreApplyResult::FAILED, [], 'Staged payload preflight failed.');
            }
        }

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
                $source = $this->stagedSourcePath($payloadRoot, $file->path());

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

                $activationFailure = $this->activate(
                    $temporary,
                    $destination,
                    $file->path(),
                    $existing,
                    $written,
                    $actualHash,
                    $workspace,
                    $beforeActivation
                );
                if ($activationFailure !== null) {
                    return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, $applied, $activationFailure);
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

    private function activate(
        string $temporary,
        string $destination,
        string $relativePath,
        bool $existing,
        int $written,
        string $actualHash,
        string $workspace,
        ?callable $beforeActivation
    ): ?string {
        if (DIRECTORY_SEPARATOR !== '\\') {
            try {
                if ($beforeActivation !== null) {
                    $beforeActivation($relativePath);
                }
                if (!@rename($temporary, $destination)) {
                    throw new \RuntimeException('Live-file activation failed.');
                }
                $this->guard->verifyDestination($relativePath, true);
                if (filesize($destination) !== $written || hash_file('sha256', $destination) !== $actualHash) {
                    throw new \RuntimeException('Activated live-file identity could not be verified.');
                }
            } catch (\Throwable $exception) {
                @unlink($temporary);
                return $exception->getMessage();
            }

            return null;
        }

        $existingSize = $existing ? @filesize($destination) : null;
        $existingHash = $existing ? @hash_file('sha256', $destination) : null;
        if ($existing && (!is_int($existingSize) || !is_string($existingHash))) {
            @unlink($temporary);
            return 'Existing live-file identity could not be verified.';
        }

        $backup = $workspace . DIRECTORY_SEPARATOR . 'backup-' . bin2hex(random_bytes(16));
        if ($existing && !@rename($destination, $backup)) {
            @unlink($temporary);
            return 'Existing live-file could not be preserved for replacement.';
        }

        $activationTemporary = null;
        try {
            if ($beforeActivation !== null) {
                $beforeActivation($relativePath);
            }

            // Windows renames preserve the source security descriptor. Stage
            // the activation sibling inside the guarded destination directory
            // so it inherits that directory's normal access semantics instead
            // of inheriting the apply-workspace ACL.
            $activationTemporary = $this->stageWindowsDestination($temporary, $destination, $written, $actualHash);
            if (!@rename($activationTemporary, $destination)) {
                throw new \RuntimeException('Live-file activation failed.');
            }
            $activationTemporary = null;
            $this->guard->verifyDestination($relativePath, true);
            if (filesize($destination) !== $written || hash_file('sha256', $destination) !== $actualHash) {
                throw new \RuntimeException('Activated live-file identity could not be verified.');
            }
            if ($existing && !@unlink($backup)) {
                throw new \RuntimeException('Replacement backup could not be removed.');
            }
        } catch (\Throwable $exception) {
            if ($activationTemporary !== null) {
                @unlink($activationTemporary);
            }
            @unlink($destination);
            $restored = !$existing || (
                @rename($backup, $destination)
                && @filesize($destination) === $existingSize
                && @hash_file('sha256', $destination) === $existingHash
            );
            @unlink($temporary);

            return $restored
                ? $exception->getMessage()
                : $exception->getMessage() . ' Original live file could not be restored.';
        }

        return null;
    }

    private function stageWindowsDestination(string $source, string $destination, int $expectedSize, string $expectedHash): string
    {
        $activationTemporary = dirname($destination) . DIRECTORY_SEPARATOR . '.copot-activation-' . bin2hex(random_bytes(16)) . '.tmp';

        $input = @fopen($source, 'rb');
        $output = @fopen($activationTemporary, 'xb');

        if (!is_resource($input) || !is_resource($output)) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            @unlink($activationTemporary);
            throw new \RuntimeException('Windows destination activation staging failed.');
        }

        $hash = hash_init('sha256');
        $written = 0;

        try {
            while (!feof($input)) {
                $chunk = fread($input, 8192);
                if ($chunk === false) {
                    throw new \RuntimeException('Windows destination activation source could not be read.');
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
                        throw new \RuntimeException('Windows destination activation staging could not be written.');
                    }
                    $offset += $count;
                }
            }

            if (!fflush($output) || (function_exists('fsync') && !fsync($output))) {
                throw new \RuntimeException('Windows destination activation staging could not be finalized.');
            }
        } catch (\Throwable $exception) {
            fclose($input);
            fclose($output);
            @unlink($activationTemporary);
            throw $exception;
        }

        fclose($input);
        fclose($output);

        if ($written !== $expectedSize || hash_final($hash) !== $expectedHash
            || @filesize($activationTemporary) !== $expectedSize
            || @hash_file('sha256', $activationTemporary) !== $expectedHash) {
            @unlink($activationTemporary);
            throw new \RuntimeException('Windows destination activation staging identity could not be verified.');
        }

        return $activationTemporary;
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

    private function verifyStagedSource(string $payloadRoot, StagedFile $file): bool
    {
        $source = $this->stagedSourcePath($payloadRoot, $file->path());

        if (!is_file($source) || is_link($source)) {
            return false;
        }

        $resolved = realpath($source);

        if ($resolved === false || is_link($source) || !$this->inside($resolved, $payloadRoot)) {
            return false;
        }

        $size = @filesize($resolved);
        $hash = @hash_file('sha256', $resolved);

        return is_int($size) && $size === $file->byteSize() && $hash === $file->sha256();
    }

    private function stagedSourcePath(string $payloadRoot, string $relativePath): string
    {
        $candidate = $payloadRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!$this->inside($candidate, $payloadRoot)) {
            throw new \RuntimeException('Staged source escaped the payload root.');
        }

        return $candidate;
    }

    private function inside(string $path, string $root): bool
    {
        $path = strtolower(rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR));
        $root = strtolower(rtrim(str_replace('/', DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR));

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private static function overlaps(string $path, string $root): bool
    {
        $path = strtolower(rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR));
        $root = strtolower(rtrim(str_replace('/', DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR));

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }
}
