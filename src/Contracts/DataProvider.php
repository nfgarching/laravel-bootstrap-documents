<?php

namespace Nfgarching\Documents\Contracts;

/**
 * Liefert Werte für Platzhalter, z. B. aus der Akte.
 *
 * Registrierung in config/nfdocuments.php unter 'providers'.
 * Die Werte dürfen verschachtelt (['mandant' => ['name' => …]]) oder mit
 * Punkten (['mandant.name' => …]) angegeben werden; Platzhalter: ${mandant.name}.
 */
interface DataProvider
{
    /**
     * Verfügbare Platzhalter mit Beschreibung – für das Einfügen-Menü der Editoren.
     *
     * @return array<string, string>  ['mandant.name' => 'Name des Mandanten', …]
     */
    public function placeholders(): array;

    /**
     * Werte für den übergebenen Kontext (z. B. ['akte' => 123]).
     * Ohne passenden Kontext ein leeres Array liefern.
     */
    public function values(array $context): array;
}
