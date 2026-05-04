/* ============================================================
   Editor — Notion-inspired Markdown Editor
   editor.js  |  vanilla JS, zero dependencies
   ============================================================ */

(function (global) {
  'use strict';

  /* ── SVG icon library ───────────────────────────────────── */
  var ICONS = {
    bold: '<svg viewBox="0 0 24 24"><path d="M6 4h8a4 4 0 0 1 0 8H6z"/><path d="M6 12h9a4 4 0 0 1 0 8H6z"/></svg>',
    italic: '<svg viewBox="0 0 24 24"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>',
    strikethrough: '<svg viewBox="0 0 24 24"><path d="M16 4H9a3 3 0 0 0-2.83 4"/><path d="M14 12a4 4 0 0 1 0 8H6"/><line x1="4" y1="12" x2="20" y2="12"/></svg>',
    heading: '<svg viewBox="0 0 24 24"><path d="M6 12h12"/><path d="M6 4v16"/><path d="M18 4v16"/></svg>',
    code: '<svg viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
    quote: '<svg viewBox="0 0 24 24"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>',
    'unordered-list': '<svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
    'ordered-list': '<svg viewBox="0 0 24 24"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>',
    'clean-block': '<svg viewBox="0 0 24 24"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>',
    link: '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
    image: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
    table: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="1"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 3v18"/><path d="M15 3v18"/></svg>',
    'horizontal-rule': '<svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12" stroke-width="2.5"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/></svg>',
    preview: '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
    upload: '<svg viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>',
    'dark-mode': '<svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>',
    'light-mode': '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>',
    'code-block': '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="10" y2="13"/><line x1="8" y1="17" x2="14" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
    'clear-editor': '<svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>'
  };

  /* ── Default toolbar button definitions ─────────────────── */
  var DEFAULT_TOOLBAR = [
    { name: 'bold',           title: 'Bold',           action: 'bold' },
    { name: 'italic',         title: 'Italic',         action: 'italic' },
    { name: 'strikethrough',  title: 'Strikethrough',  action: 'strikethrough' },
    { name: '|' },
    { name: 'heading',        title: 'Heading',        action: 'heading' },
    { name: '|' },
    { name: 'code',           title: 'Inline Code',    action: 'code' },
    { name: 'code-block',     title: 'Code Block',     action: 'code-block' },
    { name: 'quote',          title: 'Quote',          action: 'quote' },
    { name: '|' },
    { name: 'unordered-list', title: 'Bullet List',    action: 'unordered-list' },
    { name: 'ordered-list',   title: 'Numbered List',  action: 'ordered-list' },
    { name: 'clean-block',    title: 'Clean Block',    action: 'clean-block' },
    { name: 'clear-editor',   title: 'Clear All (⌘⌫)', action: 'clear-editor' },
    { name: '|' },
    { name: 'link',           title: 'Link',           action: 'link' },
    { name: 'image',          title: 'Image',          action: 'image' },
    { name: 'table',          title: 'Table',          action: 'table' },
    { name: 'horizontal-rule',title: 'Horizontal Rule',action: 'horizontal-rule' },
    { name: '|' },
    { name: 'preview',        title: 'Toggle Preview', action: 'preview'    },
    { name: 'dark-mode',      title: 'Toggle Dark Mode', action: 'dark-mode' }
  ];

  /* ── Default insertTexts ────────────────────────────────── */
  var DEFAULT_INSERT = {
    bold:            ['**', '**'],
    italic:          ['*', '*'],
    strikethrough:   ['~~', '~~'],
    code:            ['`', '`'],
    quote:           ['> ', ''],
    'unordered-list':['- ', ''],
    'ordered-list':  ['1. ', ''],
    heading:         ['# ', ''],
    'clean-block':   ['', ''],
    link:            ['[', '](https://)'],
    image:           ['![', '](/img/)'],
    table:           ['| Col1 | Col2 |\n|------|------|\n| ', ' |     |'],
    'horizontal-rule': ['\n---\n', '']
  };

  /* ================================================================
     Minimal Markdown → HTML renderer (no deps)
     ================================================================ */
  function renderMarkdown(src) {
    if (!src) return '';

    var html = src;

    /* Escape helper (used inside pre/code) */
    function esc(s) {
      return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    /* 1. Fenced code blocks  ```lang\n...\n```
       The block editor stores each line separately joined by \n.
       We need to match across those newlines. */
    html = html.replace(/```([^\n`]*)\n([\s\S]*?)(\n```|```)/gm, function(_, lang, code) {
      return '<pre><code' + (lang.trim() ? ' class="language-' + lang.trim() + '"' : '') + '>' +
             esc(code.trimEnd()) + '</code></pre>';
    });

    /* 2. Horizontal rules */
    html = html.replace(/^[ \t]*(?:---|\*\*\*|___)[ \t]*$/gm, '<hr>');

    /* 3. Headings */
    html = html.replace(/^(#{1,6})\s+(.+)$/gm, function(_, hashes, text) {
      var lvl = hashes.length;
      return '<h' + lvl + '>' + text.trimEnd() + '</h' + lvl + '>';
    });

    /* 4. Blockquotes (consecutive) */
    html = html.replace(/(^> .+(\n> .+)*)/gm, function(block) {
      var inner = block.replace(/^> /gm, '');
      return '<blockquote>' + inner + '</blockquote>';
    });

    /* 5. Unordered lists */
    html = html.replace(/(^[ \t]*[-*+] .+(\n[ \t]*[-*+] .+)*)/gm, function(block) {
      var items = block.replace(/^[ \t]*[-*+] (.+)/gm, '<li>$1</li>');
      return '<ul>' + items.replace(/\n/g, '') + '</ul>';
    });

    /* 6. Ordered lists */
    html = html.replace(/(^[ \t]*\d+\. .+(\n[ \t]*\d+\. .+)*)/gm, function(block) {
      var items = block.replace(/^[ \t]*\d+\. (.+)/gm, '<li>$1</li>');
      return '<ol>' + items.replace(/\n/g, '') + '</ol>';
    });

    /* 7. Tables  |col|col| */
    html = html.replace(/(^\|.+\|\n\|[-| :]+\|\n(^\|.+\|\n?)*)/gm, function(block) {
      var lines = block.trim().split('\n');
      var head = lines[0];
      var body = lines.slice(2);
      function row(line, tag) {
        var cells = line.trim().replace(/^\||\|$/g, '').split('|');
        return '<tr>' + cells.map(function(c){
          return '<' + tag + '>' + c.trim() + '</' + tag + '>';
        }).join('') + '</tr>';
      }
      return '<table><thead>' + row(head,'th') + '</thead><tbody>' +
             body.map(function(l){ return row(l,'td'); }).join('') +
             '</tbody></table>';
    });

    /* 8. Inline code */
    html = html.replace(/`([^`\n]+)`/g, function(_, c) {
      return '<code>' + esc(c) + '</code>';
    });

    /* 9. Bold + Italic */
    html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
    html = html.replace(/\*\*(.+?)\*\*/g,   '<strong>$1</strong>');
    html = html.replace(/__(.+?)__/g,         '<strong>$1</strong>');
    html = html.replace(/\*(.+?)\*/g,         '<em>$1</em>');
    html = html.replace(/_(.+?)_/g,           '<em>$1</em>');
    html = html.replace(/~~(.+?)~~/g,         '<del>$1</del>');

    /* 10. Images (before links) */
    html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1">');

    /* 11. Links */
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

    /* 12. Paragraphs — wrap bare lines not already inside a block tag */
    var blockTags = /^<\/?( h[1-6]|ul|ol|li|blockquote|pre|table|thead|tbody|tr|th|td|hr)/;
    var paras = html.split(/\n{2,}/);
    html = paras.map(function(p) {
      p = p.trim();
      if (!p) return '';
      /* Skip anything that starts with a block-level open or close tag */
      if (/^<\/?(?:h[1-6]|ul|ol|li|blockquote|pre|table|thead|tbody|tr|th|td|hr)[\s>\/]/.test(p)) return p;
      /* Replace remaining single newlines with <br> */
      return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
    }).filter(Boolean).join('\n');

    return html;
  }

  /* (cursor save/restore is handled per-block in the block system above) */

  /* ================================================================
     Block helpers — each line of markdown is a .smd-block div
     ================================================================ */

  /* Detect the visual "type" of a block from its raw markdown text */
  function blockType(text) {
    if (/^# /.test(text))      return 'h1';
    if (/^## /.test(text))     return 'h2';
    if (/^### /.test(text))    return 'h3';
    if (/^#{4,6} /.test(text)) return 'h4';
    if (/^> /.test(text))      return 'quote';
    if (/^[-*+] /.test(text))  return 'ul';
    if (/^\d+\. /.test(text))  return 'ol';
    if (/^```/.test(text))     return 'code';
    if (/^---+$/.test(text.trim())) return 'hr';
    return 'p';
  }

  /* Apply type-based data attribute to a block element */
  function applyBlockType(blockEl) {
    var content = blockEl.querySelector('.smd-block-content');
    if (!content) return;
    var type = blockType(content.textContent);
    /* If this block isn't itself a fence marker, check if it sits inside one */
    if (type !== 'code') {
      type = isInsideCodeFence(blockEl) ? 'code-inner' : type;
    }
    blockEl.dataset.type = type;
  }

  /* Returns true if the given block row sits between opening and closing ``` fences */
  function isInsideCodeFence(row) {
    var openCount = 0;
    var sibling = row.previousElementSibling;
    while (sibling) {
      var c = sibling.querySelector('.smd-block-content');
      if (c && /^```/.test(c.textContent)) openCount++;
      sibling = sibling.previousElementSibling;
    }
    /* Odd number of ``` above means we're inside a fence */
    return (openCount % 2) === 1;
  }

  /* ── Build a single block element ───────────────────────── */
  function createBlock(text, self) {
    var row = document.createElement('div');
    row.className = 'smd-block';

    /* Left gutter: add button + drag handle */
    var gutter = document.createElement('div');
    gutter.className = 'smd-block-gutter';

    var addBtn = document.createElement('button');
    addBtn.className = 'smd-block-add';
    addBtn.type      = 'button';
    addBtn.title     = 'Add block below (click)';
    addBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
    addBtn.addEventListener('mousedown', function(e) {
      e.preventDefault();
      e.stopPropagation();
      self._addBlockAfter(row, '');
    });

    var dragHandle = document.createElement('span');
    dragHandle.className = 'smd-block-drag';
    dragHandle.title     = 'Drag to reorder';
    dragHandle.innerHTML = '<svg viewBox="0 0 10 16" fill="currentColor"><circle cx="3" cy="2"  r="1.2"/><circle cx="7" cy="2"  r="1.2"/><circle cx="3" cy="8"  r="1.2"/><circle cx="7" cy="8"  r="1.2"/><circle cx="3" cy="14" r="1.2"/><circle cx="7" cy="14" r="1.2"/></svg>';

    /* ── Mouse drag-to-reorder ── */
    dragHandle.addEventListener('mousedown', function(e) {
      if (e.button !== 0) return;
      e.preventDefault();
      e.stopPropagation();
      self._startBlockDrag(e, row);
    });

    gutter.appendChild(addBtn);
    gutter.appendChild(dragHandle);

    /* Editable content area */
    var content = document.createElement('div');
    content.className       = 'smd-block-content';
    content.contentEditable = 'true';
    content.spellcheck      = true;
    if (text) content.textContent = text;

    row.appendChild(gutter);
    row.appendChild(content);
    applyBlockType(row);

    /* ── Per-block keyboard handling ── */
    content.addEventListener('input', function() {
      applyBlockType(row);

      /* Auto-complete closing ``` when user just typed an opening fence */
      var txt = content.textContent;
      if (txt === '```' || /^```\S*$/.test(txt)) {
        /* Only auto-close if this is an opening fence (even count above means not inside) */
        var openCount = 0;
        var sib = row.previousElementSibling;
        while (sib) {
          var sc = sib.querySelector('.smd-block-content');
          if (sc && /^```/.test(sc.textContent)) openCount++;
          sib = sib.previousElementSibling;
        }
        /* openCount is now the number of ``` ABOVE this line.
           If even → this is an opening fence → auto-insert closing */
        if (openCount % 2 === 0) {
          /* Only insert if next block isn't already a closing fence */
          var nextBlock = row.nextElementSibling;
          var nextContent = nextBlock && nextBlock.querySelector('.smd-block-content');
          var nextIsFence = nextContent && /^```/.test(nextContent.textContent);
          if (!nextIsFence) {
            /* Insert blank code line + closing fence after this block */
            var codeLineBlock = createBlock('', self);
            var closeBlock    = createBlock('```', self);
            var afterRow = row.nextElementSibling;
            if (afterRow) {
              self.editor.insertBefore(codeLineBlock, afterRow);
              self.editor.insertBefore(closeBlock, afterRow);
            } else {
              self.editor.appendChild(codeLineBlock);
              self.editor.appendChild(closeBlock);
            }
            self._reapplyAllBlockTypes();
            /* Move cursor to the blank code line */
            var cl = codeLineBlock.querySelector('.smd-block-content');
            if (cl) { cl.focus(); self._placeCursorAtEnd(cl); }
          }
        }
      }

      /* If this line is or was a fence marker, cascade type update to all blocks */
      if (/^```/.test(txt) || row.dataset.type === 'code') {
        self._reapplyAllBlockTypes();
      }
      self._syncToTextarea();
      self._updateWordCount();
    });

    content.addEventListener('keydown', function(e) {
      /* Tab → spaces */
      if (e.key === 'Tab') {
        e.preventDefault();
        document.execCommand('insertText', false, '  ');
        return;
      }
      /* Ctrl/Cmd shortcuts */
      if (e.ctrlKey || e.metaKey) {
        switch (e.key.toLowerCase()) {
          case 'b': e.preventDefault(); self._handleAction('bold');   return;
          case 'i': e.preventDefault(); self._handleAction('italic'); return;
          case 'k': e.preventDefault(); self._handleAction('link');   return;
          case 'a': e.preventDefault(); self._selectAll();            return;
          case 'z':
            e.preventDefault();
            self._undoRestore();
            return;
        }
      }
      /* Delete / Backspace after Ctrl/Cmd+A → clear entire editor */
      if (self._allSelected && (e.key === 'Delete' || e.key === 'Backspace')) {
        e.preventDefault();
        self._allSelected = false;
        self._undoSnapshot();
        self._clearEditor(true); /* true = skip confirm, already intentional */
        return;
      }
      /* Any other key resets the select-all flag */
      if (!e.ctrlKey && !e.metaKey) self._allSelected = false;
      /* Enter → smart split into new block */
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        var currentText = content.textContent;
        var insideFence = isInsideCodeFence(row);

        /* ── Inside a code fence: always create a plain block ── */
        if (insideFence || /^```/.test(currentText)) {
          /* Typing Enter on the opening/closing ``` line or inside the fence */
          var sel2 = window.getSelection();
          var after2 = '';
          if (sel2.rangeCount) {
            var r2 = sel2.getRangeAt(0);
            var endR2 = document.createRange();
            try {
              endR2.setStart(r2.endContainer, r2.endOffset);
              endR2.setEndAfter(content);
              after2 = endR2.toString();
              endR2.deleteContents();
            } catch(ex) {}
          }
          applyBlockType(row);
          self._addBlockAfter(row, after2);
          /* Re-apply types to all blocks after a fence change */
          self._reapplyAllBlockTypes();
          return;
        }

        /* Detect list continuation prefix */
        var ulMatch = currentText.match(/^([-*+] )/);
        var olMatch = currentText.match(/^(\d+)\. /);
        var listPrefix = '';

        if (ulMatch) {
          listPrefix = ulMatch[1];
        } else if (olMatch) {
          var nextNumber = parseInt(olMatch[1], 10) + 1;
          listPrefix = nextNumber + '. ';
        }

        /* If on an empty list item → exit the list */
        if (listPrefix) {
          var barePrefix = ulMatch ? ulMatch[1] : olMatch[0];
          if (currentText === barePrefix) {
            content.textContent = '';
            applyBlockType(row);
            self._addBlockAfter(row, '');
            self._syncToTextarea();
            return;
          }
        }

        /* Normal split */
        var sel = window.getSelection();
        var after = '';
        if (sel.rangeCount) {
          var r = sel.getRangeAt(0);
          var endR = document.createRange();
          try {
            endR.setStart(r.endContainer, r.endOffset);
            endR.setEndAfter(content);
            after = endR.toString();
            endR.deleteContents();
          } catch(ex) {}
        }

        applyBlockType(row);
        self._addBlockAfter(row, listPrefix + after);
        return;
      }
      /* Backspace on empty / at start → merge up */
      if (e.key === 'Backspace') {
        var curSel = window.getSelection();
        var atStart = curSel.rangeCount &&
                      curSel.getRangeAt(0).startOffset === 0 &&
                      curSel.getRangeAt(0).collapsed;
        var blocks = self.editor.querySelectorAll('.smd-block');
        if ((content.textContent === '' || atStart) && blocks.length > 1) {
          e.preventDefault();
          self._mergeBlockUp(row);
          return;
        }
      }
      /* Arrow up/down navigate between blocks */
      if (e.key === 'ArrowUp') {
        var prev = row.previousElementSibling;
        if (prev) {
          e.preventDefault();
          var pc = prev.querySelector('.smd-block-content');
          if (pc) { pc.focus(); self._placeCursorAtEnd(pc); }
        }
      }
      if (e.key === 'ArrowDown') {
        var next = row.nextElementSibling;
        if (next) {
          e.preventDefault();
          var nc = next.querySelector('.smd-block-content');
          if (nc) { nc.focus(); self._placeCursorAtStart(nc); }
        }
      }
    });

    /* ── Paste: always insert as plain text, split on newlines ── */
    content.addEventListener('paste', function(e) {
      e.preventDefault();

      /* Get plain text from clipboard — strip all HTML/RTF */
      var plain = (e.clipboardData || window.clipboardData).getData('text/plain');
      if (!plain) return;

      /* Normalise line endings */
      var lines = plain.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');

      if (lines.length === 1) {
        document.execCommand('insertText', false, lines[0]);
      } else {
        /* Multi-line paste — insert first chunk into current block,
           then create a new block for each remaining line */
        var sel = window.getSelection();
        var after = '';

        /* Delete selected text first */
        if (sel.rangeCount && !sel.getRangeAt(0).collapsed) {
          document.execCommand('deleteContents');
        }

        /* Get text after cursor so we can push it to the last new block */
        if (sel.rangeCount) {
          var r = sel.getRangeAt(0);
          var tailRange = document.createRange();
          try {
            tailRange.setStart(r.endContainer, r.endOffset);
            tailRange.setEndAfter(content);
            after = tailRange.toString();
            tailRange.deleteContents();
          } catch(ex) {}
        }

        /* Insert first line into current block at cursor */
        document.execCommand('insertText', false, lines[0]);
        applyBlockType(row);

        /* Create remaining lines as new blocks */
        var lastRow = row;
        for (var i = 1; i < lines.length; i++) {
          var lineText = lines[i];
          /* Append tail text to the very last line */
          if (i === lines.length - 1) lineText += after;
          var newBlock = createBlock(lineText, self);
          var nextSib = lastRow.nextElementSibling;
          if (nextSib) {
            self.editor.insertBefore(newBlock, nextSib);
          } else {
            self.editor.appendChild(newBlock);
          }
          lastRow = newBlock;
        }

        /* Focus end of last inserted block */
        var lastContent = lastRow.querySelector('.smd-block-content');
        if (lastContent) {
          lastContent.focus();
          self._placeCursorAtEnd(lastContent);
        }

        self._reapplyAllBlockTypes();
      }

      self._syncToTextarea();
      self._updateWordCount();
    });

    /* Highlight the focused block */
    content.addEventListener('focus', function() {
      self.editor.querySelectorAll('.smd-block').forEach(function(b) {
        b.classList.remove('smd-block-active');
      });
      row.classList.add('smd-block-active');
    });
    content.addEventListener('blur', function() {
      row.classList.remove('smd-block-active');
    });

    /* Click on row margin → focus block content */
    row.addEventListener('mousedown', function(e) {
      if (e.target === row) {
        e.preventDefault();
        content.focus();
        self._placeCursorAtEnd(content);
      }
    });

    return row;
  }

  /* ── Read all blocks back to plain markdown ──────────────── */
  function getPlainText(editorEl) {
    var blocks = editorEl.querySelectorAll('.smd-block-content');
    return Array.from(blocks).map(function(b) {
      return b.textContent;
    }).join('\n');
  }

  /* ── Populate editor from a markdown string ─────────────── */
  function setPlainText(editorEl, text, self) {
    editorEl.innerHTML = '';
    var lines = (text || '').split('\n');
    if (!lines.length) lines = [''];
    lines.forEach(function(line) {
      editorEl.appendChild(createBlock(line, self));
    });
  }

  /* (block system above replaces the old flat-text functions) */

  /* ================================================================
     Main Constructor
     ================================================================ */
  function Editor(options) {
    this.options  = this._mergeOptions(options || {});
    this._previewMode = false;
    this._isDirty = false;
    this._allSelected = false;
    this._uploadEndpoint = this.options.uploadEndpoint || null;

    /* Baseline: the value the editor started with (or was last saved to).
       Dirty state is determined by comparing current content to this. */
    this._savedValue = this.options.initialValue || '';
    this._clearSnapshot = null; /* single snapshot saved just before a clear */
    this._build();
    this._bindEvents();
    this._initTheme();
    this._initAutoSave();

    /* Warn on page unload if there are unsaved changes */
    var self = this;
    this._beforeUnloadHandler = function(e) {
      if (!self._isDirty) return;
      var msg = 'You have unsaved changes. Are you sure you want to leave?';
      e.preventDefault();
      e.returnValue = msg;
      return msg;
    };
    window.addEventListener('beforeunload', this._beforeUnloadHandler);

    if (this.options.autofocus) {
      setTimeout(function(){
        var first = self.editor.querySelector('.smd-block-content');
        if (first) first.focus();
      }, 50);
    }
  }

  /* ── Option merging ──────────────────────────────────────── */
  Editor.prototype._mergeOptions = function(opts) {
    var insertTexts = {};
    /* Merge defaults with user overrides */
    var key;
    for (key in DEFAULT_INSERT) {
      insertTexts[key] = DEFAULT_INSERT[key].slice();
    }
    if (opts.insertTexts) {
      for (key in opts.insertTexts) {
        insertTexts[key] = opts.insertTexts[key];
      }
    }
    return {
      element:             opts.element        || null,
      toolbar:             opts.toolbar        || DEFAULT_TOOLBAR.map(function(b){ return b.name; }),
      insertTexts:         insertTexts,
      autofocus:           opts.autofocus      !== undefined ? opts.autofocus : false,
      uploadEndpoint:      opts.uploadEndpoint || null,
      placeholder:         opts.placeholder    || 'Start writing…',
      initialValue:        opts.initialValue   || (opts.element ? opts.element.value : '') || '',
      /* Auto-save — { enabled, delay, callback, clearAfterSave } */
      autoSave:            opts.autoSave       || null,
    };
  };

  /* ── Build DOM structure ─────────────────────────────────── */
  Editor.prototype._build = function() {
    var self    = this;
    var target  = this.options.element;

    /* Wrapper */
    var wrap = document.createElement('div');
    wrap.className = 'smd-wrapper';

    /* Toolbar */
    var toolbar = this._buildToolbar();
    wrap.appendChild(toolbar);
    this.toolbar = toolbar;

    /* Body row */
    var body = document.createElement('div');
    body.className = 'smd-body';

    /* Contenteditable block container — NOT itself editable */
    var ed = document.createElement('div');
    ed.className = 'smd-editor';
    ed.setAttribute('data-placeholder', this.options.placeholder);
    ed.setAttribute('role', 'textbox');
    ed.setAttribute('aria-multiline', 'true');
    this.editor = ed;

    /* Preview pane */
    var preview = document.createElement('div');
    preview.className = 'smd-preview';
    this.preview = preview;

    /* Drag overlay */
    var overlay = document.createElement('div');
    overlay.className = 'smd-drag-overlay';
    overlay.innerHTML  =
      '<div class="smd-drag-overlay-inner">' +
        ICONS.upload +
        '<span>Drop image to upload</span>' +
      '</div>';
    this.overlay = overlay;

    /* Upload progress toast */
    var progress = document.createElement('div');
    progress.className = 'smd-upload-progress';
    progress.textContent = 'Uploading…';
    this.progressToast = progress;

    /* Auto-save status toast */
    var autoSaveToast = document.createElement('div');
    autoSaveToast.className = 'smd-autosave-toast';
    this.autoSaveToast = autoSaveToast;

    body.appendChild(ed);
    body.appendChild(preview);
    body.appendChild(overlay);
    body.appendChild(progress);
    body.appendChild(autoSaveToast);

    /* Status bar */
    var status = document.createElement('div');
    status.className = 'smd-statusbar';

    /* Left: unsaved indicator */
    var unsaved = document.createElement('span');
    unsaved.className = 'smd-unsaved';
    unsaved.innerHTML = '<svg viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>Unsaved changes';
    this.unsavedBadge = unsaved;

    /* Centre: autosave last-saved time */
    var autoSaveStatus = document.createElement('span');
    autoSaveStatus.className = 'smd-autosave-status';
    this.autoSaveStatus = autoSaveStatus;

    /* Right: word count */
    var wordCount = document.createElement('span');
    wordCount.className = 'smd-word-count';
    wordCount.textContent = '0 words';
    this.wordCount = wordCount;

    status.appendChild(unsaved);
    status.appendChild(autoSaveStatus);
    status.appendChild(wordCount);

    wrap.appendChild(body);
    wrap.appendChild(status);
    this.wrapper = wrap;

    /* Inject after the original textarea */
    if (target) {
      target.parentNode.insertBefore(wrap, target.nextSibling);
    } else {
      document.body.appendChild(wrap);
    }

    /* Set initial value — populate blocks */
    var initVal = this.options.initialValue;
    setPlainText(ed, initVal || '', self);
    this._updateWordCount();
  };

  /* ── Build toolbar from config ───────────────────────────── */
  Editor.prototype._buildToolbar = function() {
    var self = this;
    var bar  = document.createElement('div');
    bar.className = 'smd-toolbar';

    var names = this.options.toolbar;
    /* Expand string-only items against default definitions */
    names.forEach(function(item) {
      var name = typeof item === 'string' ? item : item.name;
      if (name === '|') {
        var sep = document.createElement('span');
        sep.className = 'smd-toolbar-sep';
        bar.appendChild(sep);
        return;
      }
      /* Find definition */
      var def = DEFAULT_TOOLBAR.find(function(d){ return d.name === name; });
      if (!def && typeof item === 'object') def = item;
      if (!def) return;

      var btn = document.createElement('button');
      btn.className   = 'smd-toolbar-btn';
      btn.type        = 'button';
      btn.title       = def.title || name;
      btn.dataset.action = def.action || name;
      btn.innerHTML   = ICONS[name] || '<span>' + name[0].toUpperCase() + '</span>';
      btn.addEventListener('mousedown', function(e) {
        e.preventDefault(); /* Don't steal focus from editor */
        self._handleAction(def.action || name);
      });
      bar.appendChild(btn);

      /* Store button references */
      if (name === 'preview')   self.previewBtn   = btn;
      if (name === 'dark-mode') self.darkModeBtn  = btn;
    });

    /* Spacer pushes unsaved indicator to the far right */
    var spacer = document.createElement('span');
    spacer.className = 'smd-toolbar-spacer';
    bar.appendChild(spacer);

    /* Unsaved changes indicator in toolbar */
    var toolbarUnsaved = document.createElement('span');
    toolbarUnsaved.className = 'smd-toolbar-unsaved';
    toolbarUnsaved.innerHTML =
      '<svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg>' +
      'Unsaved changes';
    bar.appendChild(toolbarUnsaved);
    this.toolbarUnsavedBadge = toolbarUnsaved;

    return bar;
  };

  /* ── Bind wrapper-level events (drag/drop, clicks on empty area) ── */
  Editor.prototype._bindEvents = function() {
    var self = this;
    var ed   = this.editor;
    var wrap = this.wrapper;

    /* Click on the editor container's empty space → focus last block */
    ed.addEventListener('mousedown', function(e) {
      if (e.target === ed) {
        var blocks = ed.querySelectorAll('.smd-block');
        if (blocks.length) {
          e.preventDefault();
          var last = blocks[blocks.length - 1].querySelector('.smd-block-content');
          if (last) { last.focus(); self._placeCursorAtEnd(last); }
        }
      }
    });

    /* ── Drag & Drop image upload ───────────────────────────
       MUST use capture phase on the wrapper — contenteditable
       elements intercept dragover/drop before they bubble,
       causing the browser to open the image instead.
    ─────────────────────────────────────────────────────── */
    var hasImageFile = function(e) {
      if (!e.dataTransfer) return false;
      var types = Array.from(e.dataTransfer.types || []);
      /* During dragover, Files aren't accessible yet — check types */
      return types.indexOf('Files') !== -1;
    };

    wrap.addEventListener('dragenter', function(e) {
      if (hasImageFile(e)) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);

    wrap.addEventListener('dragover', function(e) {
      if (hasImageFile(e)) {
        e.preventDefault();
        e.stopPropagation();
        e.dataTransfer.dropEffect = 'copy';
        wrap.classList.add('smd-drag-over');
      }
    }, true);

    wrap.addEventListener('dragleave', function(e) {
      /* Only clear when leaving the wrapper entirely */
      if (!wrap.contains(e.relatedTarget)) {
        wrap.classList.remove('smd-drag-over');
      }
    }, true);

    wrap.addEventListener('drop', function(e) {
      var files = e.dataTransfer && e.dataTransfer.files;
      if (!files || !files.length) return;
      /* Check if any dropped item is an image */
      var hasImage = false;
      for (var i = 0; i < files.length; i++) {
        if (files[i].type.startsWith('image/')) { hasImage = true; break; }
      }
      if (!hasImage) return;
      e.preventDefault();
      e.stopPropagation();
      wrap.classList.remove('smd-drag-over');

      /* Collect only image files */
      var imageFiles = [];
      for (var j = 0; j < files.length; j++) {
        if (files[j].type.startsWith('image/')) imageFiles.push(files[j]);
      }
      self._uploadImages(imageFiles);
    }, true);
  };

  /* ================================================================
     Block drag-to-reorder
     Uses mouse events on the editor container so dragging outside
     the handle still works once the drag is in progress.
     ================================================================ */
  Editor.prototype._startBlockDrag = function(e, draggedRow) {
    var self      = this;
    var editor    = this.editor;
    var startY    = e.clientY;
    var rowHeight = draggedRow.offsetHeight;

    /* Ghost: a semi-transparent clone that follows the cursor */
    var ghost = draggedRow.cloneNode(true);
    ghost.className += ' smd-block-ghost';
    var rect           = draggedRow.getBoundingClientRect();
    ghost.style.width  = draggedRow.offsetWidth + 'px';
    ghost.style.top    = rect.top  + 'px';
    ghost.style.left   = rect.left + 'px';
    /* Append inside wrapper (not body) so CSS variables inherit dark mode */
    this.wrapper.appendChild(ghost);

    /* Drop indicator line */
    var indicator = document.createElement('div');
    indicator.className = 'smd-drop-indicator';
    editor.appendChild(indicator);

    /* Hide original while dragging */
    draggedRow.classList.add('smd-block-dragging');

    /* Track which block we'd drop before */
    var dropTarget = null; /* null = append at end */

    function getBlockAtY(clientY) {
      var blocks = Array.from(editor.querySelectorAll('.smd-block:not(.smd-block-dragging)'));
      for (var i = 0; i < blocks.length; i++) {
        var rect = blocks[i].getBoundingClientRect();
        var mid  = rect.top + rect.height / 2;
        if (clientY < mid) return blocks[i]; /* insert before this one */
      }
      return null; /* insert after last */
    }

    function positionIndicator(target) {
      if (!target) {
        /* After last visible block */
        var allBlocks = editor.querySelectorAll('.smd-block:not(.smd-block-dragging)');
        var last = allBlocks[allBlocks.length - 1];
        if (last) {
          var r = last.getBoundingClientRect();
          var er = editor.getBoundingClientRect();
          indicator.style.top     = (r.bottom - er.top + editor.scrollTop) + 'px';
          indicator.style.display = 'block';
        }
      } else {
        var r  = target.getBoundingClientRect();
        var er = editor.getBoundingClientRect();
        indicator.style.top     = (r.top - er.top + editor.scrollTop) + 'px';
        indicator.style.display = 'block';
      }
    }

    function onMouseMove(ev) {
      /* Move ghost */
      var dy = ev.clientY - startY;
      ghost.style.transform = 'translateY(' + dy + 'px)';

      /* Update drop target + indicator */
      dropTarget = getBlockAtY(ev.clientY);
      positionIndicator(dropTarget);
    }

    function onMouseUp() {
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup',  onMouseUp);

      /* Remove ghost and indicator */
      ghost.parentNode && ghost.parentNode.removeChild(ghost);
      indicator.parentNode && indicator.parentNode.removeChild(indicator);

      /* Re-show original */
      draggedRow.classList.remove('smd-block-dragging');

      /* Perform the reorder */
      if (dropTarget === draggedRow || dropTarget === draggedRow.nextElementSibling) {
        return;
      }
      if (dropTarget) {
        editor.insertBefore(draggedRow, dropTarget);
      } else {
        editor.appendChild(draggedRow);
      }

      self._syncToTextarea();
      self._updateWordCount();

      /* Re-focus the moved block */
      var c = draggedRow.querySelector('.smd-block-content');
      if (c) c.focus();
    }

    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup',  onMouseUp);
  };

  /* Reapply data-type to every block — call after fence lines change */
  Editor.prototype._reapplyAllBlockTypes = function() {
    var blocks = this.editor.querySelectorAll('.smd-block');
    blocks.forEach(function(b) { applyBlockType(b); });
  };

  /* ================================================================
     Undo — single pre-clear snapshot
     Saves editor state just before a clear operation.
     Cmd/Ctrl+Z restores it once. Normal typing undo is handled
     natively by the browser's contenteditable undo stack.
     ================================================================ */
  Editor.prototype._undoSnapshot = function() {
    var lines = Array.from(
      this.editor.querySelectorAll('.smd-block-content')
    ).map(function(b) { return b.textContent; });
    this._clearSnapshot = lines.join('\n');
  };

  Editor.prototype._undoRestore = function() {
    if (!this._clearSnapshot) return;
    var content = this._clearSnapshot;
    this._clearSnapshot = null;
    setPlainText(this.editor, content, this);
    this._reapplyAllBlockTypes();
    this._syncToTextarea();
    this._updateWordCount();
    /* Focus last block */
    var blocks = this.editor.querySelectorAll('.smd-block-content');
    var last = blocks[blocks.length - 1];
    if (last) { last.focus(); this._placeCursorAtEnd(last); }
  };

  /* ── Add a new block after a given block row ─────────────── */
  Editor.prototype._addBlockAfter = function(refRow, text) {
    var newBlock = createBlock(text || '', this);
    var next = refRow.nextElementSibling;
    if (next) {
      this.editor.insertBefore(newBlock, next);
    } else {
      this.editor.appendChild(newBlock);
    }
    var content = newBlock.querySelector('.smd-block-content');
    if (content) {
      content.focus();
      this._placeCursorAtEnd(content);
    }
    this._syncToTextarea();
    this._updateWordCount();
  };

  /* ── Merge a block up into the previous one ──────────────── */
  Editor.prototype._mergeBlockUp = function(row) {
    var prev = row.previousElementSibling;
    if (!prev) return;
    var prevContent = prev.querySelector('.smd-block-content');
    var thisContent = row.querySelector('.smd-block-content');
    if (!prevContent || !thisContent) return;
    var prevLen  = prevContent.textContent.length;
    var thisText = thisContent.textContent;
    /* Append current text to previous block */
    prevContent.textContent = prevContent.textContent + thisText;
    applyBlockType(prev);
    row.parentNode.removeChild(row);
    /* Place cursor at the join point */
    prevContent.focus();
    this._placeCursorAtOffset(prevContent, prevLen);
    this._reapplyAllBlockTypes();
    this._syncToTextarea();
    this._updateWordCount();
  };

  /* ── Cursor helpers ──────────────────────────────────────── */
  Editor.prototype._placeCursorAtEnd = function(el) {
    var sel   = window.getSelection();
    var range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(false);
    sel.removeAllRanges();
    sel.addRange(range);
  };

  Editor.prototype._placeCursorAtStart = function(el) {
    var sel   = window.getSelection();
    var range = document.createRange();
    range.setStart(el, 0);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
  };

  Editor.prototype._placeCursorAtOffset = function(el, offset) {
    var sel  = window.getSelection();
    var range = document.createRange();
    var textNode = el.firstChild;
    if (!textNode || textNode.nodeType !== Node.TEXT_NODE) {
      this._placeCursorAtEnd(el);
      return;
    }
    var safeOffset = Math.min(offset, textNode.length);
    range.setStart(textNode, safeOffset);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
  };

  /* Select all content across every block */
  Editor.prototype._selectAll = function() {
    var blocks = this.editor.querySelectorAll('.smd-block-content');
    if (!blocks.length) return;

    var first = blocks[0];
    var last  = blocks[blocks.length - 1];

    /* Walk to the deepest first/last text nodes */
    function firstTextNode(el) {
      if (el.nodeType === Node.TEXT_NODE) return el;
      for (var i = 0; i < el.childNodes.length; i++) {
        var found = firstTextNode(el.childNodes[i]);
        if (found) return found;
      }
      return null;
    }
    function lastTextNode(el) {
      if (el.nodeType === Node.TEXT_NODE) return el;
      for (var i = el.childNodes.length - 1; i >= 0; i--) {
        var found = lastTextNode(el.childNodes[i]);
        if (found) return found;
      }
      return null;
    }

    var startNode = firstTextNode(first) || first;
    var endNode   = lastTextNode(last)   || last;
    var endOffset = endNode.nodeType === Node.TEXT_NODE
                    ? endNode.length
                    : endNode.childNodes.length;

    var range = document.createRange();
    try {
      range.setStart(startNode, 0);
      range.setEnd(endNode, endOffset);
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
      /* Mark all-selected so next Delete/Backspace clears */
      this._allSelected = true;
    } catch(e) {}
  };

  /* ── Sync all blocks → hidden textarea + update dirty state ─ */
  Editor.prototype._syncToTextarea = function() {
    var content = getPlainText(this.editor);
    if (this.options.element) {
      this.options.element.value = content;
    }

    var isDirty = (content !== this._savedValue);
    this._isDirty = isDirty;
    this._setBadges(isDirty);

    /* Kick the debounced auto-save on every change */
    this._scheduleAutoSave();
  };

  /* ── Show/hide both unsaved badges ──────────────────────────── */
  Editor.prototype._setBadges = function(visible) {
    if (this.unsavedBadge)        this.unsavedBadge.classList.toggle('visible', visible);
    if (this.toolbarUnsavedBadge) this.toolbarUnsavedBadge.classList.toggle('visible', visible);
  };

  /* ── Word count ──────────────────────────────────────────── */
  Editor.prototype._updateWordCount = function() {
    var text  = getPlainText(this.editor).trim();
    var words = text ? text.split(/\s+/).length : 0;
    this.wordCount.textContent = words + (words === 1 ? ' word' : ' words');
  };

  /* ================================================================
     Formatting actions
     ================================================================ */
  Editor.prototype._handleAction = function(action) {
    if (action === 'preview')      { this._togglePreview();   return; }
    if (action === 'dark-mode')    { this._toggleDarkMode();  return; }
    if (action === 'clean-block')  { this._cleanBlock();      return; }
    if (action === 'code-block')   { this._insertCodeBlock(); return; }
    if (action === 'clear-editor') { this._clearEditor();     return; }

    var pair = this.options.insertTexts[action];
    if (!pair) return;

    /* Focus active block content, or first block */
    var active = this.editor.querySelector('.smd-block-active .smd-block-content') ||
                 this.editor.querySelector('.smd-block-content');
    if (!active) return;
    active.focus();

    var sel  = window.getSelection();
    var text = sel.rangeCount ? sel.toString() : '';
    var before = pair[0];
    var after  = pair[1];

    if (text) {
      document.execCommand('insertText', false, before + text + after);
    } else {
      document.execCommand('insertText', false, before + after);
      if (after.length > 0) {
        var curSel = window.getSelection();
        if (curSel.rangeCount) {
          var r = curSel.getRangeAt(0);
          try {
            r.setStart(r.startContainer, r.startOffset - after.length);
            r.collapse(true);
            curSel.removeAllRanges();
            curSel.addRange(r);
          } catch(ex) {}
        }
      }
    }

    /* Re-detect block type */
    var activeBlock = this.editor.querySelector('.smd-block-active');
    if (activeBlock) applyBlockType(activeBlock);

    this._syncToTextarea();
    this._updateWordCount();
  };

  /* Clean block — strip leading markdown prefix from the active block */
  Editor.prototype._cleanBlock = function() {
    var activeBlock = this.editor.querySelector('.smd-block-active');
    if (!activeBlock) return;
    var content = activeBlock.querySelector('.smd-block-content');
    if (!content) return;
    content.textContent = content.textContent.replace(/^[\s>*#\-0-9.`]+/, '');
    applyBlockType(activeBlock);
    this._placeCursorAtEnd(content);
    this._syncToTextarea();
    this._updateWordCount();
  };

  /* Clear all content — toolbar button and Cmd/Ctrl+A+Delete shortcut */
  Editor.prototype._clearEditor = function(skipConfirm) {
    if (!skipConfirm) {
      if (!window.confirm('Clear all content? This cannot be undone.')) return;
    }
    this._undoSnapshot();
    /* Reset to a single empty block */
    setPlainText(this.editor, '', this);
    this._syncToTextarea();
    this._updateWordCount();
    /* Focus the empty block */
    var first = this.editor.querySelector('.smd-block-content');
    if (first) first.focus();
  };

  /* ================================================================
     Code block insertion — toolbar button
     Inserts opening ```, a blank content line, closing ```,
     then places cursor on the content line ready to type.
     ================================================================ */
  Editor.prototype._insertCodeBlock = function() {
    var activeBlock = this.editor.querySelector('.smd-block-active') ||
                      this.editor.querySelector('.smd-block:last-child');
    if (!activeBlock) return;

    var activeContent = activeBlock.querySelector('.smd-block-content');
    /* If current block is empty, reuse it as the opening fence */
    if (activeContent && activeContent.textContent.trim() === '') {
      activeContent.textContent = '```';
      applyBlockType(activeBlock);
    } else {
      /* Otherwise insert opening fence as a new block after current */
      var openBlock = createBlock('```', this);
      var nextSib = activeBlock.nextElementSibling;
      if (nextSib) this.editor.insertBefore(openBlock, nextSib);
      else         this.editor.appendChild(openBlock);
      activeBlock = openBlock;
    }

    /* Insert blank code line */
    var codeLineBlock = createBlock('', this);
    var afterOpen = activeBlock.nextElementSibling;
    if (afterOpen) this.editor.insertBefore(codeLineBlock, afterOpen);
    else           this.editor.appendChild(codeLineBlock);

    /* Insert closing fence */
    var closeBlock = createBlock('```', this);
    var afterCode = codeLineBlock.nextElementSibling;
    if (afterCode) this.editor.insertBefore(closeBlock, afterCode);
    else           this.editor.appendChild(closeBlock);

    this._reapplyAllBlockTypes();
    this._syncToTextarea();
    this._updateWordCount();

    /* Focus the blank code line, ready to type */
    var codeContent = codeLineBlock.querySelector('.smd-block-content');
    if (codeContent) {
      codeContent.focus();
      this._placeCursorAtEnd(codeContent);
    }
  };

  /* ================================================================
     Preview toggle
     ================================================================ */
  Editor.prototype._togglePreview = function() {
    this._previewMode = !this._previewMode;
    var wrap = this.wrapper;
    if (this._previewMode) {
      this.preview.innerHTML = renderMarkdown(getPlainText(this.editor));
      wrap.classList.add('smd-preview-only');
      if (this.previewBtn) this.previewBtn.classList.add('smd-active');
    } else {
      wrap.classList.remove('smd-preview-only');
      if (this.previewBtn) this.previewBtn.classList.remove('smd-active');
      setTimeout(function(){ }, 0);
    }
  };

  /* ================================================================
     Dark mode toggle
     Scoped to .smd-wrapper — never touches the host page.
     Persists preference in localStorage under 'markflow-theme'.
     ================================================================ */
  Editor.prototype._toggleDarkMode = function() {
    this._darkMode = !this._darkMode;
    this._applyDarkMode();
  };

  Editor.prototype._applyDarkMode = function() {
    var wrap = this.wrapper;
    if (this._darkMode) {
      wrap.classList.add('smd-dark');
      if (this.darkModeBtn) {
        this.darkModeBtn.innerHTML = ICONS['light-mode'];
        this.darkModeBtn.title     = 'Switch to Light Mode';
        this.darkModeBtn.classList.add('smd-active');
      }
      try { localStorage.setItem('markflow-theme', 'dark'); } catch(e) {}
    } else {
      wrap.classList.remove('smd-dark');
      if (this.darkModeBtn) {
        this.darkModeBtn.innerHTML = ICONS['dark-mode'];
        this.darkModeBtn.title     = 'Switch to Dark Mode';
        this.darkModeBtn.classList.remove('smd-active');
      }
      try { localStorage.setItem('markflow-theme', 'light'); } catch(e) {}
    }
  };

  /* ── Detect initial theme ────────────────────────────────── */
  Editor.prototype._initTheme = function() {
    var saved = null;
    try { saved = localStorage.getItem('markflow-theme'); } catch(e) {}
    if (saved === 'dark') {
      this._darkMode = true;
    } else if (saved === 'light') {
      this._darkMode = false;
    } else {
      /* Fall back to OS preference */
      this._darkMode = !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    }
    this._applyDarkMode();
  };

  /* ================================================================
     Image upload
     ================================================================ */
  /* ── Upload multiple images in parallel, insert all at once ── */
  Editor.prototype._uploadImages = function(files) {
    if (!files || !files.length) return;
    var self = this;

    /* Show a single shared toast for the whole batch */
    var label = files.length === 1
      ? 'Uploading ' + files[0].name + '…'
      : 'Uploading ' + files.length + ' images…';
    this.progressToast.textContent = label;
    this.progressToast.classList.add('visible');

    /* Run all uploads in parallel */
    var promises = Array.from(files).map(function(file) {
      return self._uploadOneImage(file);
    });

    Promise.all(promises)
      .then(function(markdownStrings) {
        /* Insert each image as its own new block */
        markdownStrings.forEach(function(md) {
          self._insertBlockAtCursor(md);
        });
      })
      .catch(function(err) {
        console.error('[Editor] Batch upload error:', err);
      })
      .finally(function() {
        self.progressToast.classList.remove('visible');
      });
  };

  /* ── Upload a single image, returns a Promise<markdownString> ── */
  Editor.prototype._uploadOneImage = function(file) {
    var self = this;

    if (!this._uploadEndpoint) {
      return Promise.resolve('![' + file.name + '](IMAGE_URL)');
    }

    var fd = new FormData();
    fd.append('file', file);

    return fetch(this._uploadEndpoint, { method: 'POST', body: fd })
      .then(function(r) {
        if (!r.ok) throw new Error('Upload failed: ' + r.statusText);
        return r.json();
      })
      .then(function(data) {
        var url = data.filename || data.url || data.src || '';
        var alt = url
          .replace(/^.*[\\/]/, '')
          .replace(/\.[^.]*$/, '')
          .replace(/[^\w\s\-]/g, '')
          .trim() || 'image';
        return '![' + alt + '](' + url + ')';
      })
      .catch(function(err) {
        console.error('[Editor] Image upload error:', err);
        return '![' + file.name + '](UPLOAD_FAILED)';
      });
  };

  /* ── Insert text as a new block after the current active block ── */
  Editor.prototype._insertBlockAtCursor = function(text) {
    var activeBlock = this.editor.querySelector('.smd-block-active') ||
                      this.editor.querySelector('.smd-block:last-child');
    if (!activeBlock) {
      /* Fallback: append to editor */
      this.editor.appendChild(createBlock(text, this));
    } else {
      this._addBlockAfter(activeBlock, text);
      /* Advance active to the newly inserted block so the next
         image lands below it, not repeatedly after the same anchor */
      var inserted = activeBlock.nextElementSibling;
      if (inserted) {
        this.editor.querySelectorAll('.smd-block').forEach(function(b) {
          b.classList.remove('smd-block-active');
        });
        inserted.classList.add('smd-block-active');
      }
    }
    this._syncToTextarea();
    this._updateWordCount();
  };

  /* ── Legacy single-cursor insert (used by toolbar image action) ── */
  Editor.prototype._insertAtCursor = function(text) {
    var active = this.editor.querySelector('.smd-block-active .smd-block-content') ||
                 this.editor.querySelector('.smd-block-content');
    if (active) active.focus();
    document.execCommand('insertText', false, text);
    var activeBlock = this.editor.querySelector('.smd-block-active');
    if (activeBlock) applyBlockType(activeBlock);
    this._syncToTextarea();
    this._updateWordCount();
  };

  /* ================================================================
     Public API
     ================================================================ */

  /** Get current markdown value, or set it */
  Editor.prototype.value = function(val) {
    if (val !== undefined) {
      setPlainText(this.editor, val, this);
      /* Programmatic set becomes the new baseline — not dirty */
      this._savedValue = val;
      this._isDirty = false;
      this._setBadges(false);
      if (this.options.element) {
        this.options.element.value = val;
      }
      this._updateWordCount();
      return this;
    }
    return getPlainText(this.editor);
  };

  /** Programmatically toggle preview */
  Editor.prototype.togglePreview = function() {
    this._togglePreview();
  };

  /* ================================================================
     Auto-save
     Spec:
       autoSave: {
         enabled:        true,
         delay:          2000,       // debounce ms
         callback:       fn(content, editorInstance),
         clearAfterSave: false
       }
     - Debounced on every content change.
     - Skips save if content unchanged since last save.
     - Supports async callbacks (Promise).
     - Blocks overlapping saves while one is in progress.
     ================================================================ */
  Editor.prototype._initAutoSave = function() {
    var cfg = this.options.autoSave;

    /* Completely disabled when omitted or enabled !== true */
    if (!cfg || cfg.enabled !== true) return;

    this._autoSaveDebounceTimer = null;
    this._autoSaveInProgress   = false;
    this._autoSaveLastContent  = this._savedValue; /* baseline = initial value */
  };

  /* Called by _syncToTextarea on every content change */
  Editor.prototype._scheduleAutoSave = function() {
    var cfg = this.options.autoSave;
    if (!cfg || cfg.enabled !== true) return;
    if (typeof cfg.callback !== 'function') return;

    var delay = typeof cfg.delay === 'number' ? cfg.delay : 2000;
    var self  = this;

    /* Reset debounce timer on every change */
    if (this._autoSaveDebounceTimer) clearTimeout(this._autoSaveDebounceTimer);

    this._autoSaveDebounceTimer = setTimeout(function() {
      self._autoSaveDebounceTimer = null;
      self._executeAutoSave();
    }, delay);
  };

  Editor.prototype._executeAutoSave = function() {
    var cfg = this.options.autoSave;
    if (!cfg || cfg.enabled !== true) return;
    if (typeof cfg.callback !== 'function') return;

    /* Block overlapping saves */
    if (this._autoSaveInProgress) return;

    var content = this.value();

    /* Skip if nothing changed since last save */
    if (content === this._autoSaveLastContent) return;

    var self = this;
    this._autoSaveInProgress = true;
    this._showAutoSaveToast('saving');

    var finish = function(success) {
      self._autoSaveInProgress = false;
      if (success) {
        self._autoSaveLastContent = content;
        self.markSaved();
        self._showAutoSaveToast('saved');
        if (cfg.clearAfterSave) {
          self._clearEditor(true /* skipConfirm */);
          /* Also wipe the synced textarea explicitly */
          if (self.options.element) self.options.element.value = '';
          self._autoSaveLastContent = '';
        }
      } else {
        self._showAutoSaveToast('error');
      }
    };

    try {
      var result = cfg.callback(content, this);
      /* Support both sync and Promise-returning callbacks */
      if (result && typeof result.then === 'function') {
        result
          .then(function()  { finish(true);  })
          .catch(function() { finish(false); });
      } else {
        finish(true);
      }
    } catch(e) {
      console.error('[Editor] autoSave callback threw:', e);
      finish(false);
    }
  };

  /* Show auto-save status toast (saving / saved / error) */
  Editor.prototype._showAutoSaveToast = function(state) {
    var toast = this.autoSaveToast;
    if (!toast) return;

    if (this._autoSaveToastTimer) clearTimeout(this._autoSaveToastTimer);

    var messages = { saving: '⟳ Saving…', saved: '✓ Saved', error: '✕ Save failed' };
    toast.textContent = messages[state] || '';
    toast.className   = 'smd-autosave-toast smd-autosave-' + state + ' visible';

    if (this.autoSaveStatus) {
      this.autoSaveStatus.textContent = state === 'saved'
        ? 'Auto-saved ' + new Date().toLocaleTimeString()
        : state === 'error' ? 'Auto-save failed' : '';
    }

    /* Auto-hide after 3 s (keep 'saving' visible until result) */
    if (state !== 'saving') {
      var self = this;
      this._autoSaveToastTimer = setTimeout(function() {
        toast.classList.remove('visible');
      }, 3000);
    }
  };

  /** Manually trigger auto-save on demand (ignores debounce + unchanged guard) */
  Editor.prototype.autoSave = function() {
    /* Force run by temporarily clearing the last-saved content guard */
    var prev = this._autoSaveLastContent;
    this._autoSaveLastContent = null;
    this._executeAutoSave();
    /* Restore if no save ran (e.g. already in progress) */
    if (this._autoSaveInProgress && this._autoSaveLastContent === null) {
      this._autoSaveLastContent = prev;
    }
    return this;
  };

  /** Mark the editor as saved — shifts baseline to current content */
  Editor.prototype.markSaved = function() {
    this._savedValue = getPlainText(this.editor);
    this._isDirty = false;
    this._setBadges(false);
    return this;
  };

  /** Returns true if content differs from the last saved baseline */
  Editor.prototype.isDirty = function() {
    return this._isDirty;
  };

  /** Destroy editor, restore original textarea */
  Editor.prototype.destroy = function() {
    window.removeEventListener('beforeunload', this._beforeUnloadHandler);
    if (this._autoSaveDebounceTimer) clearTimeout(this._autoSaveDebounceTimer);
    if (this._autoSaveToastTimer)    clearTimeout(this._autoSaveToastTimer);
    if (this.options.element) {
      this.options.element.hidden = false;
    }
    if (this.wrapper && this.wrapper.parentNode) {
      this.wrapper.parentNode.removeChild(this.wrapper);
    }
  };

  /* ── Expose globally ─────────────────────────────────────── */
  global.Editor = Editor;

}(window));


/* ================================================================
   USAGE EXAMPLES
   ================================================================

  ── 1. Basic setup ───────────────────────────────────────────────

    <textarea id="content" hidden></textarea>

    <script src="editor.js"></script>
    <link  rel="stylesheet" href="editor.css">

    <script>
      var editor = new Editor({
        element: document.getElementById("content")
      });
    </script>


  ── 2. Setting initial content via the config option ─────────────

    The cleanest way — pass initialValue in the constructor:

    <script>
      var editor = new Editor({
        element: document.getElementById("content"),
        initialValue: "# Hello World\n\nStart **writing** here."
      });
    </script>


  ── 3. Setting initial content from an existing textarea value ───

    Populate the textarea before init — Editor will pick it up:

    <textarea id="content" hidden>
# My Document

This is the *initial* content loaded from the textarea.
    </textarea>

    <script>
      var editor = new Editor({
        element: document.getElementById("content")
      });
    </script>


  ── 4. Setting content after init (via JavaScript) ───────────────

    Call .value(newContent) at any point to replace the editor content:

    <script>
      var editor = new Editor({
        element: document.getElementById("content")
      });

      // Set content programmatically (e.g. after an AJAX load)
      editor.value("# Loaded from server\n\nContent fetched via API.");

      // Or load from a fetch response:
      fetch("/api/document/42")
        .then(function(r) { return r.json(); })
        .then(function(doc) {
          editor.value(doc.body);
        });
    </script>


  ── 5. Getting content (reading back the markdown) ───────────────

    Call .value() with no arguments to read the current markdown:

    <script>
      var editor = new Editor({
        element: document.getElementById("content")
      });

      // Read on demand
      var markdown = editor.value();
      console.log(markdown);

      // Save button example
      document.getElementById("save-btn").addEventListener("click", function() {
        var content = editor.value();
        fetch("/api/save", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ body: content })
        });
      });

      // The hidden textarea stays in sync too — standard form submit works:
      // <form>
      //   <textarea id="content" name="body" hidden></textarea>
      //   ...
      // </form>
      // form.submit() will include the latest markdown in the "body" field.
    </script>


  ── 6. Full configuration example ────────────────────────────────

    <script>
      var editor = new Editor({
        element: document.getElementById("content"),

        // Initial markdown content
        initialValue: "## Draft\n\nBegin your story here…",

        // Autofocus on page load
        autofocus: true,

        // Custom placeholder text
        placeholder: "Write something amazing…",

        // Toolbar buttons (omit any you don't need)
        toolbar: [
          "bold", "italic", "strikethrough", "|",
          "heading", "|",
          "code", "quote", "|",
          "unordered-list", "ordered-list", "|",
          "link", "image", "table", "horizontal-rule", "|",
          "preview"
        ],

        // Override default wrap syntax for any action
        insertTexts: {
          image:  ["![", "](https://example.com/img/)"],
          link:   ["[link text](", ")"],
          table:  ["| A | B | C |\n|---|---|---|\n| ", " |   |   |"]
        },

        // Drag-and-drop image upload endpoint
        // Expects: POST multipart/form-data { image: File }
        // Returns: { "url": "https://cdn.example.com/img.jpg" }
        uploadEndpoint: "/api/upload/image"
      });

      // Read content later
      var md = editor.value();

      // Set content later
      editor.value("# New content\n\nHello!");

      // Toggle preview programmatically
      editor.togglePreview();

      // Tear down and restore original textarea
      editor.destroy();
    </script>

  ================================================================ */
