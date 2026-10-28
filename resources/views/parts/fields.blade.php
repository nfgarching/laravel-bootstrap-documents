{{-- Eingabefelder bearbeiten (Trait EditsFields). Erwartet $types --}}
<div class="table-responsive">
  <table class="table table-sm align-middle mb-2">
    <thead class="small text-body-secondary">
      <tr>
        <th style="width: 16%">Schlüssel</th>
        <th style="width: 20%">Bezeichnung</th>
        <th style="width: 14%">Typ</th>
        <th>Vorgabe <i class="bi bi-info-circle" title="Fester Wert oder Platzhalter, z. B. ${mandant.name} oder ${heute_iso}"></i></th>
        <th class="text-center" style="width: 4rem">Pflicht</th>
        <th style="width: 6.5rem"></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($this->fields as $i => $f)
        <tr wire:key="field-{{ $i }}">
          <td>
            <input type="text" class="form-control form-control-sm nfd-code @error("fields.$i.key") is-invalid @enderror"
                   wire:model.blur="fields.{{ $i }}.key" placeholder="schluessel">
            @error("fields.$i.key") <div class="invalid-feedback">{{ $message }}</div> @enderror
          </td>
          <td><input type="text" class="form-control form-control-sm" wire:model.blur="fields.{{ $i }}.label"></td>
          <td>
            <select class="form-select form-select-sm" wire:model.live="fields.{{ $i }}.type">
              @foreach ($types as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
            </select>
          </td>
          <td>
            @if (($f['type'] ?? '') === 'textarea')
              <textarea class="form-control form-control-sm" rows="2" wire:model.blur="fields.{{ $i }}.default"></textarea>
            @else
              <input type="text" class="form-control form-control-sm" wire:model.blur="fields.{{ $i }}.default"
                     placeholder="{{ ($f['type'] ?? '') === 'checkbox' ? '1 = angehakt' : '' }}">
            @endif
            @if (($f['type'] ?? '') === 'select')
              <textarea class="form-control form-control-sm mt-1 nfd-code" rows="3" wire:model.blur="fields.{{ $i }}.options"
                        placeholder="wert=Beschriftung (eine Option je Zeile)"></textarea>
            @endif
            <input type="text" class="form-control form-control-sm mt-1 border-0 border-bottom rounded-0 small"
                   wire:model.blur="fields.{{ $i }}.help" placeholder="Hinweis zum Feld (optional)">
          </td>
          <td class="text-center"><input type="checkbox" class="form-check-input" wire:model="fields.{{ $i }}.required"></td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-link px-1" wire:click="moveField({{ $i }}, -1)" @disabled($i === 0) title="nach oben"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="btn btn-sm btn-link px-1" wire:click="moveField({{ $i }}, 1)" @disabled($loop->last) title="nach unten"><i class="bi bi-arrow-down"></i></button>
            <button type="button" class="btn btn-sm btn-link px-1 text-danger" wire:click="removeField({{ $i }})" title="entfernen"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-body-secondary small">Keine Eingabefelder.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addField"><i class="bi bi-plus-lg"></i> Feld hinzufügen</button>
