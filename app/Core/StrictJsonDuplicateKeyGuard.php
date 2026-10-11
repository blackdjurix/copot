<?php

namespace Copot\Core;

/** Validates JSON syntax and rejects duplicate object members without reserializing it. */
final class StrictJsonDuplicateKeyGuard
{
    private int $offset = 0;
    private int $length;

    private function __construct(private string $json)
    {
        $this->length = strlen($json);
    }

    public static function validate(string $json): void
    {
        if (!preg_match('//u', $json)) {
            throw new \RuntimeException('JSON must be valid UTF-8.');
        }

        $parser = new self($json);
        $parser->value();
        $parser->whitespace();

        if ($parser->offset !== $parser->length) {
            throw new \RuntimeException('JSON contains trailing data.');
        }
    }

    private function value(): void
    {
        $this->whitespace();
        $character = $this->json[$this->offset] ?? '';

        if ($character === '{') {
            $this->object();
        } elseif ($character === '[') {
            $this->array();
        } elseif ($character === '"') {
            $this->string();
        } elseif ($character === '-' || ($character >= '0' && $character <= '9')) {
            $this->number();
        } elseif (substr($this->json, $this->offset, 4) === 'true') {
            $this->offset += 4;
        } elseif (substr($this->json, $this->offset, 5) === 'false') {
            $this->offset += 5;
        } elseif (substr($this->json, $this->offset, 4) === 'null') {
            $this->offset += 4;
        } else {
            throw new \RuntimeException('JSON contains an invalid value.');
        }
    }

    private function object(): void
    {
        $this->offset++;
        $this->whitespace();
        $keys = [];
        if (($this->json[$this->offset] ?? '') === '}') { $this->offset++; return; }

        while (true) {
            $this->whitespace();
            if (($this->json[$this->offset] ?? '') !== '"') { throw new \RuntimeException('JSON object key is invalid.'); }
            $key = $this->string();
            if (isset($keys[$key])) { throw new \RuntimeException('JSON object contains a duplicate key.'); }
            $keys[$key] = true;
            $this->whitespace();
            if (($this->json[$this->offset] ?? '') !== ':') { throw new \RuntimeException('JSON object separator is missing.'); }
            $this->offset++;
            $this->value();
            $this->whitespace();
            $character = $this->json[$this->offset] ?? '';
            if ($character === '}') { $this->offset++; return; }
            if ($character !== ',') { throw new \RuntimeException('JSON object delimiter is invalid.'); }
            $this->offset++;
        }
    }

    private function array(): void
    {
        $this->offset++;
        $this->whitespace();
        if (($this->json[$this->offset] ?? '') === ']') { $this->offset++; return; }
        while (true) {
            $this->value();
            $this->whitespace();
            $character = $this->json[$this->offset] ?? '';
            if ($character === ']') { $this->offset++; return; }
            if ($character !== ',') { throw new \RuntimeException('JSON array delimiter is invalid.'); }
            $this->offset++;
        }
    }

    private function string(): string
    {
        $start = $this->offset++;
        while ($this->offset < $this->length) {
            $character = $this->json[$this->offset++];
            if ($character === '\\') {
                if ($this->offset >= $this->length) { throw new \RuntimeException('JSON string escape is incomplete.'); }
                $escape = $this->json[$this->offset++];
                if ($escape === 'u') {
                    if ($this->offset + 4 > $this->length || preg_match('/^[0-9A-Fa-f]{4}$/', substr($this->json, $this->offset, 4)) !== 1) { throw new \RuntimeException('JSON unicode escape is invalid.'); }
                    $this->offset += 4;
                } elseif (!str_contains('"\\/bfnrt', $escape)) {
                    throw new \RuntimeException('JSON string escape is invalid.');
                }
            } elseif ($character === '"') {
                $encoded = substr($this->json, $start, $this->offset - $start);
                try { $value = json_decode($encoded, true, 4, JSON_THROW_ON_ERROR); }
                catch (\JsonException $exception) { throw new \RuntimeException('JSON string is invalid.', 0, $exception); }
                if (!is_string($value)) { throw new \RuntimeException('JSON string is invalid.'); }
                return $value;
            } elseif (ord($character) < 0x20) {
                throw new \RuntimeException('JSON string contains a control character.');
            }
        }
        throw new \RuntimeException('JSON string is unterminated.');
    }

    private function number(): void
    {
        $remaining = substr($this->json, $this->offset);
        if (preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/', $remaining, $match) !== 1) { throw new \RuntimeException('JSON number is invalid.'); }
        $this->offset += strlen($match[0]);
    }

    private function whitespace(): void
    {
        while ($this->offset < $this->length && str_contains(" \t\r\n", $this->json[$this->offset])) { $this->offset++; }
    }
}
