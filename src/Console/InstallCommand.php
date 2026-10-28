<?php

namespace Nfgarching\Documents\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Documents\Models\DocumentLayout;
use Nfgarching\Documents\Models\DocumentTemplate;
use Nfgarching\Documents\Support\Starter;

/** Legt Briefbogen, Vertragslayout und Beispielvorlagen an (vorhandene bleiben unverändert) */
class InstallCommand extends Command
{
    protected $signature = 'nfdocuments:install {--force : Vorhandene Startvorlagen überschreiben}';

    protected $description = 'Startausstattung anlegen: Briefbogen DIN 5008, Vertragslayout und Beispielvorlagen';

    public function handle(): int
    {
        $layouts = [];

        foreach (Starter::layouts() as $data) {
            $layout = DocumentLayout::firstWhere('slug', $data['slug']);

            if ($layout && ! $this->option('force')) {
                $this->line("  Briefbogen „{$layout->name}“ vorhanden – übersprungen");
            } else {
                $isDefault = $data['is_default'] && ! DocumentLayout::query()->where('is_default', true)->when($layout, fn ($q) => $q->whereKeyNot($layout->id))->exists();
                $layout = DocumentLayout::updateOrCreate(['slug' => $data['slug']], array_merge($data, ['is_default' => $isDefault]));
                $this->info("  Briefbogen „{$layout->name}“ angelegt");
            }

            $layouts[$data['slug']] = $layout->id;
        }

        $disk = Storage::disk(config('nfdocuments.storage.disk', 'local'));
        $dir = trim(config('nfdocuments.storage.directory', 'nfdocuments'), '/').'/templates';

        foreach (Starter::templates() as $data) {
            $existing = DocumentTemplate::firstWhere('slug', $data['slug']);

            if ($existing && ! $this->option('force')) {
                $this->line("  Vorlage „{$existing->name}“ vorhanden – übersprungen");
                continue;
            }

            $attrs = collect($data)->except(['layout', 'docx'])->all();
            $attrs['layout_id'] = isset($data['layout']) ? ($layouts[$data['layout']] ?? null) : null;

            if (isset($data['docx'])) {
                $path = $dir.'/'.$data['docx'];
                $disk->put($path, file_get_contents(dirname(__DIR__, 2).'/resources/templates/'.$data['docx']));
                $attrs['docx_path'] = $path;
            }

            $t = DocumentTemplate::updateOrCreate(['slug' => $data['slug']], $attrs);
            $this->info("  Vorlage „{$t->name}“ angelegt");
        }

        $this->newLine();
        $this->line('Kanzleidaten in der .env (NFDOCUMENTS_KANZLEI_*) eintragen, dann unter /'.config('nfdocuments.route_prefix').' den Briefbogen anpassen.');
        $this->line('Gotenberg prüfen: php artisan nfdocuments:check');

        return self::SUCCESS;
    }
}
