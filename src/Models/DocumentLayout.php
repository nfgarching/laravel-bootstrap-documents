<?php

namespace Nfgarching\Documents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Briefbogen bzw. Seitenlayout.
 *
 * Formen:
 *  - din5008-b  Geschäftsbrief nach DIN 5008, Form B (Anschriftfeld 45 mm von oben)
 *  - din5008-a  Form A (Anschriftfeld 27 mm von oben, kleiner Briefkopf)
 *  - plain      ohne Anschriftfeld, z. B. für Verträge und Vereinbarungen
 *
 * @property array|null $page
 * @property array|null $fields
 */
class DocumentLayout extends Model
{
    protected $table = 'nfdoc_layouts';

    protected $guarded = ['id'];

    protected $casts = [
        'page'       => 'array',
        'fields'     => 'array',
        'is_default' => 'boolean',
    ];

    public const FORMS = [
        'din5008-b' => 'Brief DIN 5008 Form B',
        'din5008-a' => 'Brief DIN 5008 Form A',
        'plain'     => 'Ohne Anschriftfeld (Vertrag, Vereinbarung)',
    ];

    /** Maße in mm, Schrift in pt */
    public static function pageDefaults(string $form): array
    {
        $common = [
            'margin_top'    => 25,
            'margin_right'  => 20,
            'margin_bottom' => 30,
            'margin_left'   => 25,
            'font'          => 'Arial',
            'font_size'     => 11,
            'line_height'   => 1.35,
            'paragraph'     => 2.5,     // Abstand nach Absätzen in mm
            'color'         => '#000000',
            'accent'        => '#1f3a5f',
            'logo_height'   => 18,
            'follow_header_first' => false,  // Kopfzeile auch auf Seite 1
        ];

        return match ($form) {
            'din5008-a' => $common + [
                'address_top' => 27,  'address_left' => 20, 'info_top' => 32, 'info_left' => 125,
                'text_start'  => 80.5, 'fold_marks' => true, 'folds' => [87, 192],
            ],
            'plain' => array_merge($common, [
                'margin_top' => 25, 'margin_bottom' => 25, 'fold_marks' => false, 'follow_header_first' => true,
            ]),
            default => $common + [
                'address_top' => 45,  'address_left' => 20, 'info_top' => 50, 'info_left' => 125,
                'text_start'  => 98.5, 'fold_marks' => true, 'folds' => [105, 210],
            ],
        };
    }

    /** Einstellungen mit Standardwerten der Form */
    public function settings(): array
    {
        return array_merge(static::pageDefaults($this->form ?: 'din5008-b'), array_filter(
            (array) $this->page,
            fn ($v) => $v !== null && $v !== '',
        ));
    }

    public function isLetter(): bool
    {
        return str_starts_with((string) $this->form, 'din5008');
    }

    /** Standardfelder eines Briefbogens: Anschrift und Datum */
    public static function letterFields(): array
    {
        return [
            ['key' => 'anschrift', 'label' => 'Anschrift', 'type' => 'textarea', 'default' => '${empfaenger.anschrift}', 'required' => true],
            ['key' => 'datum', 'label' => 'Datum', 'type' => 'date', 'default' => '${heute_iso}', 'required' => true],
        ];
    }

    /** Noch nicht gespeichertes Logo (Data-URI) für die Vorschau im Editor */
    public ?string $previewLogo = null;

    public function logoDataUri(): ?string
    {
        if ($this->previewLogo) {
            return $this->previewLogo;
        }

        if (! $this->logo_path) {
            return null;
        }

        $disk = Storage::disk(config('nfdocuments.storage.disk', 'local'));

        if (! $disk->exists($this->logo_path)) {
            return null;
        }

        $mime = $disk->mimeType($this->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($this->logo_path));
    }

    public static function defaultLayout(): ?self
    {
        return static::query()->where('is_default', true)->first() ?? static::query()->orderBy('id')->first();
    }

    public function makeDefault(): void
    {
        static::query()->whereKeyNot($this->getKey())->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class, 'layout_id');
    }
}
