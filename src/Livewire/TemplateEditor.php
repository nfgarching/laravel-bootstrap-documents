<?php

namespace Nfgarching\Documents\Livewire;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Nfgarching\Documents\DocumentManager;
use Nfgarching\Documents\Http\Controllers\FileController;
use Nfgarching\Documents\Livewire\Concerns\EditsFields;
use Nfgarching\Documents\Models\DocumentLayout;
use Nfgarching\Documents\Models\DocumentTemplate;
use Nfgarching\Documents\Rendering\DocxRenderer;
use Nfgarching\Documents\Rendering\Placeholders;
use Throwable;

/**
 * Vorlage bearbeiten: Text im Editor (HTML) oder Word-Datei hochladen,
 * Eingabefelder festlegen, Vorschau mit Beispielwerten.
 */
class TemplateEditor extends Component
{
    use EditsFields;
    use WithFileUploads;

    #[Locked]
    public ?int $templateId = null;

    public string $name = '';
    public string $category = 'brief';
    public string $engine = 'html';
    public ?int $layout_id = null;
    public string $description = '';
    public string $body = '';
    public bool $is_active = true;
    public int $sort = 0;

    public ?string $docx_path = null;
    public $docx = null;

    /** In der Word-Datei gefundene Platzhalter */
    public array $docxKeys = [];

    public string $tab = 'text';
    public ?string $notice = null;
    public ?string $saved = null;

    public function mount(?DocumentTemplate $template = null): void
    {
        $this->authorizeManage();

        if ($template?->exists) {
            $this->templateId = $template->id;
            $this->fill($template->only(['name', 'category', 'engine', 'layout_id', 'is_active', 'sort', 'docx_path']));
            $this->description = (string) $template->description;
            $this->body = (string) $template->body;
            $this->fields = (array) $template->fields;
            $this->tab = $this->engine === 'docx' ? 'word' : 'text';

            if ($this->engine === 'docx' && $this->docx_path) {
                try {
                    $this->docxKeys = app(DocumentManager::class)->docxPlaceholders($template);
                } catch (Throwable $e) {
                    $this->notice = $e->getMessage();
                }
            }

            return;
        }

        $this->name = 'Neue Vorlage';
        $this->category = (string) request()->query('category', 'brief');
        $this->engine = request()->query('engine') === 'docx' ? 'docx' : 'html';
        $this->tab = $this->engine === 'docx' ? 'word' : 'text';
        $this->body = '<p><strong>${betreff}</strong></p><p>Sehr geehrte Damen und Herren,</p><p></p><p>Mit freundlichen Grüßen</p>';
        $this->fields = [['key' => 'betreff', 'label' => 'Betreff', 'type' => 'text', 'default' => '', 'required' => true, 'options' => '', 'help' => '']];
    }

    public function updatedEngine(): void
    {
        $this->tab = $this->engine === 'docx' ? 'word' : 'text';
    }

    public function updatedDocx(): void
    {
        $this->notice = null;
        $this->validate(['docx' => ['file', 'mimes:docx', 'max:'.(int) config('nfdocuments.uploads.max_kb', 10240)]]);

        try {
            $this->docxKeys = app(DocxRenderer::class)->placeholders($this->docx->getRealPath());
        } catch (Throwable $e) {
            $this->docx = null;
            $this->notice = 'Die Word-Datei konnte nicht gelesen werden: '.$e->getMessage();
        }
    }

    /** Platzhalter im Text bzw. in der Word-Datei */
    #[Computed]
    public function usedKeys(): array
    {
        $keys = $this->engine === 'docx'
            ? $this->docxKeys
            : array_merge(Placeholders::names($this->body), Placeholders::blocks($this->body));

        return array_values(array_unique($keys));
    }

    /** Verwendete Platzhalter ohne Datenquelle und ohne Eingabefeld */
    #[Computed]
    public function unknownKeys(): array
    {
        $known = array_keys($this->placeholders());

        return array_values(array_filter($this->usedKeys(), fn ($k) => ! in_array($k, $known, true)));
    }

    /** Für jeden unbekannten Platzhalter ein Eingabefeld anlegen */
    public function addMissingFields(): void
    {
        foreach ($this->unknownKeys() as $key) {
            $this->addField($key);
        }
    }

    protected function draft(): DocumentTemplate
    {
        $t = new DocumentTemplate([
            'name'      => $this->name,
            'category'  => $this->category,
            'engine'    => $this->engine,
            'layout_id' => $this->layout_id,
            'body'      => $this->body,
            'fields'    => $this->cleanFields(),
            'docx_path' => $this->docx_path,
        ]);
        $t->id = $this->templateId;

        return $t;
    }

    #[Computed]
    public function previewHtml(): ?string
    {
        if ($this->engine !== 'html') {
            return null;
        }

        $docs = app(DocumentManager::class);
        $t = $this->draft();
        $fields = $t->allFields();
        $values = $docs->data()->values($fields, $docs->data()->sample($fields), []);

        return $docs->renderHtml($t->resolvedLayout(), $this->body, $values, preview: true, title: $this->name);
    }

    public function pdfTest(): void
    {
        $this->notice = null;
        $docs = app(DocumentManager::class);
        $t = $this->draft();

        try {
            if ($this->engine === 'docx' && $this->docx) {
                // noch nicht gespeicherte Word-Datei direkt verwenden
                $fields = $t->allFields();
                $values = $docs->data()->values($fields, $docs->data()->sample($fields), []);
                $filled = app(DocxRenderer::class)->fill($this->docx->getRealPath(), $values, highlight: true);
                $pdf = $docs->gotenberg()->docx($filled);
                @unlink($filled);
            } else {
                $pdf = $docs->pdf($t, [], $docs->data()->sample($t->allFields()), draft: true);
            }

            $this->js('window.open('.json_encode(FileController::storePreview($pdf)).', "_blank")');
        } catch (Throwable $e) {
            $this->notice = $e->getMessage();
        }
    }

    public function save(): void
    {
        $this->authorizeManage();
        $this->notice = null;

        $this->validate([
            'name'      => ['required', 'string', 'max:150'],
            'category'  => ['required', 'in:'.implode(',', array_keys((array) config('nfdocuments.categories')))],
            'engine'    => ['required', 'in:html,docx'],
            'layout_id' => ['nullable', 'integer'],
        ] + $this->fieldRules(), $this->fieldMessages());

        if ($this->engine === 'docx' && ! $this->docx && ! $this->docx_path) {
            $this->addError('docx', 'Bitte eine Word-Datei (.docx) hochladen.');

            return;
        }

        $t = $this->templateId ? DocumentTemplate::findOrFail($this->templateId) : new DocumentTemplate;
        $diskName = config('nfdocuments.storage.disk', 'local');

        if ($this->docx) {
            if ($t->docx_path) {
                Storage::disk($diskName)->delete($t->docx_path);
            }

            $this->docx_path = $this->docx->storeAs(
                trim(config('nfdocuments.storage.directory', 'nfdocuments'), '/').'/templates',
                Str::slug($this->name).'-'.Str::lower(Str::random(6)).'.docx',
                ['disk' => $diskName],
            );
            $this->docx = null;
        }

        $t->fill([
            'name'        => $this->name,
            'slug'        => $t->slug ?: $this->uniqueSlug(DocumentTemplate::class, $this->name, $this->templateId),
            'category'    => $this->category,
            'engine'      => $this->engine,
            'layout_id'   => $this->layout_id ?: null,
            'description' => $this->description ?: null,
            'body'        => $this->engine === 'html' ? $this->body : null,
            'docx_path'   => $this->docx_path,
            'fields'      => $this->cleanFields(),
            'is_active'   => $this->is_active,
            'sort'        => $this->sort,
        ])->save();

        $this->saved = 'Gespeichert um '.now()->format('H:i:s');

        if (! $this->templateId) {
            $this->redirectRoute('nfdocuments.templates.edit', $t->id, navigate: true);
        }
    }

    public function placeholders(): array
    {
        $out = app(DocumentManager::class)->data()->placeholders();

        foreach ($this->draft()->allFields() as $f) {
            $out[$f['key']] = 'Feld: '.($f['label'] ?? $f['key']);
        }

        return $out;
    }

    public function render()
    {
        return view('nfdocuments::livewire.template-editor', [
            'layouts'      => DocumentLayout::query()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'form', 'is_default']),
            'categories'   => (array) config('nfdocuments.categories'),
            'types'        => DocumentTemplate::FIELD_TYPES,
            'placeholders' => $this->placeholders(),
        ])->layout(config('nfdocuments.layout', 'layout::popup'));
    }
}
