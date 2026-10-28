<div class="container-xl py-3">
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h4 class="mb-0 me-auto"><i class="bi bi-file-earmark-richtext"></i> Dokumente &amp; Vorlagen</h4>
    <input type="search" class="form-control form-control-sm" style="max-width: 16rem" placeholder="Suchen …" wire:model.live.debounce.300ms="search">
    <a href="{{ route('nfdocuments.compose') }}" class="btn btn-sm btn-primary" wire:navigate>
      <i class="bi bi-file-earmark-plus"></i> Dokument erstellen
    </a>
  </div>

  <ul class="nav nav-tabs mb-3">
    @foreach (['templates' => 'Vorlagen', 'layouts' => 'Briefbögen', 'documents' => 'Erzeugte Dokumente'] as $k => $l)
      <li class="nav-item">
        <button type="button" class="nav-link @if ($tab === $k) active @endif" wire:click="$set('tab', '{{ $k }}')">{{ $l }}</button>
      </li>
    @endforeach
  </ul>

  @if ($tab === 'templates')
    <div class="d-flex gap-2 mb-3">
      <a href="{{ route('nfdocuments.templates.create') }}" class="btn btn-sm btn-outline-primary" wire:navigate><i class="bi bi-plus-lg"></i> Neue Vorlage (Text-Editor)</a>
      <a href="{{ route('nfdocuments.templates.create', ['engine' => 'docx']) }}" class="btn btn-sm btn-outline-primary" wire:navigate><i class="bi bi-file-earmark-word"></i> Neue Vorlage (Word-Datei)</a>
    </div>

    @forelse ($templates as $cat => $list)
      <h6 class="text-body-secondary text-uppercase small mt-3">{{ $categories[$cat] ?? $cat }}</h6>
      <div class="list-group mb-2">
        @foreach ($list as $t)
          <div class="list-group-item d-flex align-items-center gap-3 @unless ($t->is_active) bg-body-tertiary @endunless" wire:key="t-{{ $t->id }}">
            <i class="bi {{ $t->engine === 'docx' ? 'bi-file-earmark-word text-primary' : 'bi-file-earmark-text' }} fs-5"></i>
            <div class="flex-grow-1 min-w-0">
              <a href="{{ route('nfdocuments.templates.edit', $t) }}" class="fw-semibold text-decoration-none" wire:navigate>{{ $t->name }}</a>
              @unless ($t->is_active) <span class="badge text-bg-secondary ms-1">inaktiv</span> @endunless
              <div class="small text-body-secondary text-truncate">
                {{ $t->engine === 'docx' ? 'Word-Datei' : ($t->layout?->name ?? 'Standard-Briefbogen') }}
                @if ($t->description) · {{ $t->description }} @endif
              </div>
            </div>
            <div class="btn-group btn-group-sm">
              <a href="{{ route('nfdocuments.templates.edit', $t) }}" class="btn btn-outline-secondary" title="Bearbeiten" wire:navigate><i class="bi bi-pencil"></i></a>
              <button type="button" class="btn btn-outline-secondary" title="Kopieren" wire:click="duplicateTemplate({{ $t->id }})"><i class="bi bi-copy"></i></button>
              <button type="button" class="btn btn-outline-secondary" title="{{ $t->is_active ? 'Deaktivieren' : 'Aktivieren' }}" wire:click="toggleTemplate({{ $t->id }})"><i class="bi {{ $t->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i></button>
              <button type="button" class="btn btn-outline-danger" title="Löschen" wire:click="deleteTemplate({{ $t->id }})" wire:confirm="Vorlage „{{ $t->name }}“ löschen? Bereits erzeugte Dokumente bleiben erhalten."><i class="bi bi-trash"></i></button>
            </div>
          </div>
        @endforeach
      </div>
    @empty
      <div class="alert alert-light border">Noch keine Vorlagen. Startausstattung anlegen mit <code>php artisan nfdocuments:install</code>.</div>
    @endforelse
  @endif

  @if ($tab === 'layouts')
    @error('layouts') <div class="alert alert-warning py-2">{{ $message }}</div> @enderror
    <a href="{{ route('nfdocuments.layouts.create') }}" class="btn btn-sm btn-outline-primary mb-3" wire:navigate><i class="bi bi-plus-lg"></i> Neuer Briefbogen</a>
    <div class="list-group">
      @forelse ($layouts as $l)
        <div class="list-group-item d-flex align-items-center gap-3" wire:key="l-{{ $l->id }}">
          <i class="bi bi-layout-text-window fs-5"></i>
          <div class="flex-grow-1">
            <a href="{{ route('nfdocuments.layouts.edit', $l) }}" class="fw-semibold text-decoration-none" wire:navigate>{{ $l->name }}</a>
            @if ($l->is_default) <span class="badge text-bg-primary ms-1">Standard</span> @endif
            <div class="small text-body-secondary">{{ \Nfgarching\Documents\Models\DocumentLayout::FORMS[$l->form] ?? $l->form }} · {{ $l->templates_count }} Vorlage(n)</div>
          </div>
          <div class="btn-group btn-group-sm">
            <a href="{{ route('nfdocuments.layouts.edit', $l) }}" class="btn btn-outline-secondary" title="Bearbeiten" wire:navigate><i class="bi bi-pencil"></i></a>
            @unless ($l->is_default)
              <button type="button" class="btn btn-outline-secondary" title="Als Standard verwenden" wire:click="makeDefault({{ $l->id }})"><i class="bi bi-star"></i></button>
            @endunless
            <button type="button" class="btn btn-outline-secondary" title="Kopieren" wire:click="duplicateLayout({{ $l->id }})"><i class="bi bi-copy"></i></button>
            <button type="button" class="btn btn-outline-danger" title="Löschen" wire:click="deleteLayout({{ $l->id }})" wire:confirm="Briefbogen „{{ $l->name }}“ löschen?"><i class="bi bi-trash"></i></button>
          </div>
        </div>
      @empty
        <div class="list-group-item text-body-secondary">Noch kein Briefbogen.</div>
      @endforelse
    </div>
  @endif

  @if ($tab === 'documents')
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead class="small text-body-secondary">
          <tr><th>Dokument</th><th>Vorlage</th><th>Bezug</th><th>Erstellt</th><th class="text-end">Größe</th><th></th></tr>
        </thead>
        <tbody>
          @forelse ($documents as $d)
            <tr wire:key="d-{{ $d->id }}">
              <td>
                {{ $d->title }}
                @if ($d->version > 1) <span class="badge text-bg-light border">Fassung {{ $d->version }}</span> @endif
              </td>
              <td class="small">{{ $d->template?->name ?? '–' }}</td>
              <td class="small nfd-code">{{ $d->context_key ?? '–' }}</td>
              <td class="small text-nowrap">{{ $d->created_at->format('d.m.Y H:i') }}</td>
              <td class="small text-end text-nowrap">{{ number_format($d->size / 1024, 0, ',', '.') }} KB</td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('nfdocuments.document', $d) }}" target="_blank" title="PDF öffnen"><i class="bi bi-file-earmark-pdf"></i></a>
                @if ($d->docx_path)
                  <a class="btn btn-sm btn-outline-secondary" href="{{ route('nfdocuments.document', [$d, 'docx']) }}" title="Word-Datei"><i class="bi bi-file-earmark-word"></i></a>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-body-secondary">Noch keine Dokumente erzeugt.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{ $documents?->links() }}
  @endif
</div>
