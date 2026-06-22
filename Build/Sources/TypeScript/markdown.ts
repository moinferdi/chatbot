/**
 * Lightweight Markdown → HTML renderer for assistant messages.
 *
 * Handles bold, italic, inline code, fenced code blocks, links, images,
 * headings (rendered as styled <p>, not <h1>-<h4>, to avoid clashing with
 * the host page's heading outline), blockquotes, lists, horizontal rules,
 * GFM tables, and paragraphs.
 *
 * This is a tiny hand-rolled renderer on purpose — pulling in a full markdown
 * library for a chat bubble would be overkill and a CSP/size burden. Output
 * is escaped first, then re-applied as known-safe HTML.
 */

const HTML_ESCAPE: ReadonlyArray<readonly [RegExp, string]> = [
  [/&/g, '&amp;'],
  [/</g, '&lt;'],
  [/>/g, '&gt;'],
];

const BLOCK_TAG_RE = /^<(h[1-4]|ul|ol|li|pre|blockquote|hr|table|p class="cb-md-h)/;

/**
 * Render a markdown string to HTML. Returns '' for empty input.
 */
export function renderMarkdown(text: string): string {
  if (!text) {
    return '';
  }

  // 1. Escape HTML first so user/assistant text can't inject markup.
  let html = text;
  for (const [pattern, replacement] of HTML_ESCAPE) {
    html = html.replace(pattern, replacement);
  }

  // 2. Fenced code blocks — stash so later transforms don't touch them.
  const codeBlocks: string[] = [];
  html = html.replace(/```(\w*)\n?([\s\S]*?)```/g, (_match, _lang, code) => {
    const trimmed = (code as string).replace(/^\n|\n$/g, '');
    codeBlocks.push(trimmed);
    return `\u0000CODEBLOCK${codeBlocks.length - 1}\u0000`;
  });

  // 3. GFM tables — detected before inline transforms so that bold / code /
  //    links inside cells are still applied by the steps below (same trick
  //    the list transforms use). Each table is emitted as a single line so
  //    the final wrapParagraphs pass treats it as one block.
  html = processTables(html);

  // 4. Inline code.
  html = html.replace(/`([^`]+)`/g, (_m, code) => `<code>${code}</code>`);

  // 5. Bold + italic (*** / ___ before ** / __ before * / _).
  html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
  html = html.replace(/___(.+?)___/g, '<strong><em>$1</em></strong>');
  html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');

  // 6. Images before links so '![' isn't caught by the link rule.
  html = html.replace(
    /!\[([^\]]*)\]\(([^)]+)\)/g,
    '<img src="$2" alt="$1" style="max-width:100%">',
  );

  // 7. Links.
  html = html.replace(
    /\[([^\]]+)\]\(([^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener">$1</a>',
  );

  // 8. Headings — rendered as <p class="cb-md-hN"> rather than <hN> so they
  //    don't inject heading elements into the host page's DOM outline.
  //    Styled as headings via CSS; colour inherited from .cb-message--assistant.
  //    Longest first so '##' isn't eaten by '#'.
  html = html.replace(/^#### (.+)$/gm, '<p class="cb-md-h4">$1</p>');
  html = html.replace(/^### (.+)$/gm, '<p class="cb-md-h3">$1</p>');
  html = html.replace(/^## (.+)$/gm, '<p class="cb-md-h2">$1</p>');
  html = html.replace(/^# (.+)$/gm, '<p class="cb-md-h1">$1</p>');

  // 9. Horizontal rules.
  html = html.replace(/^(---|\*\*\*|___)\s*$/gm, '<hr>');

  // 10. Blockquotes (note: '>' was escaped to '&gt;' in step 1).
  html = html.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');
  html = html.replace(/<\/blockquote>\n<blockquote>/g, '\n');

  // 11. Unordered lists.
  html = html.replace(/^[\-\*] (.+)$/gm, '<li>$1</li>');
  html = html.replace(/((?:<li>.*<\/li>\n?)+)/g, '<ul>$1</ul>');

  // 12. Ordered lists.
  html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');

  // 13. Restore fenced code blocks.
  html = html.replace(/\u0000CODEBLOCK(\d+)\u0000/g, (_m, i) => {
    return `<pre><code>${codeBlocks[Number(i)]}</code></pre>`;
  });

  // 14. Wrap stray text blocks in <p>…</p>.
  return wrapParagraphs(html);
}

/** Wrap remaining free text lines into <p> tags, leaving block tags alone. */
function wrapParagraphs(html: string): string {
  const lines = html.split('\n');
  const result: string[] = [];
  let para: string[] = [];

  const flush = (): void => {
    if (para.length) {
      result.push(`<p>${para.join('\n')}</p>`);
      para = [];
    }
  };

  for (const line of lines) {
    const trimmed = line.trim();
    if (trimmed === '') {
      flush();
    } else if (BLOCK_TAG_RE.test(trimmed)) {
      flush();
      result.push(trimmed);
    } else {
      para.push(line);
    }
  }
  flush();

  return result.join('\n');
}

// ── GFM tables ───────────────────────────────────────────────────────────────
//
// A table is a header row, a separator row of dashes/colons, and zero or more
// body rows — every row delimited by '|' (leading/trailing pipe optional but
// common). Cells may contain inline markdown (handled by the later steps);
// literal pipes in a cell must be escaped as `\|`.

type Alignment = 'left' | 'center' | 'right' | null;

/** A separator cell is dashes optionally wrapped in colons, plus spaces. */
const SEP_CELL_RE = /^\s*:?-+:?\s*$/;

/** Placeholder used to protect escaped pipes while splitting rows. */
const ESCAPED_PIPE = '\u0001PIPE\u0001';

/** True if a line looks like a table row (starts with '|' after trim). */
function isTableRow(line: string): boolean {
  return line.trim().startsWith('|');
}

/** True if a line is a valid table separator row (every cell is dashes/colons). */
function isTableSeparator(line: string): boolean {
  const trimmed = line.trim();
  if (!trimmed.startsWith('|')) {
    return false;
  }
  const cells = stripPipes(trimmed).split('|');
  // Need at least one cell, and every cell must be a separator cell.
  return cells.length > 0 && cells.every((c) => SEP_CELL_RE.test(c));
}

/** Remove one leading and one trailing '|' from a row, after trimming. */
function stripPipes(line: string): string {
  let s = line.trim();
  if (s.startsWith('|')) {
    s = s.slice(1);
  }
  if (s.endsWith('|')) {
    s = s.slice(0, -1);
  }
  return s;
}

/** Split a row into cell contents, respecting `\|` escapes; cells trimmed. */
function splitRow(line: string): string[] {
  const stripped = stripPipes(line).replace(/\\\|/g, ESCAPED_PIPE);
  return stripped
    .split('|')
    .map((c) => c.replace(new RegExp(ESCAPED_PIPE, 'g'), '|').trim());
}

/** Per-column alignment derived from the separator row. */
function parseAlignments(separator: string): Alignment[] {
  return stripPipes(separator)
    .split('|')
    .map((cell) => {
      const c = cell.trim();
      const left = c.startsWith(':');
      const right = c.endsWith(':');
      if (left && right) return 'center';
      if (right) return 'right';
      if (left) return 'left';
      return null;
    });
}

/** Build an inline `style="text-align:…"` for an alignment, or '' for default. */
function alignStyle(a: Alignment): string {
  return a ? ` style="text-align:${a}"` : '';
}

/** Build a single-line <table>…</table> from parsed rows. */
function buildTable(
  headerCells: string[],
  aligns: Alignment[],
  bodyRows: string[][],
): string {
  const head = headerCells
    .map((c, i) => `<th${alignStyle(aligns[i] ?? null)}>${c}</th>`)
    .join('');
  const body = bodyRows
    .map((row) =>
      row
        .map((c, i) => `<td${alignStyle(aligns[i] ?? null)}>${c}</td>`)
        .join(''),
    )
    .map((cells) => `<tr>${cells}</tr>`)
    .join('');
  return `<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
}

/** Scan lines and replace GFM table blocks with single-line <table> HTML. */
function processTables(html: string): string {
  const lines = html.split('\n');
  const out: string[] = [];
  let i = 0;

  while (i < lines.length) {
    const header = lines[i] ?? '';
    const separator = lines[i + 1] ?? '';

    if (isTableRow(header) && isTableSeparator(separator)) {
      const aligns = parseAlignments(separator);
      const headerCells = splitRow(header);

      let j = i + 2;
      const bodyRows: string[][] = [];
      while (j < lines.length) {
        const row = lines[j] ?? '';
        if (row.trim() === '' || !isTableRow(row)) {
          break;
        }
        bodyRows.push(splitRow(row));
        j++;
      }

      out.push(buildTable(headerCells, aligns, bodyRows));
      i = j;
    } else {
      out.push(header);
      i++;
    }
  }

  return out.join('\n');
}
