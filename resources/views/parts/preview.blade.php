{{-- Live-Vorschau (paged.js im abgeschotteten iframe). Erwartet $html, optional $height --}}
<div class="nfd-preview" style="height: {{ $height ?? '78vh' }}" x-data="nfdocPreview()">
  @if ($html)
    <iframe x-ref="frame" sandbox="allow-scripts" srcdoc="{{ $html }}" title="Vorschau"></iframe>
    <span class="nfd-pages badge text-bg-dark opacity-75" x-show="pages" x-cloak>
      <span x-text="pages"></span> <span x-text="pages == 1 ? 'Seite' : 'Seiten'"></span>
    </span>
  @else
    <div class="h-100 d-flex align-items-center justify-content-center text-body-secondary p-4 text-center">
      {{ $empty ?? 'Keine Vorschau' }}
    </div>
  @endif
  <div wire:loading.delay class="position-absolute top-0 end-0 m-2">
    <span class="spinner-border spinner-border-sm text-secondary"></span>
  </div>
</div>
