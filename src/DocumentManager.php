<?php

namespace Nfgarching\Documents;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nfgarching\Documents\Models\Document;
use Nfgarching\Documents\Models\DocumentLayout;
use Nfgarching\Documents\Models\DocumentTemplate;
use Nfgarching\Documents\Pdf\Gotenberg;
use Nfgarching\Documents\Rendering\DataResolver;
use Nfgarching\Documents\Rendering\DocxRenderer;
use Nfgarching\Documents\Rendering\HtmlRenderer;
use Nfgarching\Documents\Rendering\Placeholders;
use Nfgarching\Documents\Rendering\Values;
use RuntimeException;

/**
 * Einstieg für Apps und andere Packages:
 *
 *   $docs = app(DocumentManager::class);
 *   $doc  = $docs->generate($template, ['akte' => 123], ['betreff' => '…']);
 *   $pdf  = $docs->pdf($template, $context, $input);           // nur Bytes, ohne Speichern
 *   $pdf  = $docs->htmlToPdf('<p>…</p>', $layout, $values);    // freier Text auf Briefbogen
 */
class DocumentManager
{
    public function __construct(
        private DataResolver $data,
        private HtmlRenderer $html,
        private DocxRenderer $docx,
    ) {}

    public function data(): DataResolver
    {
        return $this->data;
    }

    public function gotenberg(): Gotenberg
    {
        return Gotenberg::fromConfig();
    }

    /** Eingabefelder mit Vorgabewerten für den Kontext */
    public function defaults(DocumentTemplate $template, array $context = []): array
    {
        return $this->data->defaults($template->allFields(), $context);
    }

    public function values(DocumentTemplate $template, array $context, array $input): Values
    {
        return $this->data->values($template->allFields(), $input, $context);
    }

    /** Felder, die Pflicht sind und leer blieben: [key => label] */
    public function missing(DocumentTemplate $template, array $input): array
    {
        return collect($template->allFields())
            ->filter(fn ($f) => ! empty($f['required']) && trim((string) ($input[$f['key']] ?? '')) === '')
            ->mapWithKeys(fn ($f) => [$f['key'] => $f['label'] ?? $f['key']])
            ->all();
    }

    /** Vollständige HTML-Seite (Vorschau im Browser oder für Gotenberg) */
    public function renderHtml(
        ?DocumentLayout $layout,
        string $body,
        Values $values,
        bool $preview = false,
        bool $guides = false,
        ?string $title = null,
    ): string {
        return $this->html->render($layout, $body, $values, [
            'mode'      => $preview ? 'preview' : 'final',
            'guides'    => $guides,
            'title'     => $title,
            'paged_src' => $preview ? route('nfdocuments.paged') : 'paged.polyfill.min.js',
        ]);
    }

    public function previewHtml(DocumentTemplate $template, array $context, array $input): string
    {
        return $this->renderHtml(
            $template->resolvedLayout(),
            (string) $template->body,
            $this->values($template, $context, $input),
            preview: true,
            title: $template->name,
        );
    }

    /** PDF-Bytes ohne Speichern (Vorschau) */
    public function pdf(DocumentTemplate $template, array $context, array $input, bool $draft = false): string
    {
        return $this->build($template, $this->values($template, $context, $input), $draft)['pdf'];
    }

    /** Freier HTML-Text auf einem Briefbogen, z. B. aus dem Textgenerator */
    public function htmlToPdf(string $body, ?DocumentLayout $layout = null, ?Values $values = null, ?string $title = null): string
    {
        $layout ??= DocumentLayout::defaultLayout();
        $values ??= $this->data->providerValues([]);

        return $this->gotenberg()->html(
            $this->renderHtml($layout, $body, $values, title: $title),
            ['Title' => $title, 'Creator' => config('nfdocuments.kanzlei.name')],
        );
    }

    /**
     * Dokument erzeugen und ablegen. Gleiche Vorlage im gleichen Kontext → neue Fassung.
     */
    public function generate(DocumentTemplate $template, array $context = [], array $input = [], ?string $title = null): Document
    {
        $values = $this->values($template, $context, $input);
        $built = $this->build($template, $values);

        $title = trim((string) ($title ?: Placeholders::renderText($template->name, $values))) ?: 'Dokument';
        $contextKey = Document::contextKey($context);
        $version = 1 + (int) Document::query()
            ->where('template_id', $template->id)
            ->where('context_key', $contextKey)
            ->where('title', $title)
            ->max('version');

        $diskName = config('nfdocuments.storage.disk', 'local');
        $disk = Storage::disk($diskName);
        $dir = trim(config('nfdocuments.storage.directory', 'nfdocuments'), '/').'/'.now()->format('Y/m');
        $base = $dir.'/'.now()->format('Ymd-His').'-'.Str::slug($title).'-'.Str::lower(Str::random(6));

        $disk->put($base.'.pdf', $built['pdf']);

        $docxPath = null;
        if ($built['docx']) {
            $docxPath = $base.'.docx';
            $disk->put($docxPath, file_get_contents($built['docx']));
            @unlink($built['docx']);
        }

        return Document::create([
            'template_id' => $template->id,
            'layout_id'   => $template->engine === 'html' ? $template->resolvedLayout()?->id : null,
            'user_id'     => auth()->id(),
            'title'       => $title,
            'engine'      => $template->engine,
            'context'     => $context ?: null,
            'context_key' => $contextKey,
            'data'        => $input,
            'html'        => $built['html'],
            'disk'        => $diskName,
            'path'        => $base.'.pdf',
            'docx_path'   => $docxPath,
            'sha256'      => hash('sha256', $built['pdf']),
            'size'        => strlen($built['pdf']),
            'version'     => $version,
        ]);
    }

    /** @return array{pdf: string, html: ?string, docx: ?string} */
    private function build(DocumentTemplate $template, Values $values, bool $draft = false): array
    {
        $meta = ['Title' => $template->name, 'Creator' => (string) config('nfdocuments.kanzlei.name')];

        if ($template->engine === 'docx') {
            $source = $this->templateFile($template);
            $filled = $this->docx->fill($source, $values, highlight: $draft);

            try {
                $pdf = $this->gotenberg()->docx($filled, $meta);
            } finally {
                @unlink($source);
                if ($draft) {
                    @unlink($filled);
                }
            }

            return ['pdf' => $pdf, 'html' => null, 'docx' => $draft ? null : $filled];
        }

        $html = $this->renderHtml($template->resolvedLayout(), (string) $template->body, $values, title: $template->name);

        return ['pdf' => $this->gotenberg()->html($html, $meta), 'html' => $html, 'docx' => null];
    }

    /** Word-Vorlage als lokale temporäre Datei (Disk kann auch S3/NAS sein) */
    public function templateFile(DocumentTemplate $template): string
    {
        $disk = Storage::disk(config('nfdocuments.storage.disk', 'local'));

        if (! $template->docx_path || ! $disk->exists($template->docx_path)) {
            throw new RuntimeException("Für die Vorlage „{$template->name}“ ist keine Word-Datei hinterlegt.");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'nfdoctpl');
        @unlink($tmp);
        $tmp .= '.docx';
        file_put_contents($tmp, $disk->get($template->docx_path));

        return $tmp;
    }

    /** Ausgefüllte Word-Datei ohne PDF (zum Weiterbearbeiten in Word) */
    public function filledDocx(DocumentTemplate $template, array $context, array $input): string
    {
        $source = $this->templateFile($template);

        try {
            return $this->docx->fill($source, $this->values($template, $context, $input));
        } finally {
            @unlink($source);
        }
    }

    /** Platzhalter einer Word-Vorlage */
    public function docxPlaceholders(DocumentTemplate $template): array
    {
        $source = $this->templateFile($template);

        try {
            return $this->docx->placeholders($source);
        } finally {
            @unlink($source);
        }
    }
}
