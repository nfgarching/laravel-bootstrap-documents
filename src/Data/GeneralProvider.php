<?php

namespace Nfgarching\Documents\Data;

use Illuminate\Support\Carbon;
use Nfgarching\Documents\Contracts\DataProvider;

/** Datum und angemeldeter Bearbeiter */
class GeneralProvider implements DataProvider
{
    public function placeholders(): array
    {
        return [
            'heute'             => 'Heutiges Datum (28.10.2026)',
            'heute_lang'        => 'Heutiges Datum ausgeschrieben (28. Oktober 2026)',
            'heute_iso'         => 'Heutiges Datum als Vorgabe für Datumsfelder',
            'bearbeiter.name'   => 'Name des angemeldeten Benutzers',
            'bearbeiter.email'  => 'E-Mail des angemeldeten Benutzers',
            'seite'             => 'Aktuelle Seitenzahl (nur Kopf-/Fußzeile)',
            'seiten'            => 'Gesamtzahl der Seiten (nur Kopf-/Fußzeile)',
        ];
    }

    public function values(array $context): array
    {
        $now = Carbon::now()->locale('de');
        $user = auth()->user();

        return [
            'heute'      => $now->format('d.m.Y'),
            'heute_lang' => $now->translatedFormat('j. F Y'),
            'heute_iso'  => $now->toDateString(),
            'bearbeiter' => [
                'name'  => $user?->name ?? '',
                'email' => $user?->email ?? '',
            ],
        ];
    }
}
