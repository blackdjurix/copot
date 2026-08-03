<?php

use Copot\Core\Database;
use Copot\Core\Session;

final class FormCorruptDefinitionException extends RuntimeException {}
final class FormPublicUnavailableException extends RuntimeException {}
final class FormPublicAntiAbuseException extends RuntimeException {}

final class FormPublicSubmissionService
{
    private const NONCE_KEY = '_copot_form_render_nonces';
    private const NONCE_TTL = 1800;
    private const MINIMUM_SECONDS = 3;
    private const MAX_NONCES = 8;

    public function __construct(
        private FormRepository $forms,
        private FormFieldRepository $fields,
        private FormSubmissionLifecycleService $submissions,
        private FormSubmissionAttemptRepository $attempts,
        private FormDefinitionValidator $definitions,
        private FormPublicRequestValidator $requests
    ) {}

    /** @return array{form: Form, fields: FormField[]} */
    public function published(FormId|int $formId): array
    {
        $id = $formId instanceof FormId ? $formId : new FormId($formId);
        $form = $this->forms->findById($id);
        if ($form === null || $form->status() !== 'published') throw new FormPublicUnavailableException('Form is unavailable.');
        $fields = $this->fields->forForm($id);
        $keys = [];
        foreach ($fields as $field) {
            if (isset($keys[$field->key()])) throw new FormCorruptDefinitionException('Form definition is invalid.');
            $keys[$field->key()] = true;
            try {
                $this->definitions->field([
                    'field_key' => $field->key(), 'label' => $field->label(), 'field_type' => $field->type(),
                    'sort_order' => $field->sortOrder(), 'is_required' => $field->required() ? 1 : 0,
                    'min_length' => $field->minLength(), 'max_length' => $field->maxLength(),
                    'options' => array_map(static fn (FormFieldOption $option): array => [
                        'option_value' => $option->value(), 'option_label' => $option->label(), 'sort_order' => $option->sortOrder(),
                    ], $field->options()),
                ]);
            } catch (Throwable $failure) { throw new FormCorruptDefinitionException('Form definition is invalid.', 0, $failure); }
        }
        return ['form' => $form, 'fields' => $fields];
    }

    public function issueNonce(Session $session, FormId|int $formId): string
    {
        $id = $formId instanceof FormId ? $formId->value() : (new FormId($formId))->value();
        $nonces = $session->get(self::NONCE_KEY, []);
        $nonces = is_array($nonces) ? $nonces : [];
        $now = time();
        foreach ($nonces as $token => $state) if (!is_array($state) || ($now - (int) ($state['issued_at'] ?? 0)) > self::NONCE_TTL) unset($nonces[$token]);
        while (count($nonces) >= self::MAX_NONCES) array_shift($nonces);
        $token = bin2hex(random_bytes(32));
        $nonces[$token] = ['form_id' => $id, 'issued_at' => $now];
        $session->set(self::NONCE_KEY, $nonces);
        return $token;
    }

    public function assertBodySize(): void { $this->requests->assertBodySize(); }

    public function submit(Session $session, FormId|int $formId, string $nonce, mixed $values, mixed $honeypot, string $clientAddress): FormSubmission
    {
        $id = $formId instanceof FormId ? $formId : new FormId($formId);
        $this->requests->assertBodySize();
        $this->requests->honeypot($honeypot);
        $this->validateNonce($session, $id, $nonce);
        $this->attempts->guardAndRecord($id, $this->normalizeClientAddress($clientAddress));
        $normalizedValues = $this->requests->values($values);
        try {
            $submission = $this->submissions->persist($id, $normalizedValues);
            $this->consumeNonce($session, $nonce);
            return $submission;
        } catch (FormNotFoundException|InvalidArgumentException|FormSubmissionFieldValidationException $failure) {
            throw $failure;
        } catch (Throwable $failure) {
            throw $failure;
        }
    }

    public function normalizeClientAddress(string $address): string
    {
        $packed = @inet_pton($address);
        if (!is_string($packed) || ($address === '' || filter_var($address, FILTER_VALIDATE_IP) === false)) throw new FormPublicAntiAbuseException('The submission could not be accepted.');
        return $packed;
    }

    private function validateNonce(Session $session, FormId $formId, string $token): void
    {
        $nonces = $session->get(self::NONCE_KEY, []);
        $state = is_array($nonces) ? ($nonces[$token] ?? null) : null;
        $issued = is_array($state) ? (int) ($state['issued_at'] ?? 0) : 0;
        if (!is_array($state) || (int) ($state['form_id'] ?? 0) !== $formId->value() || $issued <= 0 || time() - $issued > self::NONCE_TTL || time() - $issued < self::MINIMUM_SECONDS) throw new FormPublicAntiAbuseException('The submission could not be accepted.');
    }

    private function consumeNonce(Session $session, string $token): void
    {
        $nonces = $session->get(self::NONCE_KEY, []);
        if (!is_array($nonces)) return;
        unset($nonces[$token]);
        $session->set(self::NONCE_KEY, $nonces);
    }
}
