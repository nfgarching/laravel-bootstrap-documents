<?php

namespace Nfgarching\Documents\Pdf;

use Gotenberg\Gotenberg as Api;
use Gotenberg\Modules\ChromiumPdf;
use Gotenberg\Modules\LibreOffice;
use Gotenberg\Stream;
use GuzzleHttp\Client;
use RuntimeException;
use Throwable;

/**
 * Anbindung an den Gotenberg-Container.
 * HTML → PDF über Chromium (mit paged.js), DOCX → PDF über LibreOffice.
 */
class Gotenberg
{
    public function __construct(
        private string $url,
        private int $timeout = 60,
        private ?string $pdfa = null,
        private bool $pdfua = false,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('nfdocuments.gotenberg.url'), '/'),
            (int) config('nfdocuments.gotenberg.timeout', 60),
            config('nfdocuments.gotenberg.pdfa') ?: null,
            (bool) config('nfdocuments.gotenberg.pdfua', false),
        );
    }

    /** HTML-Seite (aus HtmlRenderer) als PDF */
    public function html(string $html, array $metadata = []): string
    {
        $req = Api::chromium($this->url)->pdf()
            ->paperSize('210mm', '297mm')
            ->margins(0, 0, 0, 0)            // Ränder setzt paged.js über @page
            ->preferCssPageSize()
            ->printBackground()
            ->emulatePrintMediaType()
            ->waitForExpression('window.nfdocReady === true')
            ->failOnConsoleExceptions()
            ->assets(Stream::path(self::pagedJs(), 'paged.polyfill.min.js'));

        $req = $this->archive($req, $metadata);

        return $this->send($req->html(Stream::string('index.html', $html)));
    }

    /** Word-Datei als PDF */
    public function docx(string $path, array $metadata = []): string
    {
        $req = $this->archive(Api::libreOffice($this->url), $metadata);

        return $this->send($req->convert(Stream::path($path, 'dokument.docx')));
    }

    /** Erreichbarkeit: GET /health */
    public function health(): array
    {
        try {
            $res = $this->client(5)->get($this->url.'/health');

            return json_decode((string) $res->getBody(), true) ?: ['status' => 'unknown'];
        } catch (Throwable $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    public static function pagedJs(): string
    {
        return dirname(__DIR__, 2).'/resources/js/paged.polyfill.min.js';
    }

    private function archive(ChromiumPdf|LibreOffice $req, array $metadata): ChromiumPdf|LibreOffice
    {
        if ($this->pdfa) {
            $req = $req->pdfa($this->pdfa);
        }

        if ($this->pdfua) {
            $req = $req->pdfua();
        }

        if ($metadata = array_filter($metadata)) {
            $req = $req->metadata($metadata);
        }

        return $req;
    }

    private function send($request): string
    {
        try {
            return (string) Api::send($request, $this->client($this->timeout))->getBody();
        } catch (Throwable $e) {
            throw new RuntimeException('PDF-Erzeugung fehlgeschlagen (Gotenberg '.$this->url.'): '.$e->getMessage(), 0, $e);
        }
    }

    private function client(int $timeout): Client
    {
        return new Client(['timeout' => $timeout, 'connect_timeout' => 5, 'http_errors' => false]);
    }
}
