<?php

final class SettingsSection
{
    public function __construct(
        private string $identifier,
        private string $label,
        private ?string $description,
        private array $fields
    ) {
        if ($identifier === '' || trim($label) === '' || $fields === []) {
            throw new LogicException('Settings section contract is invalid.');
        }

        $identifiers = [];

        foreach ($fields as $field) {
            if (!$field instanceof SettingsField || $field->namespace() !== $identifier) {
                throw new LogicException('Settings section contains an invalid field.');
            }

            if (isset($identifiers[$field->identifier()])) {
                throw new LogicException('Settings section field identifiers must be unique.');
            }

            $identifiers[$field->identifier()] = true;
        }
    }

    public function identifier(): string { return $this->identifier; }
    public function label(): string { return $this->label; }
    public function description(): ?string { return $this->description; }
    public function fields(): array { return $this->fields; }
}
