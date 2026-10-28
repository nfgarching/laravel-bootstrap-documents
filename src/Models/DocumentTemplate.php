<?php

namespace Nfgarching\Documents\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vorlage für Briefe, Vereinbarungen und Verträge.
 *
 * engine = html: Text (HTML) mit Platzhaltern ${schluessel}, gedruckt auf einem Briefbogen.
 * engine = docx: Word-Datei mit denselben Platzhaltern, Layout komplett in Word.
 *
 * Felder (fields): Liste aus
 *   ['key' => 'betrag', 'label' => 'Betrag', 'type' => 'money', 'default' => '', 'required' => true,
 *    'options' => "a=Text A\nb=Text B", 'help' => '…']
 * Typen: text, textarea, date, money, number, select, checkbox
 *
 * @property array|null $fields
 */
class DocumentTemplate extends Model
{
    protected $table = 'nfdoc_templates';

    protected $guarded = ['id'];

    protected $casts = [
        'fields'    => 'array',
        'is_active' => 'boolean',
    ];

    public const ENGINES = [
        'html' => 'Text-Editor (HTML)',
        'docx' => 'Word-Datei (.docx)',
    ];

    public const FIELD_TYPES = [
        'text'     => 'Text (einzeilig)',
        'textarea' => 'Text (mehrzeilig)',
        'date'     => 'Datum',
        'money'    => 'Betrag (€)',
        'number'   => 'Zahl',
        'select'   => 'Auswahl',
        'checkbox' => 'Ja/Nein (Abschnitt ein/aus)',
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(DocumentLayout::class, 'layout_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'template_id');
    }

    /** Briefbogen der Vorlage oder Standard-Briefbogen */
    public function resolvedLayout(): ?DocumentLayout
    {
        return $this->layout ?? DocumentLayout::defaultLayout();
    }

    /** Felder des Briefbogens (falls HTML) gefolgt von den Feldern der Vorlage; gleicher Schlüssel: Vorlage gewinnt */
    public function allFields(): array
    {
        $fields = [];

        if ($this->engine === 'html' && ($layout = $this->resolvedLayout())) {
            foreach ((array) $layout->fields as $f) {
                $fields[$f['key']] = $f + ['group' => 'Briefbogen'];
            }
        }

        foreach ((array) $this->fields as $f) {
            if (! empty($f['key'])) {
                $fields[$f['key']] = $f + ['group' => $this->name];
            }
        }

        return array_values($fields);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
