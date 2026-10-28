<?php

namespace Nfgarching\Documents\Livewire;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nfgarching\Documents\Livewire\Concerns\EditsFields;
use Nfgarching\Documents\Models\Document;
use Nfgarching\Documents\Models\DocumentLayout;
use Nfgarching\Documents\Models\DocumentTemplate;

/** Übersicht: Vorlagen, Briefbögen und zuletzt erzeugte Dokumente */
class Library extends Component
{
    use EditsFields;
    use WithPagination;

    #[Url]
    public string $tab = 'templates';

    #[Url]
    public string $search = '';

    protected $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        if ($this->tab !== 'documents') {
            $this->authorizeManage();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function duplicateTemplate(int $id): void
    {
        $this->authorizeManage();
        $t = DocumentTemplate::findOrFail($id);
        $copy = $t->replicate(['slug']);
        $copy->name = $t->name.' (Kopie)';
        $copy->slug = $this->uniqueSlug(DocumentTemplate::class, $copy->name, null);

        if ($t->docx_path) {
            $disk = Storage::disk(config('nfdocuments.storage.disk', 'local'));
            $copy->docx_path = dirname($t->docx_path).'/'.$copy->slug.'-'.Str::lower(Str::random(6)).'.docx';
            $disk->copy($t->docx_path, $copy->docx_path);
        }

        $copy->save();
        $this->redirectRoute('nfdocuments.templates.edit', $copy->id, navigate: true);
    }

    public function toggleTemplate(int $id): void
    {
        $this->authorizeManage();
        $t = DocumentTemplate::findOrFail($id);
        $t->update(['is_active' => ! $t->is_active]);
    }

    public function deleteTemplate(int $id): void
    {
        $this->authorizeManage();
        $t = DocumentTemplate::findOrFail($id);

        if ($t->docx_path) {
            Storage::disk(config('nfdocuments.storage.disk', 'local'))->delete($t->docx_path);
        }

        $t->delete();   // erzeugte Dokumente bleiben erhalten
    }

    public function duplicateLayout(int $id): void
    {
        $this->authorizeManage();
        $l = DocumentLayout::findOrFail($id);
        $copy = $l->replicate(['slug', 'is_default']);
        $copy->name = $l->name.' (Kopie)';
        $copy->slug = $this->uniqueSlug(DocumentLayout::class, $copy->name, null);
        $copy->is_default = false;

        if ($l->logo_path) {
            $disk = Storage::disk(config('nfdocuments.storage.disk', 'local'));
            $copy->logo_path = dirname($l->logo_path).'/'.$copy->slug.'-'.Str::lower(Str::random(6)).'.'.pathinfo($l->logo_path, PATHINFO_EXTENSION);
            $disk->copy($l->logo_path, $copy->logo_path);
        }

        $copy->save();
        $this->redirectRoute('nfdocuments.layouts.edit', $copy->id, navigate: true);
    }

    public function makeDefault(int $id): void
    {
        $this->authorizeManage();
        DocumentLayout::findOrFail($id)->makeDefault();
    }

    public function deleteLayout(int $id): void
    {
        $this->authorizeManage();
        $l = DocumentLayout::findOrFail($id);

        if ($l->templates()->exists()) {
            $this->addError('layouts', "„{$l->name}“ wird noch von Vorlagen verwendet.");

            return;
        }

        if ($l->logo_path) {
            Storage::disk(config('nfdocuments.storage.disk', 'local'))->delete($l->logo_path);
        }

        $l->delete();
    }

    public function render()
    {
        $like = '%'.$this->search.'%';

        return view('nfdocuments::livewire.library', [
            'categories' => (array) config('nfdocuments.categories'),
            'templates'  => $this->tab === 'templates'
                ? DocumentTemplate::with('layout:id,name')
                    ->when($this->search, fn ($q) => $q->where('name', 'like', $like))
                    ->orderBy('category')->orderBy('sort')->orderBy('name')->get()->groupBy('category')
                : collect(),
            'layouts'    => $this->tab === 'layouts'
                ? DocumentLayout::withCount('templates')->orderByDesc('is_default')->orderBy('name')->get()
                : collect(),
            'documents'  => $this->tab === 'documents'
                ? Document::with('template:id,name')
                    ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('context_key', 'like', $like)))
                    ->latest()->paginate(25)
                : null,
        ])->layout(config('nfdocuments.layout', 'layout::popup'));
    }
}
