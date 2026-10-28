<?php

namespace Nfgarching\Documents\Rendering;

use Nfgarching\Documents\Models\DocumentLayout;

/**
 * Baut aus Briefbogen + Vorlagentext + Werten eine vollständige HTML-Seite.
 *
 * Den Seitenumbruch übernimmt paged.js (CSS Paged Media) – im Browser für die
 * Live-Vorschau und in Gotenbergs Chromium für das PDF; Vorschau und Druck sehen
 * deshalb gleich aus. Kopf-/Fußzeilen und Seitenzahlen setzt danach nfdocDecorate()
 * in jede Seite (zuverlässiger als die Randfelder von paged.js).
 */
class HtmlRenderer
{
    /**
     * @param  array{mode?: 'preview'|'final', paged_src?: string, title?: string, guides?: bool}  $opts
     */
    public function render(?DocumentLayout $layout, string $body, Values $values, array $opts = []): string
    {
        $layout ??= new DocumentLayout(['form' => 'plain']);
        $s = $layout->settings();
        $preview = ($opts['mode'] ?? 'final') === 'preview';
        $guides = $preview && ($opts['guides'] ?? false);
        $pagedSrc = $opts['paged_src'] ?? 'paged.polyfill.min.js';
        $title = e($opts['title'] ?? 'Dokument');

        $logo = ($uri = $layout->logoDataUri())
            ? '<img class="nf-logo" src="'.$uri.'" alt="">'
            : ($preview ? '<span class="nf-logo-missing">[Logo]</span>' : '');

        $html = [
            'logo'   => $logo,
            'seite'  => '<span class="nf-page"></span>',
            'seiten' => '<span class="nf-pages"></span>',
        ];

        $fill = fn (?string $tpl) => Placeholders::renderHtml((string) $tpl, $values, $html, $preview);

        // Kopf-/Fußzeile werden nach dem Umbruch in jede Seite gesetzt (siehe nfdocDecorate)
        $decorate = json_encode([
            'footer'     => trim((string) $layout->footer_html) !== '' ? $fill($layout->footer_html) : null,
            'follow'     => trim((string) $layout->follow_header_html) !== '' ? $fill($layout->follow_header_html) : null,
            'top'        => (float) $s['margin_top'],
            'bottom'     => (float) $s['margin_bottom'],
            'left'       => (float) $s['margin_left'],
            'width'      => 210 - (float) $s['margin_left'] - (float) $s['margin_right'],
            'followFirst' => (bool) $s['follow_header_first'],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

        $head = $layout->isLetter()
            ? $this->letterhead($layout, $s, $fill)
            : (trim((string) $layout->header_html) !== '' ? '<div class="nf-plainhead">'.$fill($layout->header_html).'</div>' : '');

        $css = $this->css($s, $preview, $guides)."\n".(string) $layout->css;

        // Vorschau: Zoom auf Fensterbreite, Seitenzahl und Scrollposition an das Elternfenster melden
        $ready = $preview
            ? 'nfdocDecorate(); window.nfdocReady = true; nfdocZoom(); window.addEventListener("resize", nfdocZoom);'
                .' window.addEventListener("scroll", function () { parent.postMessage({ nfdoc: "scroll", y: window.scrollY }, "*"); });'
                .' window.addEventListener("message", function (e) { if (e.data && e.data.nfdocScrollTo != null) window.scrollTo(0, e.data.nfdocScrollTo); });'
                .' parent.postMessage({ nfdoc: "ready", pages: document.querySelectorAll(".pagedjs_page").length }, "*");'
            : 'nfdocDecorate(); window.nfdocReady = true;';

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{$title}</title>
<style>
{$css}
</style>
{$this->screenCss($preview)}
<script>
var NFDOC = {$decorate};
function nfdocDecorate() {
  var pages = document.querySelectorAll('.pagedjs_page'), total = pages.length;
  pages.forEach(function (page, i) {
    var sheet = page.querySelector('.pagedjs_sheet') || page;
    function place(html, cls, style) {
      var el = document.createElement('div');
      el.className = cls; el.setAttribute('style', style);
      el.innerHTML = html;
      el.querySelectorAll('.nf-page').forEach(function (n) { n.textContent = i + 1; });
      el.querySelectorAll('.nf-pages').forEach(function (n) { n.textContent = total; });
      sheet.appendChild(el);
    }
    var box = 'position:absolute;left:' + NFDOC.left + 'mm;width:' + NFDOC.width + 'mm;';
    if (NFDOC.footer) place(NFDOC.footer, 'nf-footer', box + 'top:' + (297 - NFDOC.bottom + 5) + 'mm');
    if (NFDOC.follow && (i > 0 || NFDOC.followFirst)) place(NFDOC.follow, 'nf-follow', box + 'bottom:' + (297 - NFDOC.top + 5) + 'mm');
  });
}
function nfdocZoom() {
  var p = document.querySelector('.pagedjs_page'); if (!p) return;
  var z = Math.min(1, (window.innerWidth - 24) / p.offsetWidth);
  document.documentElement.style.setProperty('--nf-zoom', z);
}
window.PagedConfig = { auto: true, after: function () { {$ready} } };
</script>
<script src="{$pagedSrc}"></script>
</head>
<body>
{$head}
<div class="nf-body">
{$fill($body)}
</div>
</body>
</html>
HTML;
    }

    private function letterhead(DocumentLayout $layout, array $s, \Closure $fill): string
    {
        $mt = (float) $s['margin_top'];
        $ml = (float) $s['margin_left'];
        $isA = $layout->form === 'din5008-a';

        $height = max(0, (float) $s['text_start'] - $mt);
        $brandTop = ($isA ? 6 : 10) - $mt;
        $brandHeight = (float) $s['address_top'] - ($isA ? 6 : 10) - 2;
        $addrTop = (float) $s['address_top'] - $mt;
        $addrLeft = (float) $s['address_left'] - $ml;
        $infoTop = (float) $s['info_top'] - $mt;
        $infoLeft = (float) $s['info_left'] - $ml;

        $sender = trim((string) $layout->sender_line) !== '' ? '<div class="nf-sender">'.$fill($layout->sender_line).'</div>' : '';

        return <<<HTML
<div class="nf-letterhead" style="height: {$height}mm">
  <div class="nf-brand" style="top: {$brandTop}mm; height: {$brandHeight}mm">{$fill($layout->header_html)}</div>
  <div class="nf-address" style="top: {$addrTop}mm; left: {$addrLeft}mm">
    <div class="nf-zone">{$sender}</div>
    <div class="nf-recipient">{$fill('${anschrift}')}</div>
  </div>
  <div class="nf-info" style="top: {$infoTop}mm; left: {$infoLeft}mm">{$fill($layout->info_html)}</div>
</div>
HTML;
    }

    /** Darstellung der Seiten in der Vorschau; media="screen" wird von paged.js nicht verarbeitet */
    private function screenCss(bool $preview): string
    {
        return $preview ? <<<'HTML'
<style media="screen">
  html { background: #d5d9df; }
  body { margin: 0; }
  .pagedjs_pages { zoom: var(--nf-zoom, 1); padding: 12px 0; }
  .pagedjs_page { background: #fff; margin: 0 auto 12px; box-shadow: 0 1px 4px rgba(0,0,0,.3); }
</style>
HTML : '';
    }

    private function css(array $s, bool $preview, bool $guides): string
    {
        $fonts = (array) config('nfdocuments.fonts', []);
        $family = $fonts[$s['font']] ?? $s['font'];
        $m = "{$s['margin_top']}mm {$s['margin_right']}mm {$s['margin_bottom']}mm {$s['margin_left']}mm";

        $folds = '';
        if (! empty($s['fold_marks'])) {
            $marks = array_merge((array) ($s['folds'] ?? []), [148.5]);   // Falz- und Lochmarke
            $images = implode(', ', array_fill(0, count($marks), 'linear-gradient(#000, #000)'));
            $sizes = implode(', ', array_map(fn ($y) => $y == 148.5 ? '8mm .25pt' : '5mm .25pt', $marks));
            $pos = implode(', ', array_map(fn ($y) => "4mm {$y}mm", $marks));
            $folds = ".pagedjs_first_page .pagedjs_sheet::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 15mm; z-index: 5;"
                ." background-image: {$images}; background-size: {$sizes}; background-position: {$pos}; background-repeat: no-repeat; }";
        }

        $screen = $preview ? <<<CSS
mark.nf-missing { background: #fff3cd; color: #997404; padding: 0 .15em; border-radius: 2px; font-size: .9em; }
.nf-logo-missing { display: inline-block; border: 1px dashed #adb5bd; color: #adb5bd; padding: 2mm 6mm; font-size: 9pt; }
CSS : '';

        $guideCss = $guides ? <<<CSS
.nf-address, .nf-info, .nf-brand { outline: .3mm dashed rgba(220,53,69,.55); }
.nf-zone { outline: .3mm dotted rgba(13,110,253,.5); }
.nf-letterhead { background: linear-gradient(rgba(13,110,253,.08), rgba(13,110,253,.08)) 0 100% / 100% .3mm no-repeat; }
CSS : '';

        return <<<CSS
@page { size: A4; margin: {$m}; }
:root { --nf-accent: {$s['accent']}; }
html, body { font-family: {$family}; font-size: {$s['font_size']}pt; line-height: {$s['line_height']}; color: {$s['color']}; }
* { box-sizing: border-box; }
p { margin: 0 0 {$s['paragraph']}mm; orphans: 2; widows: 2; }
h1, h2, h3, h4 { margin: 0 0 {$s['paragraph']}mm; line-height: 1.25; break-after: avoid; }
h1 { font-size: 1.45em; text-align: center; }
h2 { font-size: 1.15em; margin-top: 4mm; }
h3 { font-size: 1em; margin-top: 3mm; }
ul, ol { margin: 0 0 {$s['paragraph']}mm; padding-left: 6mm; }
li { margin-bottom: 1mm; }
table { border-collapse: collapse; width: 100%; margin: 0 0 {$s['paragraph']}mm; }
td, th { padding: 1mm 1.5mm; vertical-align: top; text-align: left; }
table.nf-grid td, table.nf-grid th { border: .25pt solid #888; }
.text-end, .ql-align-right { text-align: right; }
.text-center, .ql-align-center { text-align: center; }
.ql-align-justify, .nf-justify { text-align: justify; hyphens: auto; }
.nf-pagebreak { break-after: page; }
.nf-keep { break-inside: avoid; }
.nf-signatures { display: flex; gap: 15mm; margin-top: 18mm; break-inside: avoid; }
.nf-signatures > div { flex: 1; border-top: .4pt solid #000; padding-top: 1mm; font-size: .9em; }

.nf-letterhead { position: relative; }
.nf-brand { position: absolute; left: 0; right: 0; overflow: hidden; }
.nf-address { position: absolute; width: 85mm; height: 45mm; padding-left: 5mm; }
.nf-zone { height: 17.7mm; display: flex; flex-direction: column; justify-content: flex-end; }
.nf-sender { font-size: 7pt; line-height: 1.2; text-decoration: underline; padding-bottom: 1mm; white-space: nowrap; overflow: hidden; }
.nf-recipient { height: 27.3mm; overflow: hidden; line-height: 1.2; }
.nf-info { position: absolute; width: 75mm; font-size: .82em; line-height: 1.3; }
.nf-info table { margin: 0; width: auto; }
.nf-info td { padding: 0 3mm 0 0; }
.nf-logo { max-height: {$s['logo_height']}mm; max-width: 100%; }
.nf-plainhead { margin-bottom: 6mm; }
.nf-footer { font-size: 7pt; line-height: 1.3; color: #444; }
.nf-follow { font-size: 8pt; line-height: 1.3; color: #444; }
.nf-footer table, .nf-follow table { margin: 0; }
.nf-footer td, .nf-follow td { padding: 0 2mm 0 0; }
.accent { color: var(--nf-accent); }
{$folds}
{$screen}
{$guideCss}
CSS;
    }
}
