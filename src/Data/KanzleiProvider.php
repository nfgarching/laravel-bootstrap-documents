<?php

namespace Nfgarching\Documents\Data;

use Nfgarching\Documents\Contracts\DataProvider;

/** Stammdaten aus config('nfdocuments.kanzlei') als ${kanzlei.*} */
class KanzleiProvider implements DataProvider
{
    public const LABELS = [
        'name'     => 'Name der Kanzlei',
        'zusatz'   => 'Zusatz (z. B. Rechtsanwälte · Steuerberater)',
        'strasse'  => 'Straße',
        'plz_ort'  => 'PLZ und Ort',
        'telefon'  => 'Telefon',
        'telefax'  => 'Telefax',
        'email'    => 'E-Mail',
        'web'      => 'Internet',
        'bank'     => 'Bank',
        'iban'     => 'IBAN',
        'bic'      => 'BIC',
        'ustid'    => 'USt-IdNr.',
        'steuernr' => 'Steuernummer',
    ];

    public function placeholders(): array
    {
        return collect(static::LABELS)->mapWithKeys(fn ($l, $k) => ["kanzlei.$k" => $l])->all()
            + ['kanzlei.absender' => 'Absenderzeile (Name · Straße · PLZ Ort)'];
    }

    public function values(array $context): array
    {
        $k = (array) config('nfdocuments.kanzlei', []);
        $k['absender'] = implode(' · ', array_filter([$k['name'] ?? '', $k['strasse'] ?? '', $k['plz_ort'] ?? '']));

        return ['kanzlei' => $k];
    }
}
