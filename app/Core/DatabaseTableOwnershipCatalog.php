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
            if (!$target instanceof DatabaseTableOwnership || $target->owner()->key() !== $extension->targetOwner()->key()) {
                throw new \InvalidArgumentException('Extension target-owner identity does not match authoritative table ownership.');
            }
            if ($target->owner()->isModule() && $target->owner()->moduleIdentity()?->value() === $extension->module()->value()) {
                throw new \InvalidArgumentException('A Module cannot claim its own table as a cross-owner extension.');
            }
            if (isset($this->extensions[$extension->key()])) throw new \InvalidArgumentException('Duplicate table extension grant.');
            $this->extensions[$extension->key()] = $extension;
        }
    }

    public static function current(): self
    {
        $webcore = ['users','roles','permissions','user_roles','role_permissions','settings','themes','modules','module_permissions','content','core_migration_history','core_schema_generation'];
        $webcore = array_merge($webcore, ['media', 'media_usages', 'navigation_menus', 'navigation_items', 'redirects']);
        $modules = [
            'navigation' => ['navigation_menu_assignments'],
            'taxonomy' => ['taxonomy_types','taxonomy_terms','taxonomy_assignments'],
            'media' => ['media_variants'],
            'form-manager' => ['forms','form_fields','form_field_options','form_submissions','form_submission_values','form_submission_attempts'],
        ];
        $entries = [];
        foreach ($webcore as $table) $entries[] = new DatabaseTableOwnership($table, DatabaseTableOwner::webcore(), 'database/schema.sql', null, self::targetOwnerFor($table), self::transitionWorkUnitFor($table));
        foreach ($modules as $module => $tables) foreach ($tables as $table) $entries[] = new DatabaseTableOwnership($table, DatabaseTableOwner::module($module), 'modules/' . $module . '/schema.sql', 'aggregate-installer:database/schema.sql', self::targetOwnerFor($table), self::transitionWorkUnitFor($table));
        return new self($entries, [
            new DatabaseTableExtensionGrant('media', 'content', DatabaseTableOwner::webcore(), DatabaseTableExtensionGrant::ADD_COLUMN, 'featured_media_id', 'database/upgrades/m3_8_media_library.sql', 'm3.8-wu7-pre-m3.8-upgrade'),
            new DatabaseTableExtensionGrant('media', 'content', DatabaseTableOwner::webcore(), DatabaseTableExtensionGrant::ADD_INDEX, 'idx_content_featured_media', 'database/upgrades/m3_8_media_library.sql', 'm3.8-wu7-pre-m3.8-upgrade'),
        ]);
    }

    public function ownership(string $logicalName): DatabaseTableOwnership
    {
        return $this->byTable[$logicalName] ?? throw new \InvalidArgumentException('Database table ownership is unknown.');
    }
    public function owner(string $logicalName): DatabaseTableOwner { return $this->ownership($logicalName)->owner(); }
    public function targetOwner(string $logicalName): DatabaseTableOwner { return $this->ownership($logicalName)->targetOwner(); }
    public function targetTransitionWorkUnit(string $logicalName): ?string { return $this->ownership($logicalName)->targetTransitionWorkUnit(); }
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
        foreach (['users','roles','permissions','user_roles','role_permissions','settings','themes','modules','module_permissions','media','media_usages','navigation_menus','navigation_items','redirects','core_migration_history','core_schema_generation'] as $table) $catalog[$table] = DatabaseTableOwner::webcore();
        foreach (['navigation'=>['navigation_menu_assignments'],'taxonomy'=>['taxonomy_types','taxonomy_terms','taxonomy_assignments'],'media'=>['media_variants'],'form-manager'=>['forms','form_fields','form_field_options','form_submissions','form_submission_values','form_submission_attempts']] as $module=>$tables) foreach ($tables as $table) $catalog[$table] = DatabaseTableOwner::module($module);
        $catalog['content'] = DatabaseTableOwner::webcore();
        return $catalog;
    }

    private static function targetOwnerFor(string $table): DatabaseTableOwner
    {
        return match ($table) {
            'content' => DatabaseTableOwner::webcore(),
            'media', 'media_usages' => DatabaseTableOwner::webcore(),
            'navigation_menus', 'navigation_items' => DatabaseTableOwner::webcore(),
            'redirects' => DatabaseTableOwner::webcore(),
            default => self::lockedOwners()[$table],
        };
    }

    private static function transitionWorkUnitFor(string $table): ?string
    {
        return match ($table) {
            default => null,
        };
    }
}
