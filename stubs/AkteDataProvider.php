<?php

namespace App\Documents;

use Illuminate\Support\Facades\Gate;
use Nfgarching\Documents\Contracts\DataProvider;

/**
 * BEISPIEL – an die eigenen Models anpassen.
 *
 * Liefert Platzhalter aus der Akte: ${akte.az}, ${mandant.name}, ${gegner.anschrift},
 * ${empfaenger.anschrift} …
 *
 * Kontext beim Öffnen:  ['akte' => $akte->id]
 *                       optional 'empfaenger' => $beteiligter->id (sonst der Mandant)
 *
 * Angenommenes Schema (wie beim Mailer, bitte anpassen):
 *   Akte  (aktenzeichen, bezeichnung)  hasMany  Beteiligter (rolle, anrede, name, firma, strasse, plz, ort, email)
 */
class AkteDataProvider implements DataProvider
{
    public function placeholders(): array
    {
        $out = [
            'akte.az'          => 'Aktenzeichen',
            'akte.bezeichnung' => 'Bezeichnung der Akte (z. B. Müller ./. Schmidt)',
        ];

        foreach (['mandant' => 'Mandant', 'gegner' => 'Gegner', 'empfaenger' => 'Empfänger'] as $k => $l) {
            $out += [
                "$k.name"      => "$l: Name",
                "$k.anrede"    => "$l: Briefanrede (Sehr geehrte Frau …)",
                "$k.anschrift" => "$l: Anschrift (mehrzeilig, ohne Name)",
                "$k.email"     => "$l: E-Mail",
            ];
        }

        return $out;
    }

    public function values(array $context): array
    {
        $akte = $this->akte($context);

        if (! $akte) {
            return [];
        }

        $beteiligte = $akte->beteiligte;
        $mandant = $beteiligte->first(fn ($b) => in_array(mb_strtolower((string) $b->rolle), ['mandant', 'mandantin']));
        $gegner = $beteiligte->first(fn ($b) => in_array(mb_strtolower((string) $b->rolle), ['gegner', 'gegnerin']));
        $empfaenger = isset($context['empfaenger']) ? $beteiligte->firstWhere('id', $context['empfaenger']) : $mandant;

        return [
            'akte' => [
                'az'          => $akte->aktenzeichen,
                'bezeichnung' => $akte->bezeichnung,
            ],
            'mandant'    => $this->person($mandant),
            'gegner'     => $this->person($gegner),
            'empfaenger' => $this->person($empfaenger, withName: true),
        ];
    }

    /** Für das Anschriftfeld: $withName = Name/Firma in den ersten Zeilen */
    protected function person($b, bool $withName = false): array
    {
        if (! $b) {
            return [];
        }

        $street = trim((string) $b->strasse);
        $city = trim($b->plz.' '.$b->ort);
        $address = implode("\n", array_filter([$street, $city]));
        $nameLines = implode("\n", array_filter([$b->firma, $b->name]));

        return [
            'name'      => $b->firma ?: $b->name,
            'anrede'    => $this->salutation($b),
            'anschrift' => $withName ? implode("\n", array_filter([$nameLines, $address])) : $address,
            'email'     => $b->email,
        ];
    }

    protected function salutation($b): string
    {
        $nachname = trim(str_contains((string) $b->name, ',') ? strstr($b->name, ',', true) : (string) strrchr(' '.$b->name, ' '));

        return match (mb_strtolower((string) $b->anrede)) {
            'frau'  => "Sehr geehrte Frau {$nachname}",
            'herr'  => "Sehr geehrter Herr {$nachname}",
            default => 'Sehr geehrte Damen und Herren',
        };
    }

    protected function akte(array $context)
    {
        $id = $context['akte'] ?? null;

        if (! $id) {
            return null;
        }

        $akte = \App\Models\Akte::with('beteiligte')->find($id);

        return $akte && Gate::allows('view', $akte) ? $akte : null;
    }
}
