<?php

namespace Nfgarching\Documents\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nfgarching\Documents\Models\Document;
use Nfgarching\Documents\Models\DocumentTemplate;
use Nfgarching\Documents\Pdf\Gotenberg;
use Symfony\Component\HttpFoundation\Response;

class FileController
{
    /** Vorschau-PDF aus dem Cache (nur für den Benutzer, der sie erzeugt hat) */
    public function preview(string $key): Response
    {
        $item = Cache::get(self::cacheKey($key));

        abort_unless($item && $item['user'] === auth()->id(), 404, 'Die Vorschau ist abgelaufen.');

        return response($item['pdf'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Vorschau.pdf"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    public function document(Document $document, string $format = 'pdf'): Response
    {
        if ($gate = config('nfdocuments.gate_documents')) {
            Gate::authorize($gate, $document);
        }

        $path = $format === 'docx' ? $document->docx_path : $document->path;
        abort_unless($path && Storage::disk($document->disk)->exists($path), 404, 'Die Datei ist nicht mehr verfügbar.');

        $name = $document->fileName($format);

        return Storage::disk($document->disk)->response($path, $name, [
            'Content-Type' => $format === 'docx'
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                : 'application/pdf',
        ], request()->boolean('download') || $format === 'docx' ? 'attachment' : 'inline');
    }

    public function templateDocx(DocumentTemplate $template): Response
    {
        if ($gate = config('nfdocuments.gate')) {
            Gate::authorize($gate);
        }

        $disk = Storage::disk(config('nfdocuments.storage.disk', 'local'));
        abort_unless($template->docx_path && $disk->exists($template->docx_path), 404);

        return $disk->download($template->docx_path, Str::slug($template->name).'.docx');
    }

    public function paged(): Response
    {
        return response()->file(Gotenberg::pagedJs(), [
            'Content-Type'                 => 'text/javascript; charset=utf-8',
            'Cache-Control'                => 'public, max-age=604800',
            'Access-Control-Allow-Origin'  => '*',
        ]);
    }

    /** Legt ein Vorschau-PDF ab und liefert die URL */
    public static function storePreview(string $pdf): string
    {
        $key = Str::random(40);
        Cache::put(self::cacheKey($key), ['user' => auth()->id(), 'pdf' => $pdf], now()->addMinutes((int) config('nfdocuments.preview_minutes', 10)));

        return route('nfdocuments.preview', $key);
    }

    private static function cacheKey(string $key): string
    {
        return 'nfdocuments.preview.'.$key;
    }
}
