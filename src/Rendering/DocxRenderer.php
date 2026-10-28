<?php

namespace Nfgarching\Documents\Rendering;

use PhpOffice\PhpWord\Settings;

/**
 * Füllt eine Word-Vorlage mit PhpWord.
 *
 * In Word schreiben:
 *   ${mandant.name}                       Wert (Zeilenumbrüche bleiben erhalten)
 *   ${#verfall} … ${/verfall}             Abschnitt, entfällt bei leer/nein – Marken je in eigenem Absatz
 *   ${^verfall} … ${/verfall}             Abschnitt nur bei leer/nein
 *   ${raten.betrag} in einer Tabellenzeile   Zeile je Eintrag der Liste „raten“
 *
 * Tipp: Platzhalter in Word am Stück tippen und erst danach als Ganzes formatieren.
 */
class DocxRenderer
{
    /** Platzhalter einer Word-Datei (ohne Abschnittsmarken) */
    public function placeholders(string $path): array
    {
        $tp = new WordTemplate($path);

        return array_values(array_unique(array_filter(
            $tp->getVariables(),
            fn ($v) => preg_match('/^'.Placeholders::KEY.'$/', $v),
        )));
    }

    /** Füllt die Vorlage und liefert den Pfad einer temporären .docx */
    public function fill(string $templatePath, Values $values, bool $highlight = false): string
    {
        Settings::setOutputEscapingEnabled(true);

        $tp = new WordTemplate($templatePath);

        // 1. Abschnitte (innerste zuerst)
        foreach ($tp->prepareBlocks() as $block => $b) {
            $show = $values->truthy($b['key']) !== $b['negate'];
            $tp->cloneBlock($block, $show ? 1 : 0, true, false);
        }

        // 2. Tabellenzeilen aus Listen
        foreach ($values->lists as $list => $rows) {
            $first = null;

            foreach ($tp->getVariables() as $v) {
                if (str_starts_with($v, $list.'.')) {
                    $first = $v;
                    break;
                }
            }

            if (! $first) {
                continue;
            }

            $tp->cloneRowAndSetValues($first, array_map(function ($row) use ($list) {
                $out = [];
                foreach ((array) $row as $k => $val) {
                    $out[$list.'.'.$k] = is_scalar($val) ? (string) $val : '';
                }

                return $out;
            }, array_values($rows)));
        }

        // 3. Werte (auch in Kopf- und Fußzeilen)
        foreach (array_unique($tp->getVariables()) as $v) {
            if (! preg_match('/^'.Placeholders::KEY.'$/', $v)) {
                continue;
            }

            $value = $values->get($v);
            $tp->setValue($v, ($value === null || $value === '') && $highlight ? "[{$v}]" : (string) $value);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'nfdoc');
        @unlink($tmp);
        $out = $tmp.'.docx';
        $tp->saveAs($out);

        return $out;
    }
}
