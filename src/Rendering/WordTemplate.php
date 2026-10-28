<?php

namespace Nfgarching\Documents\Rendering;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * PhpWord-TemplateProcessor mit Abschnitten ${#key} / ${^key} … ${/key}.
 *
 * PhpWord kennt nur ${name} … ${/name}; dabei darf ${name} nicht zugleich als Wert
 * im Abschnitt stehen. Deshalb werden die Marken vor dem Ausfüllen paarweise in
 * eindeutige Namen (nfblk1, nfblk2 …) umbenannt.
 */
class WordTemplate extends TemplateProcessor
{
    /**
     * Benennt die Abschnittsmarken im Haupttext um.
     *
     * @return array<string, array{key: string, negate: bool}>  [blockname => …], innerste zuerst
     */
    public function prepareBlocks(): array
    {
        $key = Placeholders::KEY;

        if (! preg_match_all('/\$\{\s*([#^\/])('.$key.')\s*\}/', $this->tempDocumentMainPart, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }

        $stack = [];
        $pairs = [];   // [open-Treffer, close-Treffer, Name]
        $n = 0;

        foreach ($m as $hit) {
            [$marker, $type, $name] = [$hit[0], $hit[1][0], $hit[2][0]];

            if ($type !== '/') {
                $stack[] = ['hit' => $marker, 'type' => $type, 'key' => $name];
                continue;
            }

            // passende offene Marke (von innen nach außen) suchen
            for ($i = count($stack) - 1; $i >= 0; $i--) {
                if ($stack[$i]['key'] === $name) {
                    $open = $stack[$i];
                    array_splice($stack, $i, 1);
                    $pairs[] = ['open' => $open, 'close' => $marker, 'block' => 'nfblk'.(++$n)];
                    break;
                }
            }
        }

        // von hinten ersetzen, damit Offsets gültig bleiben
        $edits = [];
        foreach ($pairs as $p) {
            $edits[] = [$p['open']['hit'][1], strlen($p['open']['hit'][0]), '${'.$p['block'].'}'];
            $edits[] = [$p['close'][1], strlen($p['close'][0]), '${/'.$p['block'].'}'];
        }
        usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);

        foreach ($edits as [$offset, $length, $replacement]) {
            $this->tempDocumentMainPart = substr_replace($this->tempDocumentMainPart, $replacement, $offset, $length);
        }

        $blocks = [];
        foreach ($pairs as $p) {   // Reihenfolge der Endmarken = innerste zuerst
            $blocks[$p['block']] = ['key' => $p['open']['key'], 'negate' => $p['open']['type'] === '^'];
        }

        return $blocks;
    }
}
