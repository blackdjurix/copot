<?php

use Copot\Core\Database;

final class FormRateLimitException extends RuntimeException {}

final class FormSubmissionAttemptRepository
{
    public const WINDOW_SECONDS = 900;
    public const RETENTION_SECONDS = 86400;
    public const MAX_ATTEMPTS = 5;

    public function __construct(private Database $database) {}

    public function guardAndRecord(FormId|int $formId, string $clientAddress): void
    {
        $id = $formId instanceof FormId ? $formId->value() : (new FormId($formId))->value();
        $connection = $this->database->connection();
        $connection->beginTransaction();
        try {
            $form = $this->database->prepareModule('SELECT id FROM forms WHERE id=:id FOR UPDATE');
            $form->execute(['id' => $id]);
            if ($form->fetchColumn() === false) throw new FormNotFoundException('Form is unavailable.');
            $this->database->execModule('DELETE FROM form_submission_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 500');
            $count = $this->database->prepareModule('SELECT COUNT(*) FROM form_submission_attempts WHERE form_id=:form_id AND client_address=:client_address AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
            $count->bindValue(':form_id', $id, PDO::PARAM_INT);
            $count->bindValue(':client_address', $clientAddress, PDO::PARAM_STR);
            $count->execute();
            if ((int) $count->fetchColumn() >= self::MAX_ATTEMPTS) throw new FormRateLimitException('Submission rate limit reached.');
            $insert = $this->database->prepareModule('INSERT INTO form_submission_attempts (form_id,client_address,attempted_at) VALUES (:form_id,:client_address,NOW())');
            $insert->bindValue(':form_id', $id, PDO::PARAM_INT);
            $insert->bindValue(':client_address', $clientAddress, PDO::PARAM_STR);
            $insert->execute();
            $connection->commit();
        } catch (Throwable $failure) {
            if ($connection->inTransaction()) $connection->rollBack();
            throw $failure;
        }
    }
}
