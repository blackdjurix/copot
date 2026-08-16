<?php

namespace Copot\Core;

final class DatabaseLifecycleClassifier
{
    public function classify(TransitionPlan $transition, CoreMigrationPlan $migration): TransitionPlan
    {
        if (!$transition->accepted()
            || $transition->classification() !== TransitionPlan::REPAIR
            || !$migration->isAccepted()
            || !$transition->package()->migrationDeclaration()->declaresCoreMigrations()
            || $migration->migrations() === []
            || $transition->installedState() === null
            || PackageVersion::compare($migration->initialWebcoreVersion(), $migration->virtualFinalWebcoreVersion()) !== 0
            || PackageVersion::compare($migration->virtualFinalWebcoreVersion(), $transition->package()->targetWebcoreVersion()) !== 0
        ) {
            return $transition;
        }

        foreach ($migration->migrations() as $descriptor) {
            if (!$descriptor instanceof CoreMigrationDescriptor) {
                return $transition;
            }
        }

        return $transition->asDatabaseUpdate();
    }
}
