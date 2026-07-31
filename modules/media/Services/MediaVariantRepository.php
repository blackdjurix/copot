<?php

use Copot\Core\Database;

final class MediaVariantRepository
{
    public function __construct(private Database $database) {}

    public function find(MediaId|int $mediaId, string $variantKey): ?MediaVariant
    {
        $mediaId = $mediaId instanceof MediaId ? $mediaId : new MediaId($mediaId);
        $statement = $this->database->connection()->prepare('SELECT * FROM media_variants WHERE media_id = :media_id AND variant_key = :variant_key LIMIT 1');
        $statement->execute(['media_id' => $mediaId->value(), 'variant_key' => trim($variantKey)]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function forMedia(MediaId|int $mediaId): array
    {
        $mediaId = $mediaId instanceof MediaId ? $mediaId : new MediaId($mediaId);
        $statement = $this->database->connection()->prepare('SELECT * FROM media_variants WHERE media_id = :media_id ORDER BY variant_key ASC, id ASC');
        $statement->execute(['media_id' => $mediaId->value()]);
        return array_map(fn (array $row): MediaVariant => $this->hydrate($row), $statement->fetchAll());
    }

    public function saveDescriptor(array $data): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO media_variants (media_id, variant_key, storage_key, mime_type, extension, byte_size, width, height, created_at, updated_at)
             VALUES (:media_id, :variant_key, :storage_key, :mime_type, :extension, :byte_size, :width, :height, NOW(), NOW())'
        );
        $statement->execute($data);
        return (int) $this->database->connection()->lastInsertId();
    }

    private function hydrate(array $row): MediaVariant
    {
        return new MediaVariant((int) $row['id'], new MediaId((int) $row['media_id']), (string) $row['variant_key'], (string) $row['storage_key'], (string) $row['mime_type'], (string) $row['extension'], (int) $row['byte_size'], $row['width'] === null ? null : (int) $row['width'], $row['height'] === null ? null : (int) $row['height'], (string) $row['created_at'], (string) $row['updated_at']);
    }
}
