<div class="container-fluid py-3">
  @include('nfdocuments::parts.assets')
  @php $font = (config('nfdocuments.fonts')[$page['font'] ?? ''] ?? 'Arial, sans-serif'); @endphp

  {{-- Kopfzeile --}}
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="{{ route('nfdocuments.index', ['tab' => 'layouts']) }}" class="btn btn-sm btn-link px-0" wire:navigate><i class="bi bi-arrow-left"></i></a>
    <input type="text" class="form-control fw-semibold @error('name') is-invalid @enderror" style="max-width: 22rem" wire:model.blur="name" placeholder="Name des Briefbogens">
    <select class="form-select" style="max-width: 20rem" wire:model.live="form">
      @foreach ($forms as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
    </select>
    <div class="form-check ms-1">
      <input class="form-check-input" type="checkbox" id="nfdDefault" wire:model="is_default">
      <label class="form-check-label small" for="nfdDefault">Standard</label>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
      @if ($saved) <span class="small text-success"><i class="bi bi-check2"></i> {{ $saved }}</span> @endif
      <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="nfdGuides" wire:model.live="guides">
        <label class="form-check-label small" for="nfdGuides">Hilfslinien</label>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="pdfTest" wire:loading.attr="disabled" wire:target="pdfTest">
        <span wire:loading wire:target="pdfTest" class="spinner-border spinner-border-sm"></span>
        <i class="bi bi-file-earmark-pdf" wire:loading.remove wire:target="pdfTest"></i> PDF-Probedruck
      </button>
      <button type="button" class="btn btn-sm btn-primary" wire:click="save"><i class="bi bi-floppy"></i> Speichern</button>
    </div>
  </div>

  @if ($notice) <div class="alert alert-danger py-2">{{ $notice }}</div> @endif
  @if ($errors->any()) <div class="alert alert-warning py-2 small">{{ implode(' ', $errors->all()) }}</div> @endif

  <div class="row g-3">
    {{-- Bearbeiten --}}
    <div class="col-xl-6" x-data="{ tab: 'kopf', ph: false }">
      <ul class="nav nav-tabs small mb-2">
        @foreach (['kopf' => 'Briefkopf', 'info' => 'Anschrift & Info', 'fuss' => 'Fußzeile & Folgeseiten', 'seite' => 'Seite & Schrift', 'felder' => 'Felder', 'css' => 'CSS'] as $k => $l)
          <li class="nav-item"><button type="button" class="nav-link py-1" :class="tab === '{{ $k }}' && 'active'" x-on:click="tab = '{{ $k }}'">{{ $l }}</button></li>
        @endforeach
        <li class="nav-item ms-auto">
          <button type="button" class="nav-link py-1" :class="ph && 'active'" x-on:click="ph = !ph"><i class="bi bi-braces"></i> Platzhalter</button>
        </li>
      </ul>

      <div x-show="ph" x-cloak class="mb-3">@include('nfdocuments::parts.placeholders')</div>

      {{-- Briefkopf --}}
      <div x-show="tab === 'kopf'">
        <label class="form-label small fw-semibold">Briefkopf <span class="fw-normal text-body-secondary">– erste Seite, oberhalb des Anschriftfelds</span></label>
        <div wire:ignore x-data="nfdocEditor('header_html', { height: 260, mini: true, font: @js($font) })"><textarea x-ref="area"></textarea></div>

        <div class="row g-2 mt-2 align-items-end">
          <div class="col-md-7">
            <label class="form-label small fw-semibold">Logo</label>
            <input type="file" class="form-control form-control-sm @error('logo') is-invalid @enderror" wire:model="logo" accept="image/*">
            @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">Im Briefkopf mit <code>${logo}</code> platzieren. PNG oder SVG empfohlen.</div>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Logohöhe (mm)</label>
            <input type="number" step="1" class="form-control form-control-sm" wire:model.live.debounce.500ms="page.logo_height">
          </div>
          <div class="col-md-2">
            @if ($logo_path || $logo)
              <button type="button" class="btn btn-sm btn-outline-danger w-100" wire:click="$set('removeLogo', true)" @disabled($removeLogo)>Logo entfernen</button>
            @endif
          </div>
        </div>
      </div>

      {{-- Anschrift & Info --}}
      <div x-show="tab === 'info'" x-cloak>
        @if ($form === 'plain')
          <div class="alert alert-light border small">Diese Form hat kein Anschriftfeld. Für Briefe „DIN 5008 Form A/B“ wählen.</div>
        @else
          <label class="form-label small fw-semibold">Rücksendeangabe <span class="fw-normal text-body-secondary">– kleine Zeile über der Anschrift</span></label>
          <input type="text" class="form-control form-control-sm mb-3" wire:model.live.debounce.600ms="sender_line">

          <label class="form-label small fw-semibold">Informationsblock <span class="fw-normal text-body-secondary">– rechts neben der Anschrift (Zeichen, Bearbeiter, Datum)</span></label>
          <div wire:ignore x-data="nfdocEditor('info_html', { height: 240, mini: true, font: @js($font), size: 9 })"><textarea x-ref="area"></textarea></div>
          <div class="form-text">Die Anschrift selbst kommt aus dem Feld <code>anschrift</code> (siehe „Felder“). Zeilen, die nur bei vorhandenem Wert erscheinen sollen, in <code>${#schlüssel}…${/schlüssel}</code> setzen.</div>

          <div class="row g-2 mt-2">
            @foreach (['address_top' => 'Anschriftfeld oben', 'address_left' => 'Anschriftfeld links', 'info_top' => 'Infoblock oben', 'info_left' => 'Infoblock links', 'text_start' => 'Textbeginn'] as $k => $l)
              <div class="col-6 col-md-4">
                <label class="form-label small">{{ $l }} (mm)</label>
                <input type="number" step="0.5" class="form-control form-control-sm" wire:model.live.debounce.500ms="page.{{ $k }}">
              </div>
            @endforeach
            <div class="col-6 col-md-4 d-flex align-items-end">
              <button type="button" class="btn btn-sm btn-outline-secondary w-100" wire:click="resetGeometry" title="Maße nach DIN 5008 wiederherstellen">DIN-Maße</button>
            </div>
          </div>
        @endif
      </div>

      {{-- Fußzeile & Folgeseiten --}}
      <div x-show="tab === 'fuss'" x-cloak>
        <label class="form-label small fw-semibold">Fußzeile <span class="fw-normal text-body-secondary">– auf jeder Seite</span></label>
        <div wire:ignore x-data="nfdocEditor('footer_html', { height: 200, mini: true, font: @js($font), size: 7 })"><textarea x-ref="area"></textarea></div>

        <label class="form-label small fw-semibold mt-3">Kopfzeile <span class="fw-normal text-body-secondary">– ab Seite 2, z. B. „Seite ${seite} von ${seiten}“</span></label>
        <div wire:ignore x-data="nfdocEditor('follow_header_html', { height: 140, mini: true, font: @js($font), size: 8 })"><textarea x-ref="area"></textarea></div>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="nfdFollowFirst" wire:model.live="page.follow_header_first">
          <label class="form-check-label small" for="nfdFollowFirst">Kopfzeile auch auf Seite 1</label>
        </div>
      </div>

      {{-- Seite & Schrift --}}
      <div x-show="tab === 'seite'" x-cloak>
        <div class="row g-2">
          @foreach (['margin_top' => 'Rand oben', 'margin_bottom' => 'Rand unten', 'margin_left' => 'Rand links', 'margin_right' => 'Rand rechts'] as $k => $l)
            <div class="col-6 col-md-3">
              <label class="form-label small">{{ $l }} (mm)</label>
              <input type="number" step="0.5" class="form-control form-control-sm @error("page.$k") is-invalid @enderror" wire:model.live.debounce.500ms="page.{{ $k }}">
            </div>
          @endforeach
          <div class="col-md-4">
            <label class="form-label small">Schrift</label>
            <select class="form-select form-select-sm" wire:model.live="page.font">
              @foreach ($fonts as $f) <option value="{{ $f }}">{{ $f }}</option> @endforeach
            </select>
          </div>
          <div class="col-4 col-md-2">
            <label class="form-label small">Größe (pt)</label>
            <input type="number" step="0.5" class="form-control form-control-sm" wire:model.live.debounce.500ms="page.font_size">
          </div>
          <div class="col-4 col-md-3">
            <label class="form-label small">Zeilenabstand</label>
            <input type="number" step="0.05" class="form-control form-control-sm" wire:model.live.debounce.500ms="page.line_height">
          </div>
          <div class="col-4 col-md-3">
            <label class="form-label small">Absatz (mm)</label>
            <input type="number" step="0.5" class="form-control form-control-sm" wire:model.live.debounce.500ms="page.paragraph">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small">Textfarbe</label>
            <input type="color" class="form-control form-control-sm form-control-color w-100" wire:model.live.debounce.500ms="page.color">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small">Akzentfarbe <code class="small">.accent</code></label>
            <input type="color" class="form-control form-control-sm form-control-color w-100" wire:model.live.debounce.500ms="page.accent">
          </div>
          @if ($form !== 'plain')
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="nfdFolds" wire:model.live="page.fold_marks">
                <label class="form-check-label small" for="nfdFolds">Falz- und Lochmarken drucken</label>
              </div>
            </div>
          @endif
        </div>
        <div class="form-text mt-2">DIN 5008: Rand links 25 mm, rechts mindestens 20 mm. Der untere Rand muss die Fußzeile aufnehmen.</div>
      </div>

      {{-- Felder --}}
      <div x-show="tab === 'felder'" x-cloak>
        <p class="small text-body-secondary">Diese Felder fragt der Briefbogen bei jedem Dokument ab (zusätzlich zu den Feldern der Vorlage). <code>anschrift</code> füllt das Anschriftfeld.</p>
        @include('nfdocuments::parts.fields')
      </div>

      {{-- CSS --}}
      <div x-show="tab === 'css'" x-cloak>
        <textarea class="form-control nfd-code" rows="16" wire:model.live.debounce.800ms="css"
                  placeholder=".nf-body h2 { color: var(--nf-accent); }&#10;.nf-info td:first-child { color: #666; }"></textarea>
        <div class="form-text">
          Klassen: <code>.nf-brand</code> Briefkopf, <code>.nf-address</code>, <code>.nf-sender</code>, <code>.nf-recipient</code>,
          <code>.nf-info</code>, <code>.nf-body</code> Text, <code>.nf-footer</code>, <code>.nf-follow</code>,
          <code>.nf-signatures</code> Unterschriftszeilen, <code>.nf-pagebreak</code> Seitenumbruch, <code>.nf-keep</code> nicht trennen.
        </div>
      </div>
    </div>

    {{-- Vorschau --}}
    <div class="col-xl-6">
      @include('nfdocuments::parts.preview', ['html' => $this->previewHtml])
      <div class="form-text">Vorschau mit Beispieltext. Der PDF-Probedruck läuft über Gotenberg und entspricht dem späteren Dokument.</div>
    </div>
  </div>
</div>
