<?php

namespace Copot\Core;

final class StagedArchiveExtractor
{
    public function extract(\ZipArchive $archive, array $entries, StagingSession $session, string $archiveSha256, ArchiveLimits $limits): StagedPayload
    {
        if (!file_exists($session->payloadPath()) && !mkdir($session->payloadPath(), 0700) && !is_dir($session->payloadPath())) {
            throw new \RuntimeException('Payload staging root could not be created.');
        }

        if (!is_dir($session->payloadPath()) || is_link($session->payloadPath())) {
            throw new \RuntimeException('Payload staging root is invalid.');
        }

        $payloadRoot = realpath($session->payloadPath());

        if ($payloadRoot === false || is_link($payloadRoot)) {
            throw new \RuntimeException('Payload staging root is invalid.');
        }

        $files = [];
        $totalBytes = 0;

        usort($entries, static fn (array $left, array $right): int => [count(ArchiveEntryPath::segments($left['path'])), $left['path']] <=> [count(ArchiveEntryPath::segments($right['path'])), $right['path']]);

        foreach ($entries as $entry) {
            $destination = $this->containedPath($payloadRoot, $entry['path']);

            if ($entry['directory']) {
                $this->ensureDirectory($payloadRoot, $entry['path']);
                continue;
            }

            $segments = ArchiveEntryPath::segments($entry['path']);
            array_pop($segments);
            $this->ensureDirectory($payloadRoot, implode('/', $segments));

            if (file_exists($destination) || is_link($destination)) {
                throw new \RuntimeException('Archive extraction would overwrite an existing path.');
            }

            // WU2 uses entry streams only. getStream() is the portable
            // ZipArchive API across the supported PHP ext-zip runtimes.
            $input = $archive->getStream($entry['raw_name']);
            $output = @fopen($destination, 'xb');

            if (!is_resource($input) || !is_resource($output)) {
                if (is_resource($input)) { fclose($input); }
                if (is_resource($output)) { fclose($output); }
                throw new \RuntimeException('Archive entry stream could not be opened.');
            }

            $hash = hash_init('sha256');
            $written = 0;

            try {
                while (!feof($input)) {
                    $chunk = fread($input, 8192);

                    if ($chunk === false) {
                        throw new \RuntimeException('Archive entry could not be read.');
                    }

                    if ($chunk === '') {
                        continue;
                    }

                    $length = strlen($chunk);
                    $written += $length;
                    $totalBytes += $length;

                    if ($written > $entry['size'] || $written > $limits->maxFileBytes() || $totalBytes > $limits->maxTotalExtractedBytes()) {
                        throw new \RuntimeException('Streamed archive bytes exceed a WU2 safety limit.');
                    }

                    hash_update($hash, $chunk);
                    $offset = 0;

                    while ($offset < $length) {
                        $count = fwrite($output, substr($chunk, $offset));

                        if (!is_int($count) || $count < 1) {
                            throw new \RuntimeException('Staged archive file could not be written.');
                        }

                        $offset += $count;
                    }
                }

                if (!fflush($output) || $written !== $entry['size']) {
                    throw new \RuntimeException('Staged archive file size is invalid.');
                }
            } finally {
                fclose($input);
                fclose($output);
            }

            if (!is_file($destination) || is_link($destination) || filesize($destination) !== $written) {
                throw new \RuntimeException('Staged archive file state is invalid.');
            }

            @chmod($destination, 0600);
            $files[] = new StagedFile($entry['path'], $written, hash_final($hash));
        }

        return new StagedPayload($session, $archiveSha256, $files);
    }

    private function ensureDirectory(string $root, string $relativeDirectory): void
    {
        $current = $root;
        $relative = '';

        foreach ($relativeDirectory === '' ? [] : ArchiveEntryPath::segments($relativeDirectory) as $segment) {
            $relative = $relative === '' ? $segment : $relative . '/' . $segment;
            $current = $this->containedPath($root, $relative);

            if (is_link($current) || (file_exists($current) && !is_dir($current))) {
                throw new \RuntimeException('Archive parent path is not a real directory.');
            }

            if (!file_exists($current) && !mkdir($current, 0700) && !is_dir($current)) {
                throw new \RuntimeException('Archive parent directory could not be created.');
            }

            if (is_link($current) || !is_dir($current)) {
                throw new \RuntimeException('Archive parent path is not a real directory.');
            }

            $resolved = realpath($current);

            if ($resolved === false || is_link($current) || !$this->inside($resolved, $root)) {
                throw new \RuntimeException('Archive parent directory escaped staging.');
            }
        }
    }

    private function containedPath(string $root, string $relative): string
    {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (!$this->insideLexical($path, $root)) {
            throw new \RuntimeException('Archive destination escaped staging.');
        }

        return $path;
    }

    private function inside(string $path, string $root): bool
    {
        return $this->insideLexical($path, $root);
    }

    private function insideLexical(string $path, string $root): bool
    {
        $path = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $root = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);

        return strtolower($path) === strtolower($root) || str_starts_with(strtolower($path), strtolower($root) . DIRECTORY_SEPARATOR);
    }
}
