<?php

namespace Copot\Core;

final class InstallerDatabaseOccupancyClassifier
{
    /** @param list<InstallerOwnershipProof> $proofs */
    public function classify(array $objects, array $proofs = []): InstallerDatabaseOccupancyResult
    {
        $objects = array_values(array_unique(array_map('strval', $objects)));
        sort($objects);
        if ($objects === []) return new InstallerDatabaseOccupancyResult(InstallerDatabaseOccupancy::EMPTY, [], []);

        $sets = [];
        foreach ($this->candidateNamespacesFromObjects($objects) as $namespace) {
            $names = new DatabaseTableNames($namespace);
            $owned = array_merge(
                array_map(fn (string $name): string => $names->table($name), DatabaseTableNames::coreTables()),
                array_map(fn (string $name): string => $names->moduleTable($name), DatabaseTableNames::moduleTables())
            );
            $present = array_values(array_intersect($owned, $objects));
            if (count($present) === count($owned)) $sets[$namespace] = $owned;
        }

        $proofByNamespace = [];
        $proofByInstallation = [];
        foreach ($proofs as $proof) {
            if (!$proof instanceof InstallerOwnershipProof || !$proof->isVerified() || isset($proofByNamespace[$proof->namespace()])) {
                return new InstallerDatabaseOccupancyResult(InstallerDatabaseOccupancy::AMBIGUOUS, $objects, [], ['Ownership evidence is missing, contradictory, or invalid.']);
            }
            if (isset($proofByInstallation[$proof->installationId()]) && $proofByInstallation[$proof->installationId()] !== $proof->namespace()) {
                return new InstallerDatabaseOccupancyResult(InstallerDatabaseOccupancy::AMBIGUOUS, $objects, [], ['One installation identity claims multiple namespaces.']);
            }
            $proofByNamespace[$proof->namespace()] = $proof;
            $proofByInstallation[$proof->installationId()] = $proof->namespace();
        }
        $unprovenSets = array_diff_key($sets, $proofByNamespace);
        if ($unprovenSets !== []) {
            return new InstallerDatabaseOccupancyResult(InstallerDatabaseOccupancy::AMBIGUOUS, $objects, [], ['A complete-looking COPOT object set lacks positive COPOT ownership evidence.']);
        }
        foreach ($proofByNamespace as $namespace => $proof) {
            if (!isset($sets[$namespace])) {
                return new InstallerDatabaseOccupancyResult(InstallerDatabaseOccupancy::AMBIGUOUS, $objects, [], ['Ownership evidence does not match the observed COPOT object set.']);
            }
        }

        if (count($sets) > 1) {
            $foreign = array_values(array_diff($objects, array_merge(...array_values($sets))));
            return new InstallerDatabaseOccupancyResult(
                $foreign === [] ? InstallerDatabaseOccupancy::MULTIPLE_COPOT : InstallerDatabaseOccupancy::MIXED,
                $objects,
                array_keys($sets),
                ['Multiple complete COPOT ownership sets were detected.']
            );
        }
        if (count($sets) === 1 && count($proofByNamespace) === 1) {
            $namespace = array_key_first($sets);
            $foreign = array_diff($objects, $sets[$namespace]);
            return new InstallerDatabaseOccupancyResult(
                $foreign === [] ? InstallerDatabaseOccupancy::COPOT : InstallerDatabaseOccupancy::MIXED,
                $objects,
                [$namespace],
                $foreign === [] ? [] : ['COPOT-owned and foreign objects coexist; foreign objects are not claimable.']
            );
        }

        // One generic collision is not ownership proof. Several canonical-looking
        // objects without a complete set are retained as unresolved evidence.
        $known = array_intersect($objects, array_merge(DatabaseTableNames::coreTables(), DatabaseTableNames::moduleTables()));
        if ($proofByNamespace !== []) {
            return new InstallerDatabaseOccupancyResult(InstallerDatabaseOccupancy::AMBIGUOUS, $objects, [], ['Positive ownership evidence is incomplete or contradictory.']);
        }
        $classification = count($known) >= 2
            ? InstallerDatabaseOccupancy::AMBIGUOUS
            : InstallerDatabaseOccupancy::FOREIGN_ONLY;
        $warnings = $classification === InstallerDatabaseOccupancy::AMBIGUOUS
            ? ['Incomplete COPOT-shaped ownership evidence could not be proven.']
            : [];
        return new InstallerDatabaseOccupancyResult($classification, $objects, [], $warnings);
    }

    /** @param list<string> $objects @return list<string> */
    public function candidateNamespaces(array $objects): array
    {
        return $this->candidateNamespacesFromObjects(array_values(array_unique(array_map('strval', $objects))));
    }

    private function candidateNamespacesFromObjects(array $objects): array
    {
        $candidates = [''];
        foreach ($objects as $object) {
            if (preg_match('/\A([a-z][a-z0-9_]{0,30})_(?:users|roles|permissions|modules|settings|content|forms|themes|core_migration_history)\z/', $object, $match)) {
                $candidates[] = $match[1];
            }
        }
        return array_values(array_unique($candidates));
    }
}
