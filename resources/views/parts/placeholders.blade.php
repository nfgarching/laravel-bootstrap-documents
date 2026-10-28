{{-- Platzhalter zum Einfügen. Erwartet $placeholders [key => Beschreibung] --}}
<div x-data="{ q: '' }">
  <input type="search" class="form-control form-control-sm mb-2" placeholder="Platzhalter suchen …" x-model="q">
  <div class="nfd-ph list-group list-group-flush border rounded">
    @foreach ($placeholders as $key => $label)
      <div class="list-group-item px-2 py-1 d-flex align-items-center gap-1"
           x-show="!q || @js(mb_strtolower($key.' '.$label)).includes(q.toLowerCase())">
        <button type="button" class="btn btn-link btn-sm p-0 text-start text-decoration-none flex-grow-1"
                title="{{ $label }} – an der Cursorposition einfügen"
                x-on:click="nfdocInsert(@js($key))">${{ '{' }}{{ $key }}}</button>
        <button type="button" class="btn btn-link btn-sm p-0 text-body-secondary" title="Als Abschnitt einfügen: nur sichtbar, wenn vorhanden"
                x-on:click="nfdocInsert(@js($key), true)"><i class="bi bi-braces"></i></button>
      </div>
    @endforeach
  </div>
  <div class="form-text">
    Klick fügt <code>${schlüssel}</code> ein; <i class="bi bi-braces"></i> einen Abschnitt
    <code>${#schlüssel}…${/schlüssel}</code>, der nur erscheint, wenn der Wert vorhanden bzw. „ja“ ist.
    <code>${^schlüssel}…${/schlüssel}</code> erscheint nur, wenn er fehlt.
  </div>
</div>
