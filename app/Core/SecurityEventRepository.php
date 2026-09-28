<?php

namespace Copot\Core;

use DateTimeImmutable;
use PDO;

final class SecurityEventRepository
{
    public function __construct(private Database $database)
    {
    }

    public function append(array $event): bool
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO ' . $this->database->table('security_events') . ' (
                occurred_at, category, severity, actor_user_id, target_type, target_identity,
                action, result, context_json, retention_until
            ) VALUES (
                :occurred_at, :category, :severity, :actor_user_id, :target_type, :target_identity,
                :action, :result, :context_json, :retention_until
            )'
        );

        $statement->execute($event);

        return true;
    }

    /** @return list<array<string,mixed>> */
    public function list(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $statement = $this->database->connection()->query(
            'SELECT * FROM ' . $this->database->table('security_events') . ' ORDER BY occurred_at DESC, id DESC LIMIT ' . $limit
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function prune(DateTimeImmutable $before): int
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM ' . $this->database->table('security_events') . ' WHERE occurred_at < :before'
        );
        $statement->execute(['before' => $before->format('Y-m-d H:i:s')]);

        return $statement->rowCount();
    }
}
