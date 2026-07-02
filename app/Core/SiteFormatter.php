<?php

declare(strict_types=1);

namespace Copot\Core;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

class SiteFormatter
{
    private const FALLBACK_LOCALE = 'en_US';

    private const NUMBER_SEPARATORS = [
        'en_US' => ['decimal' => '.', 'grouping' => ','],
        'id_ID' => ['decimal' => ',', 'grouping' => '.'],
    ];

    private const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd M Y'];
    private const TIME_FORMATS = ['H:i', 'h:i A'];

    private DateTimeZone $timezone;
    private string $locale;

    public function __construct(
        string $locale,
        string $timezone,
        private string $dateFormat,
        private string $timeFormat
    ) {
        $this->locale = isset(self::NUMBER_SEPARATORS[$locale])
            ? $locale
            : self::FALLBACK_LOCALE;

        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (Exception $exception) {
            throw new InvalidArgumentException('Invalid site timezone.', previous: $exception);
        }

        if (!in_array($dateFormat, self::DATE_FORMATS, true)) {
            throw new InvalidArgumentException('Invalid site date format.');
        }

        if (!in_array($timeFormat, self::TIME_FORMATS, true)) {
            throw new InvalidArgumentException('Invalid site time format.');
        }
    }

    public function formatDate(DateTimeInterface $value): string
    {
        return $this->inSiteTimezone($value)->format($this->dateFormat);
    }

    public function formatTime(DateTimeInterface $value): string
    {
        return $this->inSiteTimezone($value)->format($this->timeFormat);
    }

    public function formatDateTime(DateTimeInterface $value): string
    {
        $localized = $this->inSiteTimezone($value);

        return $localized->format($this->dateFormat) . ' ' . $localized->format($this->timeFormat);
    }

    public function formatInteger(int $value): string
    {
        $separators = self::NUMBER_SEPARATORS[$this->locale];
        $number = (string) $value;
        $negative = str_starts_with($number, '-');
        $digits = $negative ? substr($number, 1) : $number;
        $grouped = preg_replace(
            '/\B(?=(?:\d{3})+(?!\d))/',
            $separators['grouping'],
            $digits
        );

        return ($negative ? '-' : '') . $grouped;
    }

    public function formatDecimal(int|float $value, int $fractionDigits = 2): string
    {
        return $this->formatNumber($value, $fractionDigits);
    }

    public function formatNumber(int|float $value, int $fractionDigits = 0): string
    {
        if ($fractionDigits < 0 || $fractionDigits > 6) {
            throw new InvalidArgumentException('Fraction digits must be between 0 and 6.');
        }

        if (is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException('Number must be finite.');
        }

        $separators = self::NUMBER_SEPARATORS[$this->locale];

        if (is_int($value)) {
            $integer = $this->formatInteger($value);

            return $fractionDigits === 0
                ? $integer
                : $integer . $separators['decimal'] . str_repeat('0', $fractionDigits);
        }

        return number_format(
            $value,
            $fractionDigits,
            $separators['decimal'],
            $separators['grouping']
        );
    }

    private function inSiteTimezone(DateTimeInterface $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($value)->setTimezone($this->timezone);
    }
}
