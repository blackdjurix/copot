<?php

final class SettingsValidationException extends RuntimeException
{
    public function __construct(
        private array $fieldErrors,
        private array $formErrors,
        private array $submittedValues
    ) {
        foreach ($fieldErrors as $identifier => $messages) {
            if (!is_string($identifier)
                || !preg_match('/^[a-z][a-z0-9_-]{0,63}\.[a-z][a-z0-9_-]{0,127}$/', $identifier)
                || !is_array($messages)
                || !array_is_list($messages)
                || $messages === []) {
                throw new LogicException('Settings validation field errors are invalid.');
            }

            foreach ($messages as $message) {
                if (!is_string($message) || trim($message) === '') {
                    throw new LogicException('Settings validation field error message is invalid.');
                }
            }
        }

        if (!array_is_list($formErrors)) {
            throw new LogicException('Settings validation form errors must be a list.');
        }

        foreach ($formErrors as $message) {
            if (!is_string($message) || trim($message) === '') {
                throw new LogicException('Settings validation form error message is invalid.');
            }
        }

        foreach ($submittedValues as $identifier => $value) {
            if (!is_string($identifier)
                || !preg_match('/^[a-z][a-z0-9_-]{0,63}\.[a-z][a-z0-9_-]{0,127}$/', $identifier)
                || !(is_string($value) || is_int($value) || is_bool($value)
                    || (is_float($value) && is_finite($value)))) {
                throw new LogicException('Settings validation submitted value is invalid.');
            }
        }

        parent::__construct('Settings Manager validation failed.');
    }

    public function fieldErrors(): array { return $this->fieldErrors; }
    public function formErrors(): array { return $this->formErrors; }
    public function submittedValues(): array { return $this->submittedValues; }
}
