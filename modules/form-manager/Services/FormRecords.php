<?php

final class Form
{
    public function __construct(private FormId $id, private string $name, private string $status, private string $createdAt, private string $updatedAt) { if (!in_array($status, FormDefinitionValidator::FORM_STATES, true)) throw new InvalidArgumentException('Form status is invalid.'); }
    public function id(): FormId { return $this->id; }
    public function name(): string { return $this->name; }
    public function status(): string { return $this->status; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }
}

final class FormField
{
    /** @param FormFieldOption[] $options */
    public function __construct(private FormFieldId $id, private FormId $formId, private string $key, private string $label, private string $type, private int $sortOrder, private bool $required, private ?int $minLength, private ?int $maxLength, private array $options, private string $createdAt, private string $updatedAt) {}
    public function id(): FormFieldId { return $this->id; }
    public function formId(): FormId { return $this->formId; }
    public function key(): string { return $this->key; }
    public function label(): string { return $this->label; }
    public function type(): string { return $this->type; }
    public function sortOrder(): int { return $this->sortOrder; }
    public function required(): bool { return $this->required; }
    public function minLength(): ?int { return $this->minLength; }
    public function maxLength(): ?int { return $this->maxLength; }
    public function options(): array { return $this->options; }
}

final class FormFieldOption
{
    public function __construct(private FormFieldId $fieldId, private string $value, private string $label, private int $sortOrder, private string $createdAt, private string $updatedAt) {}
    public function fieldId(): FormFieldId { return $this->fieldId; }
    public function value(): string { return $this->value; }
    public function label(): string { return $this->label; }
    public function sortOrder(): int { return $this->sortOrder; }
}

final class FormSubmission
{
    /** @param FormSubmissionValue[] $values */
    public function __construct(private FormSubmissionId $id, private FormId $formId, private string $status, private array $values, private string $createdAt, private string $updatedAt) { if (!in_array($status, ['new', 'reviewed'], true)) throw new InvalidArgumentException('Submission status is invalid.'); }
    public function id(): FormSubmissionId { return $this->id; }
    public function formId(): FormId { return $this->formId; }
    public function status(): string { return $this->status; }
    public function values(): array { return $this->values; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }
}

final class FormSubmissionValue
{
    public function __construct(private int $id, private FormSubmissionId $submissionId, private ?FormFieldId $fieldId, private string $fieldKey, private string $fieldLabel, private string $fieldType, private string $valueText, private ?string $valueLabel, private string $createdAt) {}
    public function id(): int { return $this->id; }
    public function submissionId(): FormSubmissionId { return $this->submissionId; }
    public function fieldId(): ?FormFieldId { return $this->fieldId; }
    public function fieldKey(): string { return $this->fieldKey; }
    public function fieldLabel(): string { return $this->fieldLabel; }
    public function fieldType(): string { return $this->fieldType; }
    public function valueText(): string { return $this->valueText; }
    public function valueLabel(): ?string { return $this->valueLabel; }
}
