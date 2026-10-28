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
use Nfgarching\Documents\Rendering\Values;
use Nfgarching\Documents\Support\Starter;
use Throwable;

/**
 * Briefbogen bearbeiten: Briefkopf, Anschriftfeld, Informationsblock, Kopf-/Fußzeile,
 * Ränder und Schrift – mit Live-Vorschau (paged.js) und PDF-Probedruck (Gotenberg).
 */
class LayoutEditor extends Component
{
    use EditsFields;
    use WithFileUploads;

    #[Locked]
    public ?int $layoutId = null;

    public string $name = '';
    public string $form = 'din5008-b';
    public array $page = [];
    public string $header_html = '';
    public string $sender_line = '';
    public string $info_html = '';
    public string $follow_header_html = '';
    public string $footer_html = '';
    public string $css = '';
    public bool $is_default = false;

    public ?string $logo_path = null;
    public $logo = null;
    public bool $removeLogo = false;

    /** Hilfslinien (Anschriftfeld, Infoblock) in der Vorschau */
    public bool $guides = true;

    public string $tab = 'kopf';
    public ?string $notice = null;
    public ?string $saved = null;

    public function mount(?DocumentLayout $layout = null): void
    {
        $this->authorizeManage();

        if ($layout?->exists) {
            $this->layoutId = $layout->id;
            $this->fill($layout->only([
                'name', 'form', 'header_html', 'sender_line', 'info_html', 'follow_header_html', 'footer_html', 'css', 'logo_path', 'is_default',
            ]));
            foreach (['header_html', 'sender_line', 'info_html', 'follow_header_html', 'footer_html', 'css'] as $k) {
                $this->{$k} = (string) $this->{$k};
            }
            $this->page = $layout->settings();
            $this->fields = (array) $layout->fields;

            return;
        }

        // Neuer Briefbogen: Vorbelegung wie der mitgelieferte
        $starter = Starter::layouts()[0];
        $this->name = 'Neuer Briefbogen';
        $this->page = DocumentLayout::pageDefaults($this->form);
        $this->fields = $starter['fields'];
        $this->header_html = $starter['header_html'];
        $this->sender_line = $starter['sender_line'];
        $this->info_html = $starter['info_html'];
        $this->follow_header_html = $starter['follow_header_html'];
        $this->footer_html = $starter['footer_html'];
    }

    /** Neue Form: Maße der Form übernehmen, Schrift und Ränder behalten */
    public function updatedForm(): void
    {
        $keep = array_intersect_key($this->page, array_flip(['margin_right', 'margin_left', 'font', 'font_size', 'line_height', 'paragraph', 'color', 'accent', 'logo_height']));
        $this->page = array_merge(DocumentLayout::pageDefaults($this->form), $keep);

        if ($this->form !== 'plain' && ! collect($this->fields)->contains('key', 'anschrift')) {
            $this->fields = array_merge(DocumentLayout::letterFields(), $this->fields);
        }
    }

    public function resetGeometry(): void
    {
        $this->page = array_merge($this->page, array_intersect_key(
            DocumentLayout::pageDefaults($this->form),
            array_flip(['margin_top', 'margin_bottom', 'address_top', 'address_left', 'info_top', 'info_left', 'text_start', 'folds']),
        ));
    }

    public function updatedLogo(): void
    {
        $this->validate(['logo' => ['image', 'mimes:png,jpg,jpeg,svg,webp', 'max:'.(int) config('nfdocuments.uploads.max_kb', 10240)]]);
        $this->removeLogo = false;
    }

    /** Ungespeicherter Stand als Model (für Vorschau und Speichern) */
    protected function draft(): DocumentLayout
    {
        $layout = new DocumentLayout([
            'name'               => $this->name,
            'form'               => $this->form,
            'page'               => $this->page,
            'header_html'        => $this->header_html,
            'sender_line'        => $this->sender_line,
            'info_html'          => $this->info_html,
            'follow_header_html' => $this->follow_header_html,
            'footer_html'        => $this->footer_html,
            'css'                => $this->css,
            'fields'             => $this->cleanFields(),
            'logo_path'          => $this->removeLogo ? null : $this->logo_path,
        ]);

        if ($this->logo && ! $this->removeLogo) {
            try {
                $layout->previewLogo = 'data:'.$this->logo->getMimeType().';base64,'.base64_encode($this->logo->get());
            } catch (Throwable) {
            }
        }

        return $layout;
    }

    #[Computed]
    public function previewHtml(): string
    {
        $docs = app(DocumentManager::class);
        $layout = $this->draft();
        $fields = (array) $layout->fields;
        $values = $docs->data()->values($fields, $docs->data()->sample($fields), [])
            ->merge(new Values(['akte.az' => '26/0001', 'akte.bezeichnung' => 'Muster ./. Beispiel']));

        return $docs->renderHtml($layout, $this->sampleBody(), $values, preview: true, guides: $this->guides, title: $this->name);
    }

    protected function sampleBody(): string
    {
        $p = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. '
            .'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. '
            .'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>';

        return $this->form === 'plain'
            ? '<h1>Vereinbarung</h1><h2>§ 1 Gegenstand</h2>'.str_repeat($p, 4).'<h2>§ 2 Vergütung</h2>'.str_repeat($p, 6).'<h2>§ 3 Schlussbestimmungen</h2>'.str_repeat($p, 5)
            : '<p><strong>Betreff des Schreibens</strong></p><p>Sehr geehrte Damen und Herren,</p>'.str_repeat($p, 9).'<p>Mit freundlichen Grüßen</p>';
    }

    public function pdfTest(): void
    {
        $this->notice = null;
        $docs = app(DocumentManager::class);
        $layout = $this->draft();
        $fields = (array) $layout->fields;
        $values = $docs->data()->values($fields, $docs->data()->sample($fields), []);

        try {
            $pdf = $docs->gotenberg()->html($docs->renderHtml($layout, $this->sampleBody(), $values, title: $this->name));
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
            'name' => ['required', 'string', 'max:120'],
            'form' => ['required', 'in:'.implode(',', array_keys(DocumentLayout::FORMS))],
            'page.margin_top' => ['required', 'numeric', 'between:0,80'],
            'page.margin_right' => ['required', 'numeric', 'between:0,60'],
            'page.margin_bottom' => ['required', 'numeric', 'between:0,80'],
            'page.margin_left' => ['required', 'numeric', 'between:0,60'],
            'page.font_size' => ['required', 'numeric', 'between:6,20'],
        ] + $this->fieldRules(), $this->fieldMessages());

        $layout = $this->layoutId ? DocumentLayout::findOrFail($this->layoutId) : new DocumentLayout;
        $disk = Storage::disk(config('nfdocuments.storage.disk', 'local'));

        if (($this->removeLogo || $this->logo) && $layout->logo_path) {
            $disk->delete($layout->logo_path);
            $this->logo_path = null;
        }

        if ($this->logo && ! $this->removeLogo) {
            $this->logo_path = $this->logo->storeAs(
                trim(config('nfdocuments.storage.directory', 'nfdocuments'), '/').'/logos',
                Str::slug($this->name).'-'.Str::lower(Str::random(6)).'.'.$this->logo->getClientOriginalExtension(),
                ['disk' => config('nfdocuments.storage.disk', 'local')],
            );
            $this->logo = null;
        }

        $layout->fill([
            'name'               => $this->name,
            'slug'               => $layout->slug ?: $this->uniqueSlug(DocumentLayout::class, $this->name, $this->layoutId),
            'form'               => $this->form,
            'page'               => $this->page,
            'header_html'        => $this->header_html,
            'sender_line'        => $this->sender_line,
            'info_html'          => $this->info_html,
            'follow_header_html' => $this->follow_header_html,
            'footer_html'        => $this->footer_html,
            'css'                => $this->css,
            'fields'             => $this->cleanFields(),
            'logo_path'          => $this->logo_path,
        ])->save();

        if ($this->is_default || ! DocumentLayout::query()->where('is_default', true)->exists()) {
            $layout->makeDefault();
            $this->is_default = true;
        }

        $this->removeLogo = false;
        $this->saved = 'Gespeichert um '.now()->format('H:i:s');

        if (! $this->layoutId) {
            $this->redirectRoute('nfdocuments.layouts.edit', $layout->id, navigate: true);
        }
    }

    public function placeholders(): array
    {
        return app(DocumentManager::class)->data()->placeholders()
            + collect($this->cleanFields())->mapWithKeys(fn ($f) => [$f['key'] => 'Feld: '.$f['label']])->all()
            + ['logo' => 'Logo des Briefbogens'];
    }

    public function render()
    {
        return view('nfdocuments::livewire.layout-editor', [
            'forms'        => DocumentLayout::FORMS,
            'fonts'        => array_keys((array) config('nfdocuments.fonts', [])),
            'types'        => \Nfgarching\Documents\Models\DocumentTemplate::FIELD_TYPES,
            'placeholders' => $this->placeholders(),
        ])->layout(config('nfdocuments.layout', 'layout::popup'));
    }
}
