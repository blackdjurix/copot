<?php

namespace Copot\Core;

final class SystemManagerSettingsService
{
    private const LOCALIZATION = [
        'locale' => 'locale',
        'timezone' => 'timezone',
        'date_format' => 'date_format',
        'time_format' => 'time_format',
    ];

    public function __construct(
        private SettingsService $settings,
        private Database $database
    ) {
    }

    public function localization(): array
    {
        $values = [];
        foreach (self::LOCALIZATION as $key) {
            $values[$key] = $this->settings->get('localization', $key);
        }
        return $values;
    }

    public function saveLocalization(array $values): void
    {
        $normalized = [];
        foreach (self::LOCALIZATION as $key) {
            $value = $values[$key] ?? null;
            if (!is_string($value)) {
                throw new SettingsException('All Localization values are required.');
            }
            $normalized[$key] = $value;
            $this->settings->validate('localization', $key, $value, 'string');
        }

        $connection = $this->database->connection();
        $connection->beginTransaction();
        try {
            foreach ($normalized as $key => $value) {
                $this->settings->set('localization', $key, $value, 'string');
            }
            $connection->commit();
        } catch (\Throwable $failure) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $failure;
        }
    }
}
