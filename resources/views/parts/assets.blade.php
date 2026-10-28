{{-- Editor (Jodit, MIT) und Hilfsfunktionen; @assets lädt sie einmal je Seite (auch mit wire:navigate) --}}
@assets
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jodit@4.18.12/es2021/jodit.fat.min.css">
<script src="https://cdn.jsdelivr.net/npm/jodit@4.18.12/es2021/jodit.fat.min.js"></script>
<style>
  .nfd-preview { background: #d5d9df; border-radius: .375rem; overflow: hidden; position: relative; }
  .nfd-preview iframe { width: 100%; height: 100%; border: 0; display: block; }
  .nfd-preview .nfd-pages { position: absolute; right: .5rem; bottom: .5rem; }
  .nfd-ph { max-height: 22rem; overflow: auto; }
  .nfd-ph button { font-family: var(--bs-font-monospace); font-size: .8rem; }
  .nfd-code { font-family: var(--bs-font-monospace); font-size: .8rem; }
  .jodit-container:not(.jodit_inline) { border-radius: .375rem; }
  .jodit-wysiwyg mark, .jodit-wysiwyg code { background: #fff3cd; }
</style>
<script>
  // Text-Editor an eine Livewire-Eigenschaft binden: x-data="nfdocEditor('body', { height: 420 })"
  window.nfdocEditor = function (prop, opts) {
    opts = opts || {};
    return {
      editor: null,
      init() {
        const area = this.$refs.area;
        area.value = this.$wire.get(prop) || '';
        this.editor = Jodit.make(area, {
          language: 'de',
          height: opts.height || 420,
          minHeight: 100,
          toolbarAdaptive: false,
          toolbarSticky: false,
          showCharsCounter: false,
          showWordsCounter: false,
          showXPathInStatusbar: false,
          askBeforePasteHTML: false,
          askBeforePasteFromWord: false,
          defaultActionOnPaste: 'insert_clear_html',
          defaultActionOnPasteFromWord: 'insert_clear_html',
          beautifyHTML: false,
          enter: 'p',
          style: { fontFamily: opts.font || 'Arial, sans-serif', fontSize: (opts.size || 11) + 'pt' },
          controls: { paragraph: { list: { p: 'Absatz', h1: 'Titel', h2: 'Überschrift (§)', h3: 'Unterüberschrift' } } },
          buttons: opts.mini
            ? ['bold', 'italic', 'underline', '|', 'align', 'fontsize', 'brush', '|', 'table', 'image', '|', 'source']
            : ['paragraph', 'bold', 'italic', 'underline', '|', 'ul', 'ol', 'outdent', 'indent', '|', 'align', '|', 'table', 'hr', '|', 'undo', 'redo', 'eraser', '|', 'source', 'fullsize'],
        });
        let timer;
        this.editor.events.on('change', (value) => {
          clearTimeout(timer);
          timer = setTimeout(() => this.$wire.set(prop, value), opts.delay || 600);
        });
        this.editor.events.on('focus', () => { window.nfdocActive = this.editor; });
        if (!window.nfdocActive) window.nfdocActive = this.editor;
      },
      destroy() { this.editor && this.editor.destruct(); },
    };
  };

  // Platzhalter an der Cursorposition des zuletzt benutzten Editors einfügen
  window.nfdocInsert = function (key, block) {
    const text = block ? '${#' + key + '}…${/' + key + '}' : '${' + key + '}';
    const ed = window.nfdocActive;
    if (ed && document.body.contains(ed.container)) {
      ed.s.focus();
      ed.s.insertHTML(text);
    } else if (navigator.clipboard) {
      navigator.clipboard.writeText(text);
    }
  };

  // Vorschau-iframe: Seitenzahl anzeigen und Scrollposition über Neuladen hinweg halten
  window.nfdocPreview = function () {
    return {
      y: 0,
      pages: 0,
      init() {
        window.addEventListener('message', (e) => {
          const frame = this.$refs.frame;
          if (!frame || e.source !== frame.contentWindow || !e.data) return;
          if (e.data.nfdoc === 'scroll') this.y = e.data.y;
          if (e.data.nfdoc === 'ready') {
            this.pages = e.data.pages;
            frame.contentWindow.postMessage({ nfdocScrollTo: this.y }, '*');
          }
        });
      },
    };
  };
</script>
@endassets
