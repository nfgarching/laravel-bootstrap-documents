<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Briefbogen / Seitenlayout: Ränder, Schrift, Briefkopf, Anschriftfeld, Fußzeile
        Schema::create('nfdoc_layouts', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('form', 20)->default('din5008-b');   // din5008-b | din5008-a | plain
            $t->json('page')->nullable();                     // Ränder, Schrift, Textbeginn, Falzmarken …
            $t->text('header_html')->nullable();              // Briefkopf (nur Seite 1)
            $t->string('sender_line')->nullable();            // Rücksendeangabe über der Anschrift
            $t->text('info_html')->nullable();                // Informationsblock rechts (Az., Datum …)
            $t->text('follow_header_html')->nullable();       // Kopfzeile ab Seite 2
            $t->text('footer_html')->nullable();              // Fußzeile (jede Seite)
            $t->text('css')->nullable();                      // zusätzliches CSS
            $t->json('fields')->nullable();                   // Eingabefelder des Briefbogens (Anschrift, Datum …)
            $t->string('logo_path')->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        // Vorlage: HTML-Text mit Platzhaltern oder Word-Datei (.docx)
        Schema::create('nfdoc_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('category', 30)->default('brief');
            $t->string('engine', 10)->default('html');        // html | docx
            $t->foreignId('layout_id')->nullable()->index();  // null = Standard-Briefbogen
            $t->longText('body')->nullable();                 // engine html
            $t->string('docx_path')->nullable();              // engine docx
            $t->json('fields')->nullable();                   // Eingabefelder der Vorlage
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        // Erzeugtes Dokument (Instanz zur Akte), unveränderlich mit Hash
        Schema::create('nfdoc_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('template_id')->nullable()->index();
            $t->foreignId('layout_id')->nullable();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('title');
            $t->string('engine', 10);
            $t->json('context')->nullable();                  // z. B. {"akte": 123}
            $t->string('context_key')->nullable()->index();   // z. B. "akte:123"
            $t->json('data')->nullable();                     // Eingaben (Rohwerte)
            $t->longText('html')->nullable();                 // gerenderter Stand (engine html)
            $t->string('disk');
            $t->string('path');                               // PDF
            $t->string('docx_path')->nullable();              // ausgefüllte Word-Datei (engine docx)
            $t->char('sha256', 64);
            $t->unsignedBigInteger('size')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfdoc_documents');
        Schema::dropIfExists('nfdoc_templates');
        Schema::dropIfExists('nfdoc_layouts');
    }
};
