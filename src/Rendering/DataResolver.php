<?php

namespace Nfgarching\Documents\Rendering;

use Illuminate\Contracts\Container\Container;
use Nfgarching\Documents\Contracts\DataProvider;
use Throwable;

/**
 * Sammelt Werte aus den Datenquellen und den Eingaben eines Dokuments.
 * Eingaben überschreiben Werte der Quellen mit gleichem Schlüssel.
 */
class DataResolver
{
    /** @var list<DataProvider>|null */
    private ?array $providers = null;

    public function __construct(private Container $app) {}

    /** @return list<DataProvider> */
    public function providers(): array
    {
        return $this->providers ??= collect((array) config('nfdocuments.providers', []))
            ->filter(fn ($class) => is_string($class) && class_exists($class))
            ->map(fn ($class) => $this->app->make($class))
            ->filter(fn ($p) => $p instanceof DataProvider)
            ->values()
            ->all();
    }

    /** Platzhalter aller Quellen für das Einfügen-Menü: [schluessel => beschreibung] */
    public function placeholders(): array
    {
        $out = [];

        foreach ($this->providers() as $p) {
            $out += $p->placeholders();
        }

        return $out;
    }

    public function providerValues(array $context): Values
    {
        $values = new Values;

        foreach ($this->providers() as $p) {
            try {
                $values = $values->merge(Values::fromNested($p->values($context)));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $values;
    }

    /**
     * Vorgabewerte der Felder; Vorgaben dürfen Platzhalter enthalten,
     * z. B. default '${empfaenger.anschrift}' oder '${heute_iso}'.
     */
    public function defaults(array $fields, array $context, ?Values $provider = null): array
    {
        $provider ??= $this->providerValues($context);
        $out = [];

        foreach ($fields as $f) {
            $key = $f['key'] ?? null;

            if (! $key) {
                continue;
            }

            $default = $f['default'] ?? null;

            if (is_string($default) && $default !== '') {
                $default = trim(Placeholders::renderText($default, $provider));
            } elseif ($default === null && isset($provider->raw[$key])) {
                $default = $provider->raw[$key];   // gleichnamiger Wert der Quelle
            }

            $out[$key] = ($f['type'] ?? '') === 'checkbox' ? ValueFormatter::raw($f, $default) : ($default ?? '');
        }

        return $out;
    }

    /** Alle Werte: Quellen + formatierte Eingaben */
    public function values(array $fields, array $input, array $context): Values
    {
        $values = $this->providerValues($context);
        $display = [];
        $raw = [];

        foreach ($fields as $f) {
            $key = $f['key'] ?? null;

            if (! $key || ! array_key_exists($key, $input)) {
                continue;
            }

            $display[$key] = ValueFormatter::display($f, $input[$key]);
            $raw[$key] = ValueFormatter::raw($f, $input[$key]);
        }

        return $values->merge(new Values($display, $raw));
    }

    /** Beispielwerte für die Vorschau im Vorlagen-Editor */
    public function sample(array $fields, array $context = []): array
    {
        $defaults = $this->defaults($fields, $context);

        foreach ($fields as $f) {
            $key = $f['key'] ?? null;

            if (! $key || ($defaults[$key] ?? '') !== '' && $defaults[$key] !== false) {
                continue;
            }

            $defaults[$key] = match ($f['type'] ?? 'text') {
                'date'     => now()->toDateString(),
                'money'    => 1234.5,
                'number'   => 3,
                'checkbox' => true,
                'select'   => array_key_first(ValueFormatter::options($f)) ?? '',
                'textarea' => $key === 'anschrift' ? "Frau\nErika Mustermann\nBeispielweg 7\n12345 Musterstadt" : '['.($f['label'] ?? $key).']',
                default    => '['.($f['label'] ?? $key).']',
            };
        }

        return $defaults;
    }
}
