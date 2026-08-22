<?php

namespace Copot\Core;

final class ModuleSchemaProvisioner
{
    public function __construct(private Database $database)
    {
    }

    public function provision(ModuleDefinition $module, ModuleProvisioningContext $context): void
    {
        $relative = $module->schema();
        if ($relative === null) {
            return;
        }

        $root = realpath($module->path());
        $path = $root === false
            ? false
            : realpath($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
        if ($path === false || !is_file($path) || !str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new ModuleLifecycleException('Module schema is unavailable.');
        }

        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new ModuleLifecycleException('Module schema is unavailable.');
        }

        $catalog = DatabaseTableOwnershipCatalog::current();
        $owner = DatabaseTableOwner::module($module->name());
        $statements = (new InstallerSchemaRunner($path, 5, $catalog))->statements($contents);
        foreach ($statements as $statement) {
            if (preg_match('/\A(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT(?:\s+IGNORE)?\s+INTO)\s+`?([a-z][a-z0-9_]*)`?/i', $statement, $match) !== 1) {
                throw new ModuleLifecycleException('Module schema statement has no table target.');
            }
            if ($catalog->owner(strtolower($match[1]))->key() !== $owner->key()) {
                throw new ModuleLifecycleException('Module schema attempted to mutate a table outside its ownership.');
            }
            $this->database->connection()->exec($context->sql($statement));
        }
    }
}
