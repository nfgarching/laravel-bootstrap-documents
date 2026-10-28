# nfgarching/documents

PDF-Dokumente aus bearbeitbaren Vorlagen – Briefbogen nach DIN 5008, Vereinbarungen, Verträge
(Livewire 4, Bootstrap 5.3, PDF über [Gotenberg](https://gotenberg.dev)).

- **Drei Ebenen**: *Briefbogen* (Ränder, Schrift, Briefkopf, Anschriftfeld, Infoblock, Fußzeile) →
  *Vorlage* (Text mit Platzhaltern oder Word-Datei, Eingabefelder) → *Dokument* (ausgefüllt, als PDF abgelegt)
- **Zwei Arten von Vorlagen**
  - **Text-Editor** (HTML, WYSIWYG mit Quelltext-Ansicht) auf einem Briefbogen – mit **Live-Vorschau** samt Seitenumbruch
  - **Word-Datei** (.docx) – Layout komplett in Word gestalten, Platzhalter einsetzen, hochladen
- **Platzhalter** `${mandant.name}`, **Abschnitte** `${#verfall}…${/verfall}` – gleiche Schreibweise in beiden Arten
- **Datenquellen** liefern Werte aus der Akte, den Kanzleidaten, dem Benutzer
- **Vorschau = Druck**: Der Seitenumbruch läuft im Browser und in Gotenberg mit derselben Bibliothek (paged.js)
- **Abgelegte Dokumente** mit SHA-256 und Fassungsnummer; Übergabe an `nfgarching/mailer` per Knopfdruck
- Optional **PDF/A** (Archiv) und **PDF/UA** (barrierefrei)

---

## 1. Installation

`composer.json` der App:

```json
"repositories": [
    { "type": "path", "url": "packages/nfgarching/documents" }
],
"require": {
    "nfgarching/documents": "*"
}
```

```bash
composer update nfgarching/documents
php artisan vendor:publish --tag=nfdocuments-config
php artisan migrate
php artisan nfdocuments:install        # Briefbogen, Vertragslayout, drei Beispielvorlagen
```

**Gotenberg** als zweiten Container neben der App starten – Vorlage in
[`docker/gotenberg.compose.yml`](docker/gotenberg.compose.yml). Auf dem QNAP: in die
`docker-compose.yml` der App übernehmen, damit beide im selben Netz laufen.

`.env`:

```env
NFDOCUMENTS_GOTENBERG_URL=http://gotenberg:3000
NFDOCUMENTS_PDFA=PDF/A-2b        # optional, für die Archivierung

NFDOCUMENTS_KANZLEI_NAME="Kanzlei Muster"
NFDOCUMENTS_KANZLEI_ZUSATZ="Rechtsanwälte · Steuerberater"
NFDOCUMENTS_KANZLEI_STRASSE="Musterstraße 1"
NFDOCUMENTS_KANZLEI_PLZ_ORT="12345 Musterstadt"
NFDOCUMENTS_KANZLEI_TELEFON="089 123456-0"
NFDOCUMENTS_KANZLEI_EMAIL=kanzlei@example.de
NFDOCUMENTS_KANZLEI_WEB=www.example.de
NFDOCUMENTS_KANZLEI_BANK=Musterbank
NFDOCUMENTS_KANZLEI_IBAN="DE12 3456 7890 1234 5678 90"
NFDOCUMENTS_KANZLEI_BIC=MUSTDEMMXXX
NFDOCUMENTS_KANZLEI_USTID=DE123456789
```

Prüfen:

```bash
php artisan nfdocuments:check --save=storage/app/probe.pdf
```

Wie beim Mailer muss `window.bootstrap` global verfügbar sein (das Modal wird per `bootstrap.Modal` geöffnet).
Der Text-Editor ([Jodit](https://xdsoft.net/jodit/), MIT) wird in den Editor-Seiten von jsDelivr geladen.

## 2. Oberfläche

| Seite | Route | Zweck |
|---|---|---|
| Übersicht | `nfdocuments.index` | Vorlagen, Briefbögen, erzeugte Dokumente |
| Briefbogen | `nfdocuments.layouts.edit` | Briefkopf, Logo, Anschriftfeld, Infoblock, Fußzeile, Folgeseiten, Ränder, Schrift – mit Hilfslinien |
| Vorlage | `nfdocuments.templates.edit` | Text bzw. Word-Datei, Eingabefelder, Vorschau mit Beispielwerten |
| Erstellen | Modal `nf-document-composer` bzw. `nfdocuments.compose` | Vorlage wählen, ausfüllen, Vorschau, erzeugen |

Mit dem core-Package erscheinen ein Navbar-Menü und das Modal automatisch. Sonst einmal im Layout:

```blade
<livewire:nf-document-composer />
```

### Dokument aus der Akte erstellen

```blade
<button type="button" class="btn btn-outline-primary"
        x-on:click="Livewire.dispatch('nfdocuments-open', { options: @js([
            'context'  => ['akte' => $akte->id],
            'template' => 'anschreiben',            // optional: Slug oder ID, sonst Auswahl
        ]) })">
    Schreiben erstellen
</button>
```

Aus einer Livewire-Komponente (z. B. Textgenerator):

```php
$this->dispatch('nfdocuments-open', options: [
    'context'  => ['akte' => $this->akte->id, 'empfaenger' => $beteiligter->id],
    'template' => 'anschreiben',
    'values'   => ['betreff' => 'Ihre Mietsache', 'text' => $text],   // Felder vorbelegen
    'title'    => 'Schreiben an Gegner',
]);
```

Nach dem Erzeugen feuert `nfdocuments-created` mit der Dokument-ID.

Als eigene Seite (Popup/Tab): `route('nfdocuments.compose', ['akte' => 123, 'template' => 'anschreiben'])`.

### Ohne Oberfläche

```php
use Nfgarching\Documents\DocumentManager;
use Nfgarching\Documents\Models\DocumentTemplate;

$docs = app(DocumentManager::class);
$tpl  = DocumentTemplate::firstWhere('slug', 'ratenzahlungsvereinbarung');

$doc  = $docs->generate($tpl, ['akte' => 123], ['forderung' => '5400', 'rate' => '300']);
$doc->path;           // storage: nfdocuments/2026/10/….pdf
$doc->sha256;

$pdf  = $docs->pdf($tpl, $context, $input);                          // nur Bytes
$pdf  = $docs->htmlToPdf('<p>Freier Text …</p>', title: 'Notiz');     // auf dem Standard-Briefbogen
```

## 3. Platzhalter und Abschnitte

| Schreibweise | Wirkung |
|---|---|
| `${mandant.name}` | Wert; Zeilenumbrüche bleiben erhalten |
| `${#verfall}…${/verfall}` | Abschnitt nur, wenn der Wert vorhanden und nicht „nein/0“ ist (Checkbox angehakt) |
| `${^verfall}…${/verfall}` | Abschnitt nur, wenn der Wert fehlt bzw. „nein“ ist |
| `${#akte.az}Az. ${akte.az}${/akte.az}` | Wert darf im eigenen Abschnitt vorkommen |
| `${logo}` | Logo des Briefbogens |
| `${seite}`, `${seiten}` | Seitenzahlen – nur in Kopf- und Fußzeile des Briefbogens |

Steht eine Abschnittsmarke allein in einem Absatz, verschwindet der Absatz mit. In **Word** müssen
Abschnittsmarken immer in einem eigenen Absatz stehen.

Werte kommen aus

1. **Datenquellen** (`config('nfdocuments.providers')`):
   - `GeneralProvider`: `${heute}`, `${heute_lang}`, `${heute_iso}`, `${bearbeiter.name}`, `${bearbeiter.email}`
   - `KanzleiProvider`: `${kanzlei.name}`, `${kanzlei.strasse}`, `${kanzlei.iban}` … und `${kanzlei.absender}`
   - eigene Quellen, z. B. die Akte (siehe 5.)
2. **Eingabefeldern** des Briefbogens und der Vorlage. Typen: Text, mehrzeilig, Datum, Betrag (€),
   Zahl, Auswahl, Ja/Nein. Die **Vorgabe** darf Platzhalter enthalten, z. B. `${empfaenger.anschrift}`.
   Ein Feld mit dem Schlüssel einer Datenquelle (z. B. `mandant.name`) wird mit deren Wert vorbelegt
   und bleibt änderbar. Eingaben gehen Werten der Datenquellen vor.

Formatierung: Datum `28.10.2026` (Feld-Option `format: long` → „28. Oktober 2026“), Betrag `1.234,50 €`.

Der Vorlagen-Editor zeigt Platzhalter ohne Datenquelle und Feld an und legt sie auf Knopfdruck als Felder an.

### Listen (Tabellenzeilen in Word)

Liefert eine Datenquelle eine Liste – `'raten' => [['nr' => 1, 'datum' => '01.12.2026', 'betrag' => '300,00 €'], …]` –
wird eine Word-Tabellenzeile mit `${raten.nr}`, `${raten.datum}`, `${raten.betrag}` je Eintrag wiederholt.

## 4. Briefbogen

Formen: **DIN 5008 Form B** (Anschriftfeld 45 mm von oben), **Form A** (27 mm) und **ohne Anschriftfeld**
für Verträge. Maße in mm sind einstellbar (Ränder, Anschriftfeld, Infoblock, Textbeginn), ebenso
Schrift, Größe, Zeilen- und Absatzabstand, Akzentfarbe (`.accent`), Falz- und Lochmarken.

- **Briefkopf** (nur Seite 1), **Rücksendeangabe**, **Informationsblock** rechts
- **Fußzeile** auf jeder Seite, **Kopfzeile ab Seite 2** (z. B. `Seite ${seite} von ${seiten}`)
- **Felder** des Briefbogens (Standard: `anschrift`, `datum`, `ihr_zeichen`) werden bei jedem Brief abgefragt
- Zusätzliches **CSS**; Klassen siehe Editor (`.nf-signatures` für Unterschriftszeilen, `.nf-pagebreak` …)

Die Hilfslinien in der Vorschau zeigen Anschriftfeld, Vermerkzone und Infoblock.

## 5. Datenquelle Akte

```bash
php artisan vendor:publish --tag=nfdocuments-stubs    # → app/Documents/AkteDataProvider.php
```

An die eigenen Models anpassen (gleiches Schema wie die Adressquelle des Mailers) und eintragen:

```php
'providers' => [
    GeneralProvider::class,
    KanzleiProvider::class,
    App\Documents\AkteDataProvider::class,
],
```

Eine Quelle implementiert `Nfgarching\Documents\Contracts\DataProvider`:

- `placeholders()` – `['mandant.name' => 'Name des Mandanten', …]` für das Einfügen-Menü
- `values(array $context)` – Werte, verschachtelt oder mit Punkten

## 6. Zusammenspiel mit nfgarching/mailer

Ist der Mailer installiert, bietet der Dialog nach dem Erzeugen **„Per E-Mail senden“** an:
das Mailer-Modal öffnet sich mit dem PDF als Anhang (bzw. Download-Link) und demselben Kontext.
Dafür den Ablagepfad im Mailer erlauben (`config/nfmailer.php`):

```php
'attachments' => [
    'allowed_disks'    => ['local', 'data'],
    'allowed_prefixes' => [
        'local' => ['akten/', 'rechnungen/', 'nfdocuments/'],
    ],
],
```

## 7. Technik

- **HTML-Vorlagen**: `HtmlRenderer` baut eine vollständige Seite (Briefbogen + Text + Werte).
  [paged.js](https://pagedjs.org) (MIT, liegt im Package) bricht sie in A4-Seiten um; Kopf-/Fußzeilen werden danach
  in jede Seite gesetzt. Gotenberg (Chromium) wartet auf `window.nfdocReady` und druckt.
- **Word-Vorlagen**: PhpWord füllt die Datei (`DocxRenderer`), Gotenberg (LibreOffice) wandelt sie um.
  Die ausgefüllte .docx wird mit abgelegt.
- **Ablage**: `storage/app/nfdocuments/JJJJ/MM/…` (Disk und Ordner in der Config); Tabellen
  `nfdoc_layouts`, `nfdoc_templates`, `nfdoc_documents`.
- **Vorschau** im `iframe sandbox="allow-scripts"` – eigene Vorlagen können die App nicht ansprechen.
- **ZUGFeRD** (für das Rechnungs-Package): Gotenberg bettet XML in PDF/A-3b ein (`gotenberg-php`: `facturX()`).

## 8. Konfiguration

`config/nfdocuments.php` – u. a. `gotenberg`, `storage`, `providers`, `kanzlei`, `categories`,
`fonts` (Auswahl im Editor), `layout` (Blade-Layout der Seiten), `gate` (Vorlagen bearbeiten),
`gate_documents` (Dokumente abrufen), `route_prefix`.
