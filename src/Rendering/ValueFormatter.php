<?php

namespace Nfgarching\Documents\Rendering;

use Illuminate\Support\Carbon;
use Throwable;

/** Formatiert Eingaben je Feldtyp für die Ausgabe im Dokument */
final class ValueFormatter
{
    public static function display(array $field, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return match ($field['type'] ?? 'text') {
            'date'     => self::date($value, ($field['format'] ?? '') === 'long'),
            'money'    => ($n = self::number($value)) === null ? (string) $value : number_format($n, 2, ',', '.').' €',
            'number'   => ($n = self::number($value)) === null ? (string) $value : self::plainNumber($n),
            'checkbox' => self::bool($value) ? 'ja' : '',
            'select'   => self::options($field)[(string) $value] ?? (string) $value,
            default    => (string) $value,
        };
    }

    /** Rohwert für Abschnitte und Speicherung */
    public static function raw(array $field, mixed $value): mixed
    {
        return match ($field['type'] ?? 'text') {
            'checkbox'        => self::bool($value),
            'money', 'number' => self::number($value) ?? $value,
            default           => $value,
        };
    }

    /** "a=Text A\nb=Text B" oder "Text A\nText B" → [wert => beschriftung] */
    public static function options(array $field): array
    {
        $opts = $field['options'] ?? [];

        if (is_array($opts)) {
            return $opts;
        }

        $out = [];

        foreach (preg_split('/\R/', (string) $opts) as $line) {
            if (($line = trim($line)) === '') {
                continue;
            }

            [$k, $l] = str_contains($line, '=') ? array_map('trim', explode('=', $line, 2)) : [$line, $line];
            $out[$k] = $l;
        }

        return $out;
    }

    /** Akzeptiert 1234.5, "1234,50", "1.234,50", "1,234.50" */
    public static function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $s = preg_replace('/[^\d,.\-]/', '', (string) $value);

        if ($s === '' || $s === '-') {
            return null;
        }

        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $s = str_replace(['.', ','], ['', '.'], $s);       // deutsch
        } else {
            $s = str_replace(',', '', $s);                      // englisch
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private static function plainNumber(float $n): string
    {
        return floor($n) == $n ? number_format($n, 0, ',', '.') : rtrim(rtrim(number_format($n, 4, ',', '.'), '0'), ',');
    }

    private static function date(mixed $value, bool $long): string
    {
        try {
            $d = $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value);
        } catch (Throwable) {
            return (string) $value;
        }

        return $long ? $d->locale('de')->translatedFormat('j. F Y') : $d->format('d.m.Y');
    }

    private static function bool(mixed $value): bool
    {
        return is_string($value)
            ? ! in_array(mb_strtolower(trim($value)), ['', '0', 'nein', 'false', 'off'], true)
            : (bool) $value;
    }
}
