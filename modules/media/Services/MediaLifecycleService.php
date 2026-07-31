<?php

use Copot\Core\Database;

final class MediaLifecycleService
{
    private static int $savepointCounter = 0;

    public function __construct(private Database $database, private MediaRepository $media, private MediaVariantRepository $variants, private MediaUsageRepository $usages) {}

    public function create(array $data): MediaId
    {
        Media::validateInput([
            'kind' => $data['kind'] ?? null, 'originalFilename' => $data['original_filename'] ?? null, 'title' => $data['title'] ?? null,
            'storageKey' => $data['storage_key'] ?? null, 'mimeType' => $data['mime_type'] ?? null, 'extension' => $data['extension'] ?? null,
            'byteSize' => $data['byte_size'] ?? null, 'width' => $data['width'] ?? null, 'height' => $data['height'] ?? null,
        ]);
        return $this->atomic(fn (): MediaId => $this->media->create([
            'kind' => $data['kind'], 'original_filename' => $data['original_filename'], 'title' => $data['title'],
            'storage_key' => $data['storage_key'], 'mime_type' => $data['mime_type'], 'extension' => $data['extension'],
            'byte_size' => $data['byte_size'], 'width' => $data['width'] ?? null, 'height' => $data['height'] ?? null,
        ]));
    }

    public function updateTitle(MediaId|int $id, string $title): void
    {
        if (trim($title) === '') throw new InvalidArgumentException('Media title must be non-empty.');
        $this->atomic(fn () => $this->media->updateTitle($id, trim($title)));
    }

    public function registerVariantDescriptor(array $data): int
    {
        MediaVariant::validateInput([
            'id' => 1, 'variantKey' => $data['variant_key'] ?? '', 'storageKey' => $data['storage_key'] ?? '',
            'mimeType' => $data['mime_type'] ?? '', 'extension' => $data['extension'] ?? '', 'byteSize' => $data['byte_size'] ?? 0,
            'width' => $data['width'] ?? null, 'height' => $data['height'] ?? null,
        ]);
        return $this->atomic(fn (): int => $this->variants->saveDescriptor($data));
    }

    public function registerUsage(MediaId|int $mediaId, string $consumerType, int $consumerId, string $usageKey): void
    {
        $this->atomic(function () use ($mediaId, $consumerType, $consumerId, $usageKey): void {
            if (!$this->media->findById($mediaId, true)) throw new MediaNotFoundException('Media was not found.');
            new MediaUsage($mediaId instanceof MediaId ? $mediaId : new MediaId($mediaId), $consumerType, $consumerId, $usageKey, '');
            $this->usages->register($mediaId, $consumerType, $consumerId, $usageKey);
        });
    }

    public function removeUsage(MediaId|int $mediaId, string $consumerType, int $consumerId, string $usageKey): void
    {
        $this->atomic(fn () => $this->usages->remove($mediaId, $consumerType, $consumerId, $usageKey));
    }

    public function delete(MediaId|int $id): void
    {
        $this->atomic(function () use ($id): void {
            $media = $this->media->findById($id, true);
            if (!$media) throw new MediaNotFoundException('Media was not found.');
            if ($this->usages->forMedia($media->id(), true) !== []) throw new MediaInUseException('Referenced Media cannot be deleted.');
            $this->media->delete($media->id());
        });
    }

    private function atomic(callable $operation): mixed
    {
        $connection = $this->database->connection();
        $owns = !$connection->inTransaction();
        $savepoint = null;
        if ($owns) $connection->beginTransaction();
        else { self::$savepointCounter++; $savepoint = 'media_lifecycle_' . self::$savepointCounter . '_' . bin2hex(random_bytes(6)); $connection->exec('SAVEPOINT ' . $savepoint); }
        try {
            $result = $operation();
            if ($owns) $connection->commit(); else $connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            return $result;
        } catch (Throwable $exception) {
            if ($owns) { if ($connection->inTransaction()) $connection->rollBack(); }
            elseif ($connection->inTransaction()) { $connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint); $connection->exec('RELEASE SAVEPOINT ' . $savepoint); }
            throw $exception;
        }
    }
}

class MediaInUseException extends RuntimeException {}
