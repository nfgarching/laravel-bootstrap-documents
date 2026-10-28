<?php

namespace Nfgarching\Documents\Rendering;

/**
 * Platzhalter und Abschnitte – gleiche Schreibweise in HTML- und Word-Vorlagen:
 *
 *   ${mandant.name}              Wert
 *   ${#verfall} … ${/verfall}    Abschnitt nur, wenn der Wert „wahr“ ist (nicht leer, nicht 0/nein)
 *   ${^verfall} … ${/verfall}    Abschnitt nur, wenn der Wert leer bzw. „nein“ ist
 *
 * Innerhalb eines Abschnitts darf der Wert selbst wieder vorkommen:
 *   ${#akte.az}Unser Zeichen: ${akte.az}${/akte.az}
 * Steht eine Abschnittsmarke allein in einem Absatz, entfällt auch dieser Absatz.
 */
final class Placeholders
{
    public const KEY = '[A-Za-z0-9_][A-Za-z0-9_.\-]*';

    /** Alle verwendeten Schlüssel (ohne Endmarken), in Reihenfolge des Auftretens */
    public static function names(string $text): array
    {
        preg_match_all('/\$\{\s*('.self::KEY.')\s*\}/', self::plain($text), $m);

        return array_values(array_unique($m[1]));
    }

    /** Schlüssel, die als Abschnitt verwendet werden (${#key} bzw. ${^key}) */
    public static function blocks(string $text): array
    {
        preg_match_all('/\$\{\s*[#^]('.self::KEY.')\s*\}/', self::plain($text), $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * HTML-Vorlage füllen. Werte werden HTML-maskiert, Zeilenumbrüche zu <br>.
     *
     * @param  array<string, string>  $html  Schlüssel, deren Wert bereits HTML ist (z. B. logo)
     * @param  bool  $highlight  fehlende Werte markieren (Vorschau) statt leer lassen
     */
    public static function renderHtml(string $template, Values $values, array $html = [], bool $highlight = false): string
    {
        $template = self::resolveBlocks($template, $values, true);

        return preg_replace_callback('/\$\{\s*('.self::KEY.')\s*\}/', function ($m) use ($values, $html, $highlight) {
            $key = $m[1];

            if (array_key_exists($key, $html)) {
                return $html[$key];
            }

            $value = $values->get($key);

            if ($value === null || $value === '') {
                return $highlight
                    ? '<mark class="nf-missing" title="Kein Wert für ${'.e($key).'}">'.e($key).'</mark>'
                    : '';
            }

            return nl2br(e($value), false);
        }, $template);
    }

    /** Klartext füllen (z. B. Vorgabewerte von Feldern, Dateinamen) */
    public static function renderText(string $template, Values $values): string
    {
        $template = self::resolveBlocks($template, $values, false);

        return preg_replace_callback('/\$\{\s*('.self::KEY.')\s*\}/', fn ($m) => (string) $values->get($m[1]), $template);
    }

    private static function resolveBlocks(string $text, Values $values, bool $isHtml): string
    {
        if (! preg_match('/\$\{\s*[#^]/', $text)) {
            return $text;
        }

        if ($isHtml) {
            // Absatz, der nur aus einer Abschnittsmarke besteht → nur die Marke (entfällt dann ganz)
            $text = preg_replace(
                '/<p\b[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*(\$\{\s*[#^\/]'.self::KEY.'\s*\})(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/i',
                '$1',
                $text,
            );
        }

        // Innerster Abschnitt zuerst: sein Inhalt enthält keine weitere Abschnittsmarke
        $pattern = '/\$\{\s*([#^])('.self::KEY.')\s*\}((?:(?!\$\{\s*[#^\/]).)*?)\$\{\s*\/\2\s*\}/s';

        for ($i = 0; $i < 50 && preg_match($pattern, $text); $i++) {
            $text = preg_replace_callback(
                $pattern,
                fn ($m) => ($m[1] === '#') === $values->truthy($m[2]) ? $m[3] : '',
                $text,
            );
        }

        return $text;
    }

    /** Word zerlegt Text in Läufe; für die Suche nur Text betrachten */
    private static function plain(string $text): string
    {
        return str_contains($text, '<') ? html_entity_decode(strip_tags($text)) : $text;
    }
}
