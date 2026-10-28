<?php

namespace Nfgarching\Documents\Livewire\Concerns;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Nfgarching\Documents\Rendering\Placeholders;

/** Eingabefelder (Name, Typ, Vorgabe …) in Layout- und Vorlagen-Editor */
trait EditsFields
{
    /** @var list<array{key: string, label: string, type: string, default: string, required: bool, options: string, help: string}> */
    public array $fields = [];

    public function addField(string $key = ''): void
    {
        $this->fields[] = [
            'key'      => $key,
            'label'    => $key ? Str::of($key)->afterLast('.')->replace('_', ' ')->ucfirst()->toString() : '',
            'type'     => 'text',
            'default'  => '',
            'required' => false,
            'options'  => '',
            'help'     => '',
        ];
    }

    public function removeField(int $i): void
    {
        unset($this->fields[$i]);
        $this->fields = array_values($this->fields);
    }

    public function moveField(int $i, int $dir): void
    {
        $j = $i + $dir;

        if (isset($this->fields[$i], $this->fields[$j])) {
            [$this->fields[$i], $this->fields[$j]] = [$this->fields[$j], $this->fields[$i]];
        }
    }

    /** Bereinigte Felder zum Speichern */
    protected function cleanFields(): array
    {
        return collect($this->fields)
            ->map(fn ($f) => [
                'key'      => trim((string) ($f['key'] ?? '')),
                'label'    => trim((string) ($f['label'] ?? '')),
                'type'     => $f['type'] ?? 'text',
                'default'  => (string) ($f['default'] ?? ''),
                'required' => (bool) ($f['required'] ?? false),
                'options'  => (string) ($f['options'] ?? ''),
                'help'     => trim((string) ($f['help'] ?? '')),
            ])
            ->filter(fn ($f) => $f['key'] !== '')
            ->map(fn ($f) => array_merge($f, ['label' => $f['label'] ?: $f['key']]))
            ->values()
            ->all();
    }

    protected function fieldRules(): array
    {
        return [
            'fields.*.key'  => ['nullable', 'regex:/^'.Placeholders::KEY.'$/', 'distinct'],
            'fields.*.type' => ['required', 'in:text,textarea,date,money,number,select,checkbox'],
        ];
    }

    protected function fieldMessages(): array
    {
        return [
            'fields.*.key.regex'    => 'Schlüssel nur aus Buchstaben (ohne Umlaute), Ziffern, Punkt, Unter- und Bindestrich.',
            'fields.*.key.distinct' => 'Jeder Schlüssel darf nur einmal vorkommen.',
        ];
    }

    protected function authorizeManage(): void
    {
        if ($gate = config('nfdocuments.gate')) {
            Gate::authorize($gate);
        }
    }

    /** Eindeutiger Slug für eine Tabelle */
    protected function uniqueSlug(string $modelClass, string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'vorlage';
        $slug = $base;

        for ($i = 2; $modelClass::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
