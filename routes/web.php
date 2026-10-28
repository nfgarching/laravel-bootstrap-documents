<?php

use Illuminate\Support\Facades\Route;
use Nfgarching\Documents\Http\Controllers\FileController;
use Nfgarching\Documents\Livewire\DocumentComposer;
use Nfgarching\Documents\Livewire\LayoutEditor;
use Nfgarching\Documents\Livewire\Library;
use Nfgarching\Documents\Livewire\TemplateEditor;

$prefix = config('nfdocuments.route_prefix', 'nfdocuments');

Route::middleware(['web', 'auth'])
    ->prefix($prefix)
    ->name('nfdocuments.')
    ->group(function () {
        // Verwaltung: Vorlagen, Briefbögen, erzeugte Dokumente
        Route::get('/', Library::class)->name('index');

        Route::get('layouts/create', LayoutEditor::class)->name('layouts.create');
        Route::get('layouts/{layout}', LayoutEditor::class)->whereNumber('layout')->name('layouts.edit');

        Route::get('templates/create', TemplateEditor::class)->name('templates.create');
        Route::get('templates/{template}', TemplateEditor::class)->whereNumber('template')->name('templates.edit');
        Route::get('templates/{template}/docx', [FileController::class, 'templateDocx'])->whereNumber('template')->name('templates.docx');

        // Dokument erstellen als eigene Seite (Popup/Tab); sonst Modal über 'nfdocuments-open'
        Route::get('compose', DocumentComposer::class)->name('compose');

        Route::get('preview/{key}', [FileController::class, 'preview'])->where('key', '[A-Za-z0-9]{40}')->name('preview');
        Route::get('documents/{document}/{format?}', [FileController::class, 'document'])
            ->whereNumber('document')->whereIn('format', ['pdf', 'docx'])->name('document');
    });

// paged.js für die Live-Vorschau (statische Bibliothek, MIT-Lizenz). Ohne Login, weil die
// Vorschau in einem abgeschotteten iframe (sandbox) läuft, das keine Cookies mitsendet.
Route::get($prefix.'/assets/paged.polyfill.min.js', [FileController::class, 'paged'])->name('nfdocuments.paged');
