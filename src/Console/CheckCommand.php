<?php

namespace Nfgarching\Documents\Console;

use Illuminate\Console\Command;
use Nfgarching\Documents\DocumentManager;
use Nfgarching\Documents\Models\DocumentLayout;
use Throwable;

/** Prüft die Verbindung zu Gotenberg und erzeugt optional eine Probeseite */
class CheckCommand extends Command
{
    protected $signature = 'nfdocuments:check {--save= : Probe-PDF unter diesem Pfad speichern}';

    protected $description = 'Gotenberg-Verbindung und PDF-Erzeugung prüfen';

    public function handle(DocumentManager $docs): int
    {
        $url = config('nfdocuments.gotenberg.url');
        $this->line("Gotenberg: {$url}");

        $health = $docs->gotenberg()->health();
        $status = $health['status'] ?? 'unknown';

        if ($status !== 'up') {
            $this->error('  nicht erreichbar: '.($health['error'] ?? json_encode($health)));
            $this->line('  Läuft der Container? Stimmt NFDOCUMENTS_GOTENBERG_URL (im Docker-Netz z. B. http://gotenberg:3000)?');

            return self::FAILURE;
        }

        foreach ((array) ($health['details'] ?? []) as $module => $d) {
            $this->line("  {$module}: ".($d['status'] ?? '?'));
        }

        $layout = DocumentLayout::defaultLayout();
        $this->line('Briefbogen: '.($layout?->name ?? '– (keiner, nfdocuments:install ausführen)'));

        try {
            $start = microtime(true);
            $pdf = $docs->htmlToPdf('<p><strong>Probeseite</strong></p><p>Die PDF-Erzeugung funktioniert.</p>', $layout, title: 'Probeseite');
            $this->info(sprintf('  Probe-PDF erzeugt: %d KB in %.1f s', strlen($pdf) / 1024, microtime(true) - $start));

            if ($path = $this->option('save')) {
                file_put_contents($path, $pdf);
                $this->line("  gespeichert: {$path}");
            }
        } catch (Throwable $e) {
            $this->error('  '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
