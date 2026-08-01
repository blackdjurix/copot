<?php

use Copot\Core\Database;

final class MediaUsageRepository
{
    public function __construct(private Database $database) {}

    public function forMedia(MediaId|int $mediaId, bool $forUpdate = false): array
    {
        $mediaId = $mediaId instanceof MediaId ? $mediaId : new MediaId($mediaId);
        $statement = $this->database->connection()->prepare('SELECT * FROM media_usages WHERE media_id = :media_id ORDER BY consumer_type ASC, consumer_id ASC, usage_key ASC' . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['media_id' => $mediaId->value()]);
        return array_map(fn (array $row): MediaUsage => new MediaUsage(new MediaId((int) $row['media_id']), (string) $row['consumer_type'], (int) $row['consumer_id'], (string) $row['usage_key'], (string) $row['created_at']), $statement->fetchAll());
    }

    public function register(MediaId|int $mediaId, string $consumerType, int $consumerId, string $usageKey): void
    {
        $mediaId = $mediaId instanceof MediaId ? $mediaId : new MediaId($mediaId);
        $statement = $this->database->connection()->prepare('INSERT IGNORE INTO media_usages (media_id, consumer_type, consumer_id, usage_key, created_at) VALUES (:media_id, :consumer_type, :consumer_id, :usage_key, NOW())');
        $statement->execute(['media_id' => $mediaId->value(), 'consumer_type' => trim($consumerType), 'consumer_id' => $consumerId, 'usage_key' => trim($usageKey)]);
    }

    public function remove(MediaId|int $mediaId, string $consumerType, int $consumerId, string $usageKey): void
    {
        $mediaId = $mediaId instanceof MediaId ? $mediaId : new MediaId($mediaId);
        $statement = $this->database->connection()->prepare('DELETE FROM media_usages WHERE media_id = :media_id AND consumer_type = :consumer_type AND consumer_id = :consumer_id AND usage_key = :usage_key');
        $statement->execute(['media_id' => $mediaId->value(), 'consumer_type' => trim($consumerType), 'consumer_id' => $consumerId, 'usage_key' => trim($usageKey)]);
    }

    public function forConsumer(string $consumerType, int $consumerId, string $usageKey = ''): array
    {
        $sql = 'SELECT * FROM media_usages WHERE consumer_type = :consumer_type AND consumer_id = :consumer_id';
        $params = ['consumer_type' => trim($consumerType), 'consumer_id' => $consumerId];
        if ($usageKey !== '') { $sql .= ' AND usage_key = :usage_key'; $params['usage_key'] = trim($usageKey); }
        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);
        return array_map(fn (array $row): MediaUsage => new MediaUsage(new MediaId((int) $row['media_id']), (string) $row['consumer_type'], (int) $row['consumer_id'], (string) $row['usage_key'], (string) $row['created_at']), $statement->fetchAll());
    }
}
