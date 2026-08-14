<?php

namespace Copot\Core;

use PDO;

final class CoreMigrationRunner
{
    public function __construct(private CoreMigrationLedger $ledger)
    {
    }

    public function run(PDO $connection, CoreMigrationPlan $plan, ?callable $beforeMigration = null, ?callable $authorization = null): MigrationRunResult
    {
        if (!$plan->isAccepted()) {
            return new MigrationRunResult(MigrationRunResult::FAILED, [], $plan->reason());
        }

        if ($plan->migrations() === []) {
            return new MigrationRunResult(MigrationRunResult::NOOP);
        }

        $applied = [];

        foreach ($plan->migrations() as $migration) {
            if (!$migration instanceof CoreMigrationDescriptor) {
                return new MigrationRunResult(MigrationRunResult::FAILED, $applied, 'Migration plan contains an invalid descriptor.');
            }

            try {
                if ($authorization === null) return new MigrationRunResult(MigrationRunResult::FAILED, $applied, 'Migration authorization is required.');
                $authorized = $authorization($migration);
                if (!$authorized instanceof AuthorizedMigrationContext) return new MigrationRunResult(MigrationRunResult::FAILED, $applied, 'Migration authorization is invalid.');
                if ($beforeMigration !== null) {
                    $beforeMigration($migration);
                }
                if (!$migration->checkPreconditionAuthorized($authorized)) {
                    return new MigrationRunResult(MigrationRunResult::FAILED, $applied, 'Migration precondition failed.');
                }

                if ($migration->transactionMode() === CoreMigrationDescriptor::TRANSACTIONAL) {
                    if ($connection->inTransaction()) {
                        return new MigrationRunResult(MigrationRunResult::FAILED, $applied, 'Transactional migration runner cannot join an external transaction.');
                    }

                    $connection->beginTransaction();

                    try {
                        $migration->executeAuthorized($authorized);

                        if (!$migration->checkPostconditionAuthorized($authorized)) {
                            throw new \RuntimeException('Migration postcondition failed.');
                        }

                        $this->ledger->record($connection, $migration);
                        $connection->commit();
                    } catch (\Throwable $exception) {
                        if ($connection->inTransaction()) {
                            $connection->rollBack();
                        }

                        return new MigrationRunResult(MigrationRunResult::FAILED, $applied, $exception->getMessage());
                    }
                } else {
                    $migration->executeAuthorized($authorized);

                    if (!$migration->checkPostconditionAuthorized($authorized)) {
                        return new MigrationRunResult(MigrationRunResult::FAILED, $applied, 'Migration postcondition failed.');
                    }

                    try {
                        $this->ledger->record($connection, $migration);
                    } catch (\Throwable $exception) {
                        return new MigrationRunResult(MigrationRunResult::INDETERMINATE, $applied, $exception->getMessage());
                    }
                }

                $applied[] = $migration->id();
            } catch (\Throwable $exception) {
                return new MigrationRunResult(MigrationRunResult::FAILED, $applied, $exception->getMessage());
            }
        }

        return new MigrationRunResult(MigrationRunResult::COMPLETED, $applied);
    }
}
