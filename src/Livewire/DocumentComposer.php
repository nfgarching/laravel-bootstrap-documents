<?php

namespace Nfgarching\Documents\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Nfgarching\Documents\DocumentManager;
use Nfgarching\Documents\Http\Controllers\FileController;
use Nfgarching\Documents\Models\Document;
use Nfgarching\Documents\Models\DocumentTemplate;
use Throwable;

/**
 * Dokument aus einer Vorlage erstellen: Vorlage wählen, Felder ausfüllen,
 * Live-Vorschau, PDF erzeugen und ablegen, optional per E-Mail versenden.
 *
 * Öffnen:   Livewire.dispatch('nfdocuments-open', { options: { context: { akte: 123 }, template: 'anschreiben' } })
 *           $this->dispatch('nfdocuments-open', options: [...])
 * Seite:    route('nfdocuments.compose', ['akte' => 123, 'template' => 'anschreiben'])
 * Danach:   Event 'nfdocuments-created' mit { id } (ID des Dokuments)
 */
class DocumentComposer extends Component
{
    #[Locked]
    public array $context = [];

    #[Locked]
    public ?int $templateId = null;

    /** Eingaben je Feld; Punkte im Schlüssel als „__“ (Livewire liest Punkte als Verschachtelung) */
    public array $input = [];

    public string $title = '';
    public string $search = '';
    public string $category = '';

    public ?int $documentId = null;
    public ?string $notice = null;
    public array $missing = [];

    /** Eigenständige Seite statt Modal */
    #[Locked]
    public bool $page = false;

    public function mount(): void
    {
        if (request()->routeIs('nfdocuments.compose')) {
            $this->page = true;
            $q = request()->query();
            $this->open([
                'context'  => (array) ($q['context'] ?? array_diff_key($q, array_flip(['template', 'values', 'title']))),
                'template' => $q['template'] ?? null,
                'values'   => (array) ($q['values'] ?? []),
                'title'    => $q['title'] ?? null,
            ], show: false);
        }
    }

    #[On('nfdocuments-open')]
    public function open(array $options = [], bool $show = true): void
    {
        $this->reset(['templateId', 'input', 'title', 'search', 'category', 'documentId', 'notice', 'missing']);
        $this->context = (array) ($options['context'] ?? []);

        if ($tpl = $options['template'] ?? null) {
            $template = DocumentTemplate::query()->active()
                ->where(is_numeric($tpl) ? 'id' : 'slug', $tpl)
                ->first();

            if ($template) {
                $this->choose($template->id, (array) ($options['values'] ?? []), $options['title'] ?? null);
            }
        }

        if ($show) {
            $this->dispatch('nfdocuments-show');
        }
    }

    public function choose(int $id, array $values = [], ?string $title = null): void
    {
        $template = DocumentTemplate::query()->active()->findOrFail($id);
        $docs = app(DocumentManager::class);

        $this->templateId = $template->id;
        $this->input = static::toWire(array_replace(
            $docs->defaults($template, $this->context),
            array_intersect_key($values, array_flip(array_column($template->allFields(), 'key'))),
        ));
        $this->title = $title ?: $template->name;
        $this->documentId = null;
        $this->notice = null;
        $this->missing = [];
    }

    /** Eingaben mit echten Schlüsseln */
    public function values(): array
    {
        $out = [];

        foreach ($this->input as $k => $v) {
            $out[str_replace('__', '.', $k)] = $v;
        }

        return $out;
    }

    public static function toWire(array $values): array
    {
        $out = [];

        foreach ($values as $k => $v) {
            $out[str_replace('.', '__', $k)] = $v;
        }

        return $out;
    }

    public static function wireKey(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function back(): void
    {
        $this->reset(['templateId', 'input', 'title', 'documentId', 'notice', 'missing']);
    }

    #[Computed]
    public function chosen(): ?DocumentTemplate
    {
        return $this->templateId ? DocumentTemplate::with('layout')->find($this->templateId) : null;
    }

    #[Computed]
    public function templates()
    {
        return DocumentTemplate::query()->active()
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('sort')->orderBy('name')
            ->get(['id', 'name', 'category', 'engine', 'description'])
            ->groupBy('category');
    }

    /** Frühere Dokumente aus derselben Vorlage im selben Kontext */
    #[Computed]
    public function history()
    {
        if (! $this->templateId || ! $this->context) {
            return collect();
        }

        return Document::query()->forContext($this->context)
            ->where('template_id', $this->templateId)
            ->latest()->limit(5)->get(['id', 'title', 'version', 'created_at', 'engine']);
    }

    #[Computed]
    public function previewHtml(): ?string
    {
        $t = $this->chosen();

        if (! $t || $t->engine !== 'html') {
            return null;
        }

        return app(DocumentManager::class)->previewHtml($t, $this->context, $this->values());
    }

    public function pdfPreview(): void
    {
        $this->notice = null;

        try {
            $pdf = app(DocumentManager::class)->pdf($this->chosen(), $this->context, $this->values(), draft: true);
            $this->js('window.open('.json_encode(FileController::storePreview($pdf)).', "_blank")');
        } catch (Throwable $e) {
            $this->notice = $e->getMessage();
        }
    }

    public function generate(): void
    {
        $this->notice = null;
        $t = $this->chosen();
        $docs = app(DocumentManager::class);

        if (! $t) {
            return;
        }

        $this->missing = $docs->missing($t, $this->values());

        if ($this->missing) {
            $this->notice = 'Bitte noch ausfüllen: '.implode(', ', $this->missing);

            return;
        }

        try {
            $doc = $docs->generate($t, $this->context, $this->values(), $this->title);
        } catch (Throwable $e) {
            report($e);
            $this->notice = $e->getMessage();

            return;
        }

        $this->documentId = $doc->id;
        unset($this->history);
        $this->dispatch('nfdocuments-created', id: $doc->id);
    }

    #[Computed]
    public function document(): ?Document
    {
        return $this->documentId ? Document::find($this->documentId) : null;
    }

    public function mailerAvailable(): bool
    {
        return class_exists(\Nfgarching\Mailer\MailerServiceProvider::class);
    }

    /** Übergabe an nfgarching/mailer (Pfad muss in nfmailer.attachments erlaubt sein) */
    public function sendByMail(): void
    {
        $doc = $this->document();

        if (! $doc || ! $this->mailerAvailable()) {
            return;
        }

        $this->dispatch('nfdocuments-hide');
        $this->dispatch('nfmailer-open', options: [
            'context'     => $this->context,
            'subject'     => $doc->title,
            'attachments' => [$doc->mailAttachment()],
        ]);
    }

    public function render()
    {
        $view = view('nfdocuments::livewire.document-composer', [
            'categories' => (array) config('nfdocuments.categories'),
        ]);

        return $this->page ? $view->layout(config('nfdocuments.layout', 'layout::popup')) : $view;
    }
}
