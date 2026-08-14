<?php

namespace Copot\Core;

final class DatabaseTableOwnershipCatalog
{
    /** @var array<string,DatabaseTableOwnership> */
    private array $byTable = [];
    /** @var array<string,DatabaseTableExtensionGrant> */
    private array $extensions = [];

    /** @param list<DatabaseTableOwnership> $ownerships @param list<DatabaseTableExtensionGrant> $extensions */
    public function __construct(array $ownerships, array $extensions = [])
    {
        $expected = self::lockedOwners();
        foreach ($ownerships as $ownership) {
            if (!$ownership instanceof DatabaseTableOwnership) throw new \InvalidArgumentException('Invalid table ownership entry.');
            $name = $ownership->logicalName();
            if (isset($this->byTable[$name])) throw new \InvalidArgumentException('A database table cannot have shared or duplicate ownership.');
            if (!isset($expected[$name]) || $ownership->owner()->key() !== $expected[$name]->key()) {
                throw new \InvalidArgumentException('Database table ownership conflicts with the locked authority baseline.');
            }
            $this->byTable[$name] = $ownership;
        }
        if (count($this->byTable) !== count($expected) || array_diff_key($expected, $this->byTable) !== []) {
            throw new \InvalidArgumentException('Database table ownership catalog is incomplete.');
        }
        foreach ($extensions as $extension) {
            if (!$extension instanceof DatabaseTableExtensionGrant) throw new \InvalidArgumentException('Invalid table extension grant.');
            $target = $this->byTable[$extension->table()] ?? null;
            if (!$target instanceof DatabaseTableOwnership || !$target->owner()->isWebcore()) {
                throw new \InvalidArgumentException('Module extensions may target Webcore-owned tables only.');
            }
            if (isset($this->extensions[$extension->key()])) throw new \InvalidArgumentException('Duplicate table extension grant.');
            $this->extensions[$extension->key()] = $extension;
        }
    }

    public static function current(): self
    {
        $webcore = ['users','roles','permissions','user_roles','role_permissions','settings','themes','modules','module_permissions','core_migration_history','core_schema_generation'];
        $modules = [
            'navigation' => ['navigation_menus','navigation_items','navigation_menu_assignments'],
            'content' => ['content'],
            'taxonomy' => ['taxonomy_types','taxonomy_terms','taxonomy_assignments'],
            'media' => ['media','media_variants','media_usages'],
            'redirects' => ['redirects'],
            'form-manager' => ['forms','form_fields','form_field_options','form_submissions','form_submission_values','form_submission_attempts'],
        ];
        $entries = [];
        foreach ($webcore as $table) $entries[] = new DatabaseTableOwnership($table, DatabaseTableOwner::webcore(), 'database/schema.sql');
        foreach ($modules as $module => $tables) foreach ($tables as $table) $entries[] = new DatabaseTableOwnership($table, DatabaseTableOwner::module($module), 'database/schema.sql', 'aggregate-installer:database/schema.sql');
        return new self($entries);
    }

    public function ownership(string $logicalName): DatabaseTableOwnership
    {
        return $this->byTable[$logicalName] ?? throw new \InvalidArgumentException('Database table ownership is unknown.');
    }
    public function owner(string $logicalName): DatabaseTableOwner { return $this->ownership($logicalName)->owner(); }
    public function physicalName(string $logicalName, DatabaseTableNames $tables): string { return $this->ownership($logicalName)->physicalName($tables); }
    /** @return list<DatabaseTableOwnership> */
    public function all(): array { return array_values($this->byTable); }
    /** @return list<DatabaseTableExtensionGrant> */
    public function extensions(): array { return array_values($this->extensions); }
    public function extension(string $module, string $table, string $kind, string $element): DatabaseTableExtensionGrant
    {
        $key = $module . ':' . $table . ':' . $kind . ':' . $element;
        return $this->extensions[$key] ?? throw new \InvalidArgumentException('Database table extension is not authorized.');
    }

    /** @return array<string,DatabaseTableOwner> */
    private static function lockedOwners(): array
    {
        $catalog = [];
        foreach (['users','roles','permissions','user_roles','role_permissions','settings','themes','modules','module_permissions','core_migration_history','core_schema_generation'] as $table) $catalog[$table] = DatabaseTableOwner::webcore();
        foreach (['navigation'=>['navigation_menus','navigation_items','navigation_menu_assignments'],'content'=>['content'],'taxonomy'=>['taxonomy_types','taxonomy_terms','taxonomy_assignments'],'media'=>['media','media_variants','media_usages'],'redirects'=>['redirects'],'form-manager'=>['forms','form_fields','form_field_options','form_submissions','form_submission_values','form_submission_attempts']] as $module=>$tables) foreach ($tables as $table) $catalog[$table] = DatabaseTableOwner::module($module);
        return $catalog;
    }
}
