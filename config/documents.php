<?php

use Nfgarching\Documents\Data\GeneralProvider;
use Nfgarching\Documents\Data\KanzleiProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Gotenberg (PDF-Erzeugung)
    |--------------------------------------------------------------------------
    | Eigener Container neben der App (siehe docker/gotenberg.compose.yml).
    | HTML-Vorlagen rendert Chromium, Word-Vorlagen LibreOffice.
    | Prüfen mit: php artisan nfdocuments:check
    */
    'gotenberg' => [
        'url'     => env('NFDOCUMENTS_GOTENBERG_URL', 'http://gotenberg:3000'),
        'timeout' => (int) env('NFDOCUMENTS_GOTENBERG_TIMEOUT', 60),

        // Archivformat für erzeugte PDFs: null, 'PDF/A-1b', 'PDF/A-2b' oder 'PDF/A-3b'
        'pdfa'  => env('NFDOCUMENTS_PDFA'),
        'pdfua' => (bool) env('NFDOCUMENTS_PDFUA', false),   // barrierefrei (getaggt)
    ],

    // Ablage der erzeugten Dokumente (PDF, ausgefüllte DOCX, hochgeladene Vorlagen, Logos)
    'storage' => [
        'disk'      => env('NFDOCUMENTS_DISK', 'local'),
        'directory' => 'nfdocuments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Datenquellen für Platzhalter
    |--------------------------------------------------------------------------
    | Jede Klasse implementiert Nfgarching\Documents\Contracts\DataProvider.
    | Sie liefern Werte wie ${kanzlei.name}, ${heute} oder – mit eigener Quelle –
    | ${akte.az}, ${mandant.name}, ${empfaenger.anschrift}.
    | Spätere Quellen überschreiben frühere bei gleichem Schlüssel.
    */
    'providers' => [
        GeneralProvider::class,
        KanzleiProvider::class,
        // 'App\\Documents\\AkteDataProvider',   // php artisan vendor:publish --tag=nfdocuments-stubs
    ],

    // Stammdaten für ${kanzlei.*} – in Briefkopf und Fußzeile verwendbar
    'kanzlei' => [
        'name'     => env('NFDOCUMENTS_KANZLEI_NAME', 'Kanzlei Muster'),
        'zusatz'   => env('NFDOCUMENTS_KANZLEI_ZUSATZ', 'Rechtsanwälte · Steuerberater'),
        'strasse'  => env('NFDOCUMENTS_KANZLEI_STRASSE', 'Musterstraße 1'),
        'plz_ort'  => env('NFDOCUMENTS_KANZLEI_PLZ_ORT', '12345 Musterstadt'),
        'telefon'  => env('NFDOCUMENTS_KANZLEI_TELEFON', ''),
        'telefax'  => env('NFDOCUMENTS_KANZLEI_TELEFAX', ''),
        'email'    => env('NFDOCUMENTS_KANZLEI_EMAIL', ''),
        'web'      => env('NFDOCUMENTS_KANZLEI_WEB', ''),
        'bank'     => env('NFDOCUMENTS_KANZLEI_BANK', ''),
        'iban'     => env('NFDOCUMENTS_KANZLEI_IBAN', ''),
        'bic'      => env('NFDOCUMENTS_KANZLEI_BIC', ''),
        'ustid'    => env('NFDOCUMENTS_KANZLEI_USTID', ''),
        'steuernr' => env('NFDOCUMENTS_KANZLEI_STEUERNR', ''),
    ],

    'categories' => [
        'brief'        => 'Brief',
        'vereinbarung' => 'Vereinbarung',
        'vertrag'      => 'Vertrag',
        'sonstiges'    => 'Sonstiges',
    ],

    // Schriften zur Auswahl im Layout-Editor (CSS font-family). Im Gotenberg-Container
    // sind u. a. Liberation (metrisch wie Arial/Times), Carlito (wie Calibri) und Noto installiert.
    'fonts' => [
        'Arial'       => 'Arial, "Liberation Sans", Helvetica, sans-serif',
        'Calibri'     => 'Calibri, Carlito, "Liberation Sans", sans-serif',
        'Times'       => '"Times New Roman", "Liberation Serif", Times, serif',
        'Cambria'     => 'Cambria, Caladea, "Liberation Serif", serif',
        'Noto Sans'   => '"Noto Sans", "Liberation Sans", sans-serif',
        'Noto Serif'  => '"Noto Serif", "Liberation Serif", serif',
    ],

    // Blade-Layout der Editor-Seiten (wie beim Mailer)
    'layout' => 'layout::popup',

    // Optionaler Gate-Name für Layouts und Vorlagen, z. B. 'manage-templates' – sonst nur Login
    'gate' => null,

    // Optionaler Gate-Name für den Abruf erzeugter Dokumente; erhält das Document-Model
    'gate_documents' => null,

    // Hochgeladene Word-Vorlagen und Logos
    'uploads' => [
        'max_kb' => 10240,
    ],

    // Vorschau-PDFs im Cache (Minuten)
    'preview_minutes' => 10,

    'route_prefix' => 'nfdocuments',
];
