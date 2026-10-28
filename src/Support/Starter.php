<?php

namespace Nfgarching\Documents\Support;

use Nfgarching\Documents\Models\DocumentLayout;

/**
 * Startausstattung für php artisan nfdocuments:install
 * – zwei Layouts und drei Beispielvorlagen (Brief, Vereinbarung, Vertrag als Word-Datei).
 */
final class Starter
{
    public static function layouts(): array
    {
        return [
            [
                'slug'       => 'briefbogen',
                'name'       => 'Briefbogen (DIN 5008 Form B)',
                'form'       => 'din5008-b',
                'is_default' => true,
                'page'       => DocumentLayout::pageDefaults('din5008-b'),
                'fields'     => array_merge(DocumentLayout::letterFields(), [
                    ['key' => 'ihr_zeichen', 'label' => 'Ihr Zeichen / Ihre Nachricht', 'type' => 'text', 'default' => '', 'required' => false],
                ]),
                'header_html' => <<<'HTML'
<table style="width:100%"><tr>
<td style="width:40%;vertical-align:top">${logo}</td>
<td style="text-align:right;vertical-align:top">
<div class="accent" style="font-size:18pt;font-weight:bold;letter-spacing:.02em">${kanzlei.name}</div>
<div style="font-size:8.5pt;letter-spacing:.12em;text-transform:uppercase;color:#555;margin-top:1mm">${kanzlei.zusatz}</div>
</td></tr></table>
HTML,
                'sender_line' => '${kanzlei.absender}',
                'info_html' => <<<'HTML'
<table>
${#ihr_zeichen}<tr><td>Ihr Zeichen</td><td>${ihr_zeichen}</td></tr>${/ihr_zeichen}
${#akte.az}<tr><td>Unser Zeichen</td><td><strong>${akte.az}</strong></td></tr>${/akte.az}
${#bearbeiter.name}<tr><td>Bearbeiter</td><td>${bearbeiter.name}</td></tr>${/bearbeiter.name}
${#kanzlei.telefon}<tr><td>Telefon</td><td>${kanzlei.telefon}</td></tr>${/kanzlei.telefon}
${#kanzlei.email}<tr><td>E-Mail</td><td>${kanzlei.email}</td></tr>${/kanzlei.email}
<tr><td>&nbsp;</td><td></td></tr>
<tr><td>Datum</td><td>${datum}</td></tr>
</table>
HTML,
                'follow_header_html' => <<<'HTML'
<table style="width:100%"><tr>
<td>${kanzlei.name}${#akte.az} · Az. ${akte.az}${/akte.az}</td>
<td style="text-align:right">Seite ${seite} von ${seiten}</td>
</tr></table>
HTML,
                'footer_html' => self::footer(),
            ],
            [
                'slug'       => 'vertrag',
                'name'       => 'Vertrag / Vereinbarung',
                'form'       => 'plain',
                'is_default' => false,
                'page'       => DocumentLayout::pageDefaults('plain'),
                'fields'     => [],
                'header_html' => '',
                'follow_header_html' => <<<'HTML'
<table style="width:100%"><tr>
<td style="color:#777">${#akte.az}Az. ${akte.az}${/akte.az}</td>
<td style="text-align:right">Seite ${seite} von ${seiten}</td>
</tr></table>
HTML,
                'footer_html' => '',
            ],
        ];
    }

    public static function footer(): string
    {
        return <<<'HTML'
<table style="width:100%;border-top:.4pt solid #999"><tr>
<td style="padding-top:1.5mm">${kanzlei.name}<br>${kanzlei.strasse}<br>${kanzlei.plz_ort}</td>
<td style="padding-top:1.5mm">${#kanzlei.telefon}Tel. ${kanzlei.telefon}<br>${/kanzlei.telefon}${#kanzlei.telefax}Fax ${kanzlei.telefax}<br>${/kanzlei.telefax}${kanzlei.email}<br>${kanzlei.web}</td>
<td style="padding-top:1.5mm">${kanzlei.bank}<br>IBAN ${kanzlei.iban}<br>BIC ${kanzlei.bic}</td>
<td style="padding-top:1.5mm">${#kanzlei.ustid}USt-IdNr.<br>${kanzlei.ustid}${/kanzlei.ustid}</td>
</tr></table>
HTML;
    }

    /** layout = Slug des Layouts; docx = Datei unter resources/templates */
    public static function templates(): array
    {
        return [
            [
                'slug'        => 'anschreiben',
                'name'        => 'Anschreiben',
                'category'    => 'brief',
                'engine'      => 'html',
                'layout'      => 'briefbogen',
                'sort'        => 10,
                'description' => 'Allgemeiner Brief auf dem Briefbogen.',
                'fields'      => [
                    ['key' => 'betreff', 'label' => 'Betreff', 'type' => 'text', 'default' => '${akte.bezeichnung}', 'required' => true],
                    ['key' => 'anrede', 'label' => 'Anrede', 'type' => 'text', 'default' => '${#empfaenger.anrede}${empfaenger.anrede}${/empfaenger.anrede}${^empfaenger.anrede}Sehr geehrte Damen und Herren${/empfaenger.anrede}', 'required' => true],
                    ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => '', 'required' => true],
                    ['key' => 'gruss', 'label' => 'Grußformel', 'type' => 'text', 'default' => 'Mit freundlichen Grüßen', 'required' => false],
                ],
                'body' => <<<'HTML'
<p><strong>${betreff}</strong></p>
<p>${anrede},</p>
<p>${text}</p>
<p>${gruss}</p>
<p><br><br>${bearbeiter.name}<br>Rechtsanwalt</p>
HTML,
            ],
            [
                'slug'        => 'ratenzahlungsvereinbarung',
                'name'        => 'Ratenzahlungsvereinbarung',
                'category'    => 'vereinbarung',
                'engine'      => 'html',
                'layout'      => 'vertrag',
                'sort'        => 20,
                'description' => 'Muster – vor Verwendung prüfen. Bei Verbrauchern und entgeltlichem Zahlungsaufschub §§ 506, 498 BGB beachten.',
                'fields'      => [
                    ['key' => 'glaeubiger', 'label' => 'Gläubiger (Name, Anschrift)', 'type' => 'textarea', 'default' => "\${mandant.name}\n\${mandant.anschrift}", 'required' => true],
                    ['key' => 'schuldner', 'label' => 'Schuldner (Name, Anschrift)', 'type' => 'textarea', 'default' => "\${gegner.name}\n\${gegner.anschrift}", 'required' => true],
                    ['key' => 'grund', 'label' => 'Forderung aus', 'type' => 'text', 'default' => '', 'required' => true, 'help' => 'z. B. „Rechnung Nr. 2026-014 vom 03.03.2026“'],
                    ['key' => 'forderung', 'label' => 'Gesamtforderung', 'type' => 'money', 'default' => '', 'required' => true],
                    ['key' => 'stand', 'label' => 'Stand', 'type' => 'date', 'default' => '${heute_iso}', 'required' => true],
                    ['key' => 'rate', 'label' => 'Monatsrate', 'type' => 'money', 'default' => '', 'required' => true],
                    ['key' => 'erste_rate', 'label' => 'Erste Rate am', 'type' => 'date', 'default' => '', 'required' => true],
                    ['key' => 'faellig_tag', 'label' => 'Fällig jeweils zum … eines Monats', 'type' => 'number', 'default' => '3', 'required' => true],
                    ['key' => 'anerkenntnis', 'label' => 'Schuldanerkenntnis aufnehmen', 'type' => 'checkbox', 'default' => '1'],
                    ['key' => 'verfall', 'label' => 'Verfallklausel aufnehmen', 'type' => 'checkbox', 'default' => '1'],
                    ['key' => 'ort', 'label' => 'Ort', 'type' => 'text', 'default' => '', 'required' => false],
                ],
                'body' => <<<'HTML'
<h1>Ratenzahlungsvereinbarung</h1>
<p>zwischen</p>
<p>${glaeubiger}</p>
<p class="text-end">– nachfolgend „Gläubiger“ –</p>
<p>und</p>
<p>${schuldner}</p>
<p class="text-end">– nachfolgend „Schuldner“ –</p>
<h2>§ 1 Forderung</h2>
<p>(1) Dem Gläubiger steht gegen den Schuldner eine Forderung aus ${grund} zu, die sich einschließlich Zinsen und Kosten zum ${stand} auf <strong>${forderung}</strong> beläuft.</p>
<p>${#anerkenntnis}</p>
<p>(2) Der Schuldner erkennt diese Forderung dem Grunde und der Höhe nach an und verzichtet auf Einwendungen und Einreden, die ihm bei Abschluss dieser Vereinbarung bekannt sind.</p>
<p>${/anerkenntnis}</p>
<h2>§ 2 Ratenzahlung</h2>
<p>(1) Der Schuldner zahlt die Forderung in monatlichen Raten von <strong>${rate}</strong>, fällig jeweils zum ${faellig_tag}. eines Monats, erstmals am ${erste_rate}. Maßgeblich ist der Eingang der Zahlung.</p>
<p>(2) Zahlungen werden gemäß § 367 Abs. 1 BGB zunächst auf die Kosten, dann auf die Zinsen und zuletzt auf die Hauptforderung angerechnet.</p>
<h2>§ 3 Stundung</h2>
<p>(1) Solange der Schuldner die Raten pünktlich und vollständig zahlt, stundet der Gläubiger die Forderung und sieht von weiteren Beitreibungsmaßnahmen ab.</p>
<p>${#verfall}</p>
<p>(2) Gerät der Schuldner mit mindestens zwei aufeinanderfolgenden Raten ganz oder teilweise in Verzug, entfällt die Stundung; die gesamte Restforderung wird sofort zur Zahlung fällig.</p>
<p>${/verfall}</p>
<h2>§ 4 Schlussbestimmungen</h2>
<p>Änderungen und Ergänzungen dieser Vereinbarung bedürfen der Textform. Sollte eine Bestimmung unwirksam sein oder werden, bleibt die Wirksamkeit der übrigen Bestimmungen unberührt.</p>
<p style="margin-top:10mm">${#ort}${ort}, ${/ort}den ${heute}</p>
<div class="nf-signatures"><div>Gläubiger</div><div>Schuldner</div></div>
HTML,
            ],
            [
                'slug'        => 'verguetungsvereinbarung',
                'name'        => 'Vergütungsvereinbarung (Stundensatz)',
                'category'    => 'vertrag',
                'engine'      => 'docx',
                'docx'        => 'verguetungsvereinbarung.docx',
                'sort'        => 30,
                'description' => 'Word-Vorlage – Muster nach § 3a RVG, vor Verwendung prüfen. Layout direkt in Word bearbeiten.',
                'fields'      => [
                    ['key' => 'mandant.name', 'label' => 'Auftraggeber', 'type' => 'text', 'required' => true],
                    ['key' => 'mandant.anschrift', 'label' => 'Anschrift Auftraggeber', 'type' => 'textarea', 'required' => true],
                    ['key' => 'angelegenheit', 'label' => 'Angelegenheit', 'type' => 'text', 'default' => '${akte.bezeichnung}', 'required' => true],
                    ['key' => 'stundensatz', 'label' => 'Stundensatz (netto)', 'type' => 'money', 'default' => '', 'required' => true],
                    ['key' => 'zeittakt', 'label' => 'Abrechnung je angefangene … Minuten', 'type' => 'number', 'default' => '15', 'required' => true],
                    ['key' => 'vorschuss', 'label' => 'Vorschuss (optional)', 'type' => 'money', 'default' => ''],
                    ['key' => 'mindestens_gesetzlich', 'label' => 'Mindestens gesetzliche Vergütung', 'type' => 'checkbox', 'default' => '1'],
                    ['key' => 'ort', 'label' => 'Ort', 'type' => 'text', 'default' => ''],
                ],
            ],
        ];
    }
}
