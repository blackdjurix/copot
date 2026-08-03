<?php

abstract class FormPositiveId
{
    protected int $value;

    public function __construct(mixed $value)
    {
        if (!is_int($value) || $value <= 0) {
            throw new InvalidArgumentException('Identifier must be a positive integer.');
        }
        $this->value = $value;
    }

    public function value(): int { return $this->value; }
}

final class FormId extends FormPositiveId {}
final class FormFieldId extends FormPositiveId {}
final class FormSubmissionId extends FormPositiveId {}
