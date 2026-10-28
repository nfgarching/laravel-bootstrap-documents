<div class="container-fluid py-3">
  @include('nfdocuments::parts.assets')

  {{-- Kopfzeile --}}
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="{{ route('nfdocuments.index') }}" class="btn btn-sm btn-link px-0" wire:navigate><i class="bi bi-arrow-left"></i></a>
    <input type="text" class="form-control fw-semibold @error('name') is-invalid @enderror" style="max-width: 24rem" wire:model.blur="name" placeholder="Name der Vorlage">
    <select class="form-select" style="max-width: 12rem" wire:model="category">
      @foreach ($categories as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
    </select>
    <select class="form-select" style="max-width: 14rem" wire:model.live="engine" @disabled($templateId)>
      <option value="html">Text-Editor</option>
      <option value="docx">Word-Datei (.docx)</option>
    </select>
    @if ($engine === 'html')
      <select class="form-select" style="max-width: 18rem" wire:model.live="layout_id" title="Briefbogen">
        <option value="">Standard-Briefbogen</option>
        @foreach ($layouts as $l) <option value="{{ $l->id }}">{{ $l->name }}</option> @endforeach
      </select>
    @endif
    <div class="form-check ms-1">
      <input class="form-check-input" type="checkbox" id="nfdActive" wire:model="is_active">
      <label class="form-check-label small" for="nfdActive">aktiv</label>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
      @if ($saved) <span class="small text-success"><i class="bi bi-check2"></i> {{ $saved }}</span> @endif
      <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="pdfTest" wire:loading.attr="disabled" wire:target="pdfTest"
              @disabled($engine === 'docx' && ! $docx && ! $docx_path)>
        <span wire:loading wire:target="pdfTest" class="spinner-border spinner-border-sm"></span>
        <i class="bi bi-file-earmark-pdf" wire:loading.remove wire:target="pdfTest"></i> PDF-Probedruck
      </button>
      <button type="button" class="btn btn-sm btn-primary" wire:click="save"><i class="bi bi-floppy"></i> Speichern</button>
    </div>
  </div>

  @if ($notice) <div class="alert alert-danger py-2">{{ $notice }}</div> @endif
  @if ($errors->any()) <div class="alert alert-warning py-2 small">{{ implode(' ', $errors->all()) }}</div> @endif

  <div class="row g-3">
    <div class="{{ $engine === 'html' ? 'col-xl-6' : 'col-xl-8' }}" x-data="{ tab: @js($tab) }" wire:key="tpl-edit-{{ $engine }}">
      <ul class="nav nav-tabs small mb-2">
        @if ($engine === 'html')
          <li class="nav-item"><button type="button" class="nav-link py-1" :class="tab === 'text' && 'active'" x-on:click="tab = 'text'">Text</button></li>
        @else
          <li class="nav-item"><button type="button" class="nav-link py-1" :class="tab === 'word' && 'active'" x-on:click="tab = 'word'">Word-Datei</button></li>
        @endif
        <li class="nav-item">
          <button type="button" class="nav-link py-1" :class="tab === 'felder' && 'active'" x-on:click="tab = 'felder'">
            Eingabefelder <span class="badge text-bg-light border">{{ count($fields) }}</span>
          </button>
        </li>
        <li class="nav-item"><button type="button" class="nav-link py-1" :class="tab === 'info' && 'active'" x-on:click="tab = 'info'">Beschreibung</button></li>
      </ul>

      @if (count($this->unknownKeys))
        <div class="alert alert-warning py-2 small d-flex align-items-center gap-2">
          <div class="flex-grow-1">
            Ohne Feld und ohne Datenquelle:
            @foreach ($this->unknownKeys as $k) <code>${{ '{' }}{{ $k }}}</code> @endforeach
          </div>
          <button type="button" class="btn btn-sm btn-warning" wire:click="addMissingFields">Als Felder anlegen</button>
        </div>
      @endif

      {{-- Text --}}
      @if ($engine === 'html')
        <div x-show="tab === 'text'">
          <div class="row g-2">
            <div class="col-lg-8">
              <div wire:ignore x-data="nfdocEditor('body', { height: 560 })"><textarea x-ref="area"></textarea></div>
              <div class="form-text">
                Überschriften „Titel“ und „Überschrift (§)“ im Absatz-Menü; Quelltext über <i class="bi bi-code-slash"></i>.
                Seitenumbruch: <code>&lt;div class="nf-pagebreak"&gt;&lt;/div&gt;</code>,
                Unterschriften: <code>&lt;div class="nf-signatures"&gt;&lt;div&gt;Gläubiger&lt;/div&gt;&lt;div&gt;Schuldner&lt;/div&gt;&lt;/div&gt;</code>.
              </div>
            </div>
            <div class="col-lg-4">
              <div class="small fw-semibold mb-1">Platzhalter</div>
              @include('nfdocuments::parts.placeholders')
            </div>
          </div>
        </div>
      @else
        {{-- Word --}}
        <div x-show="tab === 'word'">
          <div class="row g-3">
            <div class="col-lg-7">
              <label class="form-label small fw-semibold">Word-Datei (.docx)</label>
              <input type="file" class="form-control @error('docx') is-invalid @enderror" wire:model="docx" accept=".docx">
              @error('docx') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div wire:loading wire:target="docx" class="small text-body-secondary mt-1"><span class="spinner-border spinner-border-sm"></span> wird gelesen …</div>
              @if ($docx_path)
                <a href="{{ route('nfdocuments.templates.docx', $templateId) }}" class="btn btn-sm btn-outline-secondary mt-2">
                  <i class="bi bi-download"></i> Aktuelle Vorlage herunterladen und in Word bearbeiten
                </a>
              @endif

              <div class="card mt-3">
                <div class="card-body small">
                  <div class="fw-semibold mb-1">So geht's</div>
                  <ol class="mb-0 ps-3">
                    <li>Vorlage in Word gestalten (Schrift, Ränder, Kopf-/Fußzeile, Logo).</li>
                    <li>Platzhalter am Stück tippen: <code>${mandant.name}</code> – erst danach als Ganzes formatieren.</li>
                    <li>Optionale Abschnitte: <code>${#vorschuss}</code> und <code>${/vorschuss}</code> jeweils in einen <em>eigenen Absatz</em>.</li>
                    <li>Tabellenzeilen für Listen: <code>${raten.betrag}</code> in eine Zeile – sie wird je Eintrag wiederholt.</li>
                    <li>Hochladen, Felder anlegen, Probedruck.</li>
                  </ol>
                </div>
              </div>
            </div>
            <div class="col-lg-5">
              <div class="small fw-semibold mb-1">Platzhalter in der Datei</div>
              @forelse ($docxKeys as $k)
                <span class="badge text-bg-light border nfd-code me-1 mb-1">${{ '{' }}{{ $k }}}</span>
              @empty
                <div class="small text-body-secondary">Noch keine Datei bzw. keine Platzhalter gefunden.</div>
              @endforelse
              <div class="small fw-semibold mt-3 mb-1">Verfügbar</div>
              @include('nfdocuments::parts.placeholders')
            </div>
          </div>
        </div>
      @endif

      {{-- Felder --}}
      <div x-show="tab === 'felder'" x-cloak>
        <p class="small text-body-secondary mb-2">
          Diese Felder werden beim Erstellen abgefragt.
          @if ($engine === 'html') Felder des Briefbogens (z. B. Anschrift, Datum) kommen automatisch dazu. @endif
          Ein Feld mit dem Schlüssel einer Datenquelle (z. B. <code>mandant.name</code>) wird mit deren Wert vorbelegt und bleibt änderbar.
        </p>
        @include('nfdocuments::parts.fields')
      </div>

      {{-- Beschreibung --}}
      <div x-show="tab === 'info'" x-cloak>
        <label class="form-label small fw-semibold">Beschreibung / Hinweise zur Verwendung</label>
        <textarea class="form-control" rows="4" wire:model.blur="description"></textarea>
        <label class="form-label small fw-semibold mt-3">Reihenfolge in der Auswahl</label>
        <input type="number" class="form-control" style="max-width: 8rem" wire:model.blur="sort">
      </div>
    </div>

    <div class="{{ $engine === 'html' ? 'col-xl-6' : 'col-xl-4' }}">
      @if ($engine === 'html')
        @include('nfdocuments::parts.preview', ['html' => $this->previewHtml])
        <div class="form-text">Vorschau mit Beispielwerten; gelb markiert sind Platzhalter ohne Wert.</div>
      @else
        <div class="alert alert-light border small">
          Word-Vorlagen werden über LibreOffice (Gotenberg) in PDF umgewandelt.
          Für eine Vorschau den <strong>PDF-Probedruck</strong> verwenden; leere Platzhalter erscheinen dort als <code>[schlüssel]</code>.
        </div>
      @endif
    </div>
  </div>
</div>
