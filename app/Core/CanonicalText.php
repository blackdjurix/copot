<?php

namespace Copot\Core;

final class CanonicalText
{
    public static function assert(string $value, string $label = 'Text'): void
    {
        if (!preg_match('//u', $value) || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) { throw new \InvalidArgumentException($label . ' is not valid canonical text.'); }
        if (preg_match('/[^\x00-\x7F]/', $value) === 1) {
            if (!class_exists('Normalizer')) { throw new \InvalidArgumentException($label . ' cannot establish NFC.'); }
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if ($normalized === false || $normalized !== $value) { throw new \InvalidArgumentException($label . ' is not NFC.'); }
        }
    }
}
