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

    public function saveOrReplaceDescriptor(array $data): ?MediaVariant
    {
        $mediaId = $data['media_id'] instanceof MediaId ? $data['media_id'] : new MediaId((int) $data['media_id']);
        MediaVariant::validateInput(['id' => 1, 'variantKey' => $data['variant_key'] ?? '', 'storageKey' => $data['storage_key'] ?? '', 'mimeType' => $data['mime_type'] ?? '', 'extension' => $data['extension'] ?? '', 'byteSize' => $data['byte_size'] ?? 0, 'width' => $data['width'] ?? null, 'height' => $data['height'] ?? null]);
        $previous = $this->find($mediaId, (string) $data['variant_key']);
        if (!$previous && count($this->forMedia($mediaId)) >= 24) throw new MediaProcessingValidationException('Media variant limit reached.');
        if ($previous) {
            $statement = $this->database->connection()->prepare('UPDATE media_variants SET storage_key = :storage_key, mime_type = :mime_type, extension = :extension, byte_size = :byte_size, width = :width, height = :height, updated_at = NOW() WHERE media_id = :media_id AND variant_key = :variant_key');
            $statement->execute(['storage_key'=>$data['storage_key'],'mime_type'=>$data['mime_type'],'extension'=>$data['extension'],'byte_size'=>$data['byte_size'],'width'=>$data['width']??null,'height'=>$data['height']??null,'media_id'=>$mediaId->value(),'variant_key'=>$data['variant_key']]);
            return $previous;
        }
        $this->saveDescriptor([...$data, 'media_id' => $mediaId->value()]); return null;
    }

    public function deleteDescriptor(MediaId|int $mediaId, string $variantKey): ?MediaVariant
    {
        $mediaId = $mediaId instanceof MediaId ? $mediaId : new MediaId($mediaId); $previous = $this->find($mediaId, $variantKey); if (!$previous) return null;
        $statement = $this->database->connection()->prepare('DELETE FROM media_variants WHERE media_id = :media_id AND variant_key = :variant_key'); $statement->execute(['media_id'=>$mediaId->value(),'variant_key'=>$variantKey]); return $previous;
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
