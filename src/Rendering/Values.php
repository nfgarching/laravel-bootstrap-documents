<?php

namespace Nfgarching\Documents\Rendering;

use Illuminate\Support\Arr;

/**
 * Werte für die Platzhalter eines Dokuments.
 *
 * display: Schlüssel (mit Punkten) => formatierter Text, z. B. 'betrag' => '1.234,50 €'
 * raw:     Rohwerte, z. B. 'betrag' => 1234.5 (für Ein-/Ausblenden von Abschnitten)
 * lists:   Listen für Tabellenzeilen in Word, z. B. 'raten' => [['nr' => 1, …], …]
 */
final class Values
{
    public function __construct(
        public readonly array $display = [],
        public readonly array $raw = [],
        public readonly array $lists = [],
    ) {}

    /** Aus verschachtelten Werten (z. B. von Datenquellen) */
    public static function fromNested(array $nested): self
    {
        $display = [];
        $raw = [];
        $lists = [];

        self::walk($nested, '', $display, $raw, $lists);

        return new self($display, $raw, $lists);
    }

    private static function walk(array $data, string $prefix, array &$display, array &$raw, array &$lists): void
    {
        foreach ($data as $key => $value) {
            $path = $prefix.$key;

            if (is_array($value)) {
                if (array_is_list($value) && $value && is_array($value[0])) {
                    $lists[$path] = $value;
                    continue;
                }

                if (array_is_list($value)) {
                    $display[$path] = implode(', ', array_map('strval', array_filter($value, 'is_scalar')));
                    $raw[$path] = $value;
                    continue;
                }

                self::walk($value, $path.'.', $display, $raw, $lists);
                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                $display[$path] = $value->format('d.m.Y');
            } elseif (is_bool($value)) {
                $display[$path] = $value ? 'ja' : '';
            } elseif (is_scalar($value) || $value === null || $value instanceof \Stringable) {
                $display[$path] = (string) $value;
            } else {
                continue;
            }

            $raw[$path] = $value;
        }
    }

    public function merge(self $other): self
    {
        return new self(
            array_replace($this->display, $other->display),
            array_replace($this->raw, $other->raw),
            array_replace($this->lists, $other->lists),
        );
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->display) || array_key_exists($key, $this->lists);
    }

    public function get(string $key): ?string
    {
        return $this->display[$key] ?? null;
    }

    /** Für Abschnitte ${key} … ${/key}: sichtbar, wenn der Wert „wahr“ ist */
    public function truthy(string $key): bool
    {
        if (array_key_exists($key, $this->lists)) {
            return (bool) $this->lists[$key];
        }

        $v = $this->raw[$key] ?? $this->display[$key] ?? null;

        if (is_string($v)) {
            $v = mb_strtolower(trim($v));

            return ! in_array($v, ['', '0', 'nein', 'false', 'off'], true);
        }

        return (bool) $v;
    }

    public function toArray(): array
    {
        return Arr::undot($this->raw) + Arr::undot($this->lists);
    }
}
