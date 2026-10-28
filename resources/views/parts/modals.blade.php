{{-- Über die ModalRegistry in jedes Layout eingebunden (DocumentsServiceProvider).
     Nicht auf der eigenständigen Seite nfdocuments.compose: dort ist die Seite selbst der Dialog. --}}
@auth
    @unless (request()->routeIs('nfdocuments.compose'))
        <livewire:nf-document-composer />
    @endunless
@endauth
