<div>
@if ($page)
<div class="container-fluid py-3" x-on:nfdocuments-hide.window="setTimeout(() => window.close(), 300)">
  <div class="border rounded bg-body">
@else
<div class="modal fade" id="nfdocumentsModal" tabindex="-1" aria-labelledby="nfdocumentsTitle" aria-hidden="true"
     wire:ignore.self
     x-data
     x-on:nfdocuments-show.window="bootstrap.Modal.getOrCreateInstance($el).show()"
     x-on:nfdocuments-hide.window="bootstrap.Modal.getOrCreateInstance($el).hide()">
  <div class="modal-dialog modal-fullscreen-xl-down" style="max-width: min(96vw, 1500px)">
    <div class="modal-content">
@endif
  @include('nfdocuments::parts.assets')
  @php $tpl = $this->chosen; @endphp

      <div class="modal-header py-2">
        @if ($tpl)
          <button type="button" class="btn btn-sm btn-link px-1 me-1" wire:click="back" title="Andere Vorlage"><i class="bi bi-arrow-left"></i></button>
          <h5 class="modal-title me-3" id="nfdocumentsTitle">{{ $tpl->name }}</h5>
          <input type="text" class="form-control form-control-sm" style="max-width: 24rem" wire:model.blur="title" title="Titel bzw. Dateiname">
        @else
          <h5 class="modal-title" id="nfdocumentsTitle"><i class="bi bi-file-earmark-plus"></i> Dokument erstellen</h5>
        @endif
        @if ($page)
          <button type="button" class="btn-close ms-auto" aria-label="Schließen" onclick="window.close()"></button>
        @else
          <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Schließen"></button>
        @endif
      </div>

      <div class="modal-body">
        @if ($notice) <div class="alert alert-danger py-2">{{ $notice }}</div> @endif

        @if (! $tpl)
          {{-- ======================================== Vorlage wählen --}}
          <div class="d-flex flex-wrap gap-2 mb-3">
            <input type="search" class="form-control" style="max-width: 20rem" placeholder="Vorlage suchen …" wire:model.live.debounce.250ms="search" autofocus>
            <div class="btn-group btn-group-sm">
              <button type="button" class="btn btn-outline-secondary @if (! $category) active @endif" wire:click="$set('category', '')">Alle</button>
              @foreach ($categories as $k => $l)
                <button type="button" class="btn btn-outline-secondary @if ($category === $k) active @endif" wire:click="$set('category', '{{ $k }}')">{{ $l }}</button>
              @endforeach
            </div>
          </div>

          @forelse ($this->templates as $cat => $list)
            <h6 class="text-body-secondary text-uppercase small mt-2">{{ $categories[$cat] ?? $cat }}</h6>
            <div class="row g-2 mb-2">
              @foreach ($list as $t)
                <div class="col-md-6 col-xl-4" wire:key="c-{{ $t->id }}">
                  <button type="button" class="btn btn-outline-secondary w-100 text-start h-100 py-2" wire:click="choose({{ $t->id }})">
                    <i class="bi {{ $t->engine === 'docx' ? 'bi-file-earmark-word' : 'bi-file-earmark-text' }} me-1"></i>
                    <span class="fw-semibold">{{ $t->name }}</span>
                    @if ($t->description) <div class="small text-body-secondary text-truncate">{{ $t->description }}</div> @endif
                  </button>
                </div>
              @endforeach
            </div>
          @empty
            <div class="text-body-secondary">Keine passende Vorlage.</div>
          @endforelse

        @elseif ($this->document)
          {{-- ======================================== Erzeugt --}}
          @php $doc = $this->document; @endphp
          <div class="text-center py-4 mx-auto" style="max-width: 34rem">
            <i class="bi bi-check-circle text-success display-5"></i>
            <h5 class="mt-2">{{ $doc->title }}</h5>
            <p class="small text-body-secondary">
              @if ($doc->version > 1) Fassung {{ $doc->version }} · @endif
              {{ number_format($doc->size / 1024, 0, ',', '.') }} KB · SHA-256 <span class="nfd-code" title="{{ $doc->sha256 }}">{{ substr($doc->sha256, 0, 12) }}…</span>
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
              <a href="{{ route('nfdocuments.document', $doc) }}" target="_blank" class="btn btn-primary"><i class="bi bi-file-earmark-pdf"></i> PDF öffnen</a>
              <a href="{{ route('nfdocuments.document', [$doc, 'pdf', 'download' => 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Herunterladen</a>
              @if ($doc->docx_path)
                <a href="{{ route('nfdocuments.document', [$doc, 'docx']) }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-word"></i> Word-Datei</a>
              @endif
              @if ($this->mailerAvailable())
                <button type="button" class="btn btn-outline-secondary" wire:click="sendByMail"><i class="bi bi-envelope"></i> Per E-Mail senden</button>
              @endif
            </div>
            <button type="button" class="btn btn-link btn-sm mt-3" wire:click="$set('documentId', null)">Weiter bearbeiten (neue Fassung)</button>
          </div>

        @else
          {{-- ======================================== Ausfüllen --}}
          <div class="row g-3">
            <div class="{{ $tpl->engine === 'html' ? 'col-lg-5' : 'col-lg-7' }}" style="max-height: 78vh; overflow: auto">
              @php $group = null; @endphp
              @foreach ($tpl->allFields() as $f)
                @php $key = $f['key']; $id = 'nfd-'.md5($key); $wk = \Nfgarching\Documents\Livewire\DocumentComposer::wireKey($key); @endphp
                @if (($f['group'] ?? null) !== $group)
                  @php $group = $f['group'] ?? null; @endphp
                  <div class="small text-uppercase text-body-secondary fw-semibold mt-2 mb-1">{{ $group }}</div>
                @endif
                <div class="mb-2" wire:key="f-{{ $id }}">
                  @if (($f['type'] ?? 'text') === 'checkbox')
                    <div class="form-check">
                      <input type="checkbox" class="form-check-input" id="{{ $id }}" wire:model.live="input.{{ $wk }}">
                      <label class="form-check-label" for="{{ $id }}">{{ $f['label'] ?? $key }}</label>
                    </div>
                  @else
                    <label class="form-label small mb-0" for="{{ $id }}">
                      {{ $f['label'] ?? $key }} @if (! empty($f['required'])) <span class="text-danger">*</span> @endif
                    </label>
                    @php $invalid = array_key_exists($key, $missing) ? 'is-invalid' : ''; @endphp
                    @switch($f['type'] ?? 'text')
                      @case('textarea')
                        <textarea id="{{ $id }}" class="form-control form-control-sm {{ $invalid }}" rows="{{ $key === 'anschrift' ? 5 : 6 }}" wire:model.live.debounce.700ms="input.{{ $wk }}"></textarea>
                        @break
                      @case('date')
                        <input id="{{ $id }}" type="date" class="form-control form-control-sm {{ $invalid }}" style="max-width: 12rem" wire:model.live="input.{{ $wk }}">
                        @break
                      @case('money')
                        <div class="input-group input-group-sm" style="max-width: 14rem">
                          <input id="{{ $id }}" type="text" inputmode="decimal" class="form-control text-end {{ $invalid }}" wire:model.live.debounce.700ms="input.{{ $wk }}" placeholder="0,00">
                          <span class="input-group-text">€</span>
                        </div>
                        @break
                      @case('number')
                        <input id="{{ $id }}" type="text" inputmode="decimal" class="form-control form-control-sm {{ $invalid }}" style="max-width: 10rem" wire:model.live.debounce.700ms="input.{{ $wk }}">
                        @break
                      @case('select')
                        <select id="{{ $id }}" class="form-select form-select-sm {{ $invalid }}" wire:model.live="input.{{ $wk }}">
                          <option value="">–</option>
                          @foreach (\Nfgarching\Documents\Rendering\ValueFormatter::options($f) as $v => $l)
                            <option value="{{ $v }}">{{ $l }}</option>
                          @endforeach
                        </select>
                        @break
                      @default
                        <input id="{{ $id }}" type="text" class="form-control form-control-sm {{ $invalid }}" wire:model.live.debounce.700ms="input.{{ $wk }}">
                    @endswitch
                  @endif
                  @if (! empty($f['help'])) <div class="form-text mt-0">{{ $f['help'] }}</div> @endif
                </div>
              @endforeach

              @if ($tpl->description)
                <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-info-circle"></i> {{ $tpl->description }}</div>
              @endif

              @if ($this->history->isNotEmpty())
                <div class="small text-body-secondary mt-3">Bereits erzeugt:</div>
                <ul class="list-unstyled small mb-0">
                  @foreach ($this->history as $h)
                    <li><a href="{{ route('nfdocuments.document', $h) }}" target="_blank">{{ $h->title }}</a> · Fassung {{ $h->version }} · {{ $h->created_at->format('d.m.Y H:i') }}</li>
                  @endforeach
                </ul>
              @endif
            </div>

            <div class="{{ $tpl->engine === 'html' ? 'col-lg-7' : 'col-lg-5' }}">
              @if ($tpl->engine === 'html')
                @include('nfdocuments::parts.preview', ['html' => $this->previewHtml, 'height' => '74vh'])
              @else
                <div class="alert alert-light border small">
                  <i class="bi bi-file-earmark-word"></i> Word-Vorlage: Vorschau über <strong>PDF-Vorschau</strong>.
                  Nach dem Erzeugen steht zusätzlich die ausgefüllte Word-Datei zum Weiterbearbeiten bereit.
                </div>
              @endif
            </div>
          </div>
        @endif
      </div>

      @if ($tpl && ! $this->document)
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-outline-secondary" wire:click="pdfPreview" wire:loading.attr="disabled" wire:target="pdfPreview">
            <span wire:loading wire:target="pdfPreview" class="spinner-border spinner-border-sm"></span>
            <i class="bi bi-eye" wire:loading.remove wire:target="pdfPreview"></i> PDF-Vorschau
          </button>
          <button type="button" class="btn btn-primary" wire:click="generate" wire:loading.attr="disabled" wire:target="generate">
            <span wire:loading wire:target="generate" class="spinner-border spinner-border-sm"></span>
            <i class="bi bi-file-earmark-check" wire:loading.remove wire:target="generate"></i> Erzeugen und ablegen
          </button>
        </div>
      @endif

@if ($page)
  </div>
</div>
@else
    </div>
  </div>
</div>
@endif
</div>
