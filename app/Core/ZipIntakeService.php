<?php

namespace Copot\Core;

final class ZipIntakeService
{
    public function __construct(
        private string $liveRoot,
        private ?string $configuredStagingRoot = null,
        private ?ArchiveLimits $limits = null,
        private ?ZipArchiveInspector $inspector = null,
        private ?StagedArchiveExtractor $extractor = null
    ) {
        $this->limits ??= new ArchiveLimits();
        $this->inspector ??= new ZipArchiveInspector();
        $this->extractor ??= new StagedArchiveExtractor();
    }

    public function intake(string $sourcePath): StagedPayload
    {
        if (!class_exists(\ZipArchive::class) || !extension_loaded('zip')) {
            throw new \RuntimeException('WU2 requires the PHP ext-zip extension and ZipArchive.');
        }

        if ($sourcePath === '' || str_contains($sourcePath, "\0") || !is_file($sourcePath) || is_link($sourcePath) || !is_readable($sourcePath)) {
            throw new \InvalidArgumentException('Local package ZIP path is invalid.');
        }

        $limits = $this->limits;
        $session = StagingSession::create($this->liveRoot, $this->configuredStagingRoot);

        try {
            $archiveSha256 = $this->copyArchive($sourcePath, $session, $limits);
            $archive = new \ZipArchive();
            $opened = $archive->open($session->archivePath(), \ZipArchive::RDONLY);

            if ($opened !== true) {
                throw new \RuntimeException('Staged package ZIP could not be opened.');
            }

            try {
                $entries = $this->inspector->inspect($archive, $limits);

                return $this->extractor->extract($archive, $entries, $session, $archiveSha256, $limits);
            } finally {
                $archive->close();
            }
        } catch (\Throwable $exception) {
            try {
                $session->cleanup();
            } catch (\Throwable) {
                // Preserve the original intake failure; cleanup is retried by reconciliation.
            }

            throw $exception;
        }
    }

    public function reconcileStale(int $maxAgeSeconds = 86400): int
    {
        $root = $this->configuredStagingRoot
            ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-package-staging';

        return StagingSession::reconcileStale($root, $maxAgeSeconds);
    }

    private function copyArchive(string $sourcePath, StagingSession $session, ArchiveLimits $limits): string
    {
        $input = @fopen($sourcePath, 'rb');
        $temporary = @fopen($session->archiveTemporaryPath(), 'xb');

        if (!is_resource($input) || !is_resource($temporary)) {
            if (is_resource($input)) { fclose($input); }
            if (is_resource($temporary)) { fclose($temporary); }
            throw new \RuntimeException('Source package ZIP could not be copied into staging.');
        }

        $hash = hash_init('sha256');
        $copied = 0;

        try {
            while (!feof($input)) {
                $chunk = fread($input, 8192);

                if ($chunk === false) {
                    throw new \RuntimeException('Source package ZIP could not be read.');
                }

                if ($chunk === '') {
                    continue;
                }

                $copied += strlen($chunk);

                if ($copied > $limits->maxArchiveBytes()) {
                    throw new \RuntimeException('Archive bytes exceed the WU2 safety limit.');
                }

                hash_update($hash, $chunk);
                $offset = 0;

                while ($offset < strlen($chunk)) {
                    $written = fwrite($temporary, substr($chunk, $offset));

                    if (!is_int($written) || $written < 1) {
                        throw new \RuntimeException('Staged package ZIP could not be written.');
                    }

                    $offset += $written;
                }
            }

            if (!fflush($temporary) || (function_exists('fsync') && !fsync($temporary))) {
                throw new \RuntimeException('Staged package ZIP could not be finalized.');
            }
        } finally {
            fclose($input);
            fclose($temporary);
        }

        if (!@rename($session->archiveTemporaryPath(), $session->archivePath())) {
            throw new \RuntimeException('Staged package ZIP could not be activated.');
        }

        @chmod($session->archivePath(), 0600);

        if (!is_file($session->archivePath()) || filesize($session->archivePath()) !== $copied) {
            throw new \RuntimeException('Staged package ZIP identity is invalid.');
        }

        return hash_final($hash);
    }
}
