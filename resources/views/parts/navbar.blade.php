{{-- Navbar: Dokument erstellen (Modal nf-document-composer) und Verwaltung der Vorlagen --}}
@auth
    <li class="nav-item dropdown d-none d-lg-inline">
        <button type="button" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Dokumente">
            <i class="bi bi-file-earmark-richtext"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <button type="button" class="dropdown-item" onclick="Livewire.dispatch('nfdocuments-open', { options: {} })">
                    <i class="bi bi-file-earmark-plus"></i> Dokument erstellen
                </button>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('nfdocuments.index') }}"><i class="bi bi-collection"></i> Vorlagen</a></li>
            <li><a class="dropdown-item" href="{{ route('nfdocuments.index', ['tab' => 'layouts']) }}"><i class="bi bi-layout-text-window"></i> Briefbögen</a></li>
            <li><a class="dropdown-item" href="{{ route('nfdocuments.index', ['tab' => 'documents']) }}"><i class="bi bi-clock-history"></i> Erzeugte Dokumente</a></li>
        </ul>
    </li>
@endauth
