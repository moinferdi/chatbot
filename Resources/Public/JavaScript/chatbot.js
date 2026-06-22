"use strict";
(() => {
  // Sources/TypeScript/markdown.ts
  var HTML_ESCAPE = [
    [/&/g, "&amp;"],
    [/</g, "&lt;"],
    [/>/g, "&gt;"],
  ];
  var BLOCK_TAG_RE = /^<(h[1-4]|ul|ol|li|pre|blockquote|hr|table)/;
  function renderMarkdown(text) {
    if (!text) {
      return "";
    }
    let html = text;
    for (const [pattern, replacement] of HTML_ESCAPE) {
      html = html.replace(pattern, replacement);
    }
    const codeBlocks = [];
    html = html.replace(/```(\w*)\n?([\s\S]*?)```/g, (_match, _lang, code) => {
      const trimmed = code.replace(/^\n|\n$/g, "");
      codeBlocks.push(trimmed);
      return `\0CODEBLOCK${codeBlocks.length - 1}\0`;
    });
    html = processTables(html);
    html = html.replace(/`([^`]+)`/g, (_m, code) => `<code>${code}</code>`);
    html = html.replace(/\*\*\*(.+?)\*\*\*/g, "<strong><em>$1</em></strong>");
    html = html.replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>");
    html = html.replace(/\*(.+?)\*/g, "<em>$1</em>");
    html = html.replace(/___(.+?)___/g, "<strong><em>$1</em></strong>");
    html = html.replace(/__(.+?)__/g, "<strong>$1</strong>");
    html = html.replace(
      /!\[([^\]]*)\]\(([^)]+)\)/g,
      '<img src="$2" alt="$1" style="max-width:100%">'
    );
    html = html.replace(
      /\[([^\]]+)\]\(([^)]+)\)/g,
      '<a href="$2" target="_blank" rel="noopener">$1</a>'
    );
    html = html.replace(/^#### (.+)$/gm, "<h4>$1</h4>");
    html = html.replace(/^### (.+)$/gm, "<h3>$1</h3>");
    html = html.replace(/^## (.+)$/gm, "<h2>$1</h2>");
    html = html.replace(/^# (.+)$/gm, "<h1>$1</h1>");
    html = html.replace(/^(---|\*\*\*|___)\s*$/gm, "<hr>");
    html = html.replace(/^&gt; (.+)$/gm, "<blockquote>$1</blockquote>");
    html = html.replace(/<\/blockquote>\n<blockquote>/g, "\n");
    html = html.replace(/^[\-\*] (.+)$/gm, "<li>$1</li>");
    html = html.replace(/((?:<li>.*<\/li>\n?)+)/g, "<ul>$1</ul>");
    html = html.replace(/^\d+\. (.+)$/gm, "<li>$1</li>");
    html = html.replace(/\u0000CODEBLOCK(\d+)\u0000/g, (_m, i) => {
      return `<pre><code>${codeBlocks[Number(i)]}</code></pre>`;
    });
    return wrapParagraphs(html);
  }
  function wrapParagraphs(html) {
    const lines = html.split("\n");
    const result = [];
    let para = [];
    const flush = () => {
      if (para.length) {
        result.push(`<p>${para.join("\n")}</p>`);
        para = [];
      }
    };
    for (const line of lines) {
      const trimmed = line.trim();
      if (trimmed === "") {
        flush();
      } else if (BLOCK_TAG_RE.test(trimmed)) {
        flush();
        result.push(trimmed);
      } else {
        para.push(line);
      }
    }
    flush();
    return result.join("\n");
  }
  var SEP_CELL_RE = /^\s*:?-+:?\s*$/;
  var ESCAPED_PIPE = "PIPE";
  function isTableRow(line) {
    return line.trim().startsWith("|");
  }
  function isTableSeparator(line) {
    const trimmed = line.trim();
    if (!trimmed.startsWith("|")) {
      return false;
    }
    const cells = stripPipes(trimmed).split("|");
    return cells.length > 0 && cells.every((c) => SEP_CELL_RE.test(c));
  }
  function stripPipes(line) {
    let s = line.trim();
    if (s.startsWith("|")) {
      s = s.slice(1);
    }
    if (s.endsWith("|")) {
      s = s.slice(0, -1);
    }
    return s;
  }
  function splitRow(line) {
    const stripped = stripPipes(line).replace(/\\\|/g, ESCAPED_PIPE);
    return stripped
      .split("|")
      .map((c) => c.replace(new RegExp(ESCAPED_PIPE, "g"), "|").trim());
  }
  function parseAlignments(separator) {
    return stripPipes(separator)
      .split("|")
      .map((cell) => {
        const c = cell.trim();
        const left = c.startsWith(":");
        const right = c.endsWith(":");
        if (left && right) return "center";
        if (right) return "right";
        if (left) return "left";
        return null;
      });
  }
  function alignStyle(a) {
    return a ? ` style="text-align:${a}"` : "";
  }
  function buildTable(headerCells, aligns, bodyRows) {
    const head = headerCells
      .map((c, i) => `<th${alignStyle(aligns[i] ?? null)}>${c}</th>`)
      .join("");
    const body = bodyRows
      .map((row) =>
        row
          .map((c, i) => `<td${alignStyle(aligns[i] ?? null)}>${c}</td>`)
          .join("")
      )
      .map((cells) => `<tr>${cells}</tr>`)
      .join("");
    return `<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
  }
  function processTables(html) {
    const lines = html.split("\n");
    const out = [];
    let i = 0;
    while (i < lines.length) {
      const header = lines[i] ?? "";
      const separator = lines[i + 1] ?? "";
      if (isTableRow(header) && isTableSeparator(separator)) {
        const aligns = parseAlignments(separator);
        const headerCells = splitRow(header);
        let j = i + 2;
        const bodyRows = [];
        while (j < lines.length) {
          const row = lines[j] ?? "";
          if (row.trim() === "" || !isTableRow(row)) {
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
    return out.join("\n");
  }

  // Sources/TypeScript/render.ts
  function scrollToBottom(container) {
    container.scrollTop = container.scrollHeight;
  }
  function autoResize(input) {
    input.style.height = "auto";
    input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
  }
  function setInputEnabled(input, sendBtn, enabled) {
    input.disabled = !enabled;
    sendBtn.disabled = !enabled;
  }
  function removeLoading(container) {
    container.querySelector(".cb-message--loading")?.remove();
  }
  function showLoading(container) {
    const loader = document.createElement("div");
    loader.className = "cb-message cb-message--loading";
    loader.innerHTML = '<span class="cb-typing-dot"></span>'.repeat(3);
    container.appendChild(loader);
    scrollToBottom(container);
  }
  function showError(container, text) {
    const div = document.createElement("div");
    div.className = "cb-message cb-message--system cb-message--error";
    div.textContent = text;
    container.appendChild(div);
    scrollToBottom(container);
  }
  function createStreamMessage(container, avatarUrl) {
    const row = document.createElement("div");
    row.className = "cb-message-row cb-message-row--assistant";
    if (avatarUrl) {
      row.appendChild(buildAvatar(avatarUrl));
    }
    const bubble = document.createElement("div");
    bubble.className = "cb-message cb-message--assistant cb-message--streaming";
    bubble.textContent = "";
    row.appendChild(bubble);
    container.appendChild(row);
    return bubble;
  }
  function renderMessageDOM(container, role, content, avatarUrl, extraClass) {
    if (role === "assistant") {
      const row = document.createElement("div");
      row.className = "cb-message-row cb-message-row--assistant";
      if (avatarUrl) {
        row.appendChild(buildAvatar(avatarUrl));
      }
      const bubble = document.createElement("div");
      bubble.className = appendClass(
        "cb-message cb-message--assistant",
        extraClass
      );
      bubble.innerHTML = renderMarkdown(content);
      row.appendChild(bubble);
      container.appendChild(row);
    } else if (role === "user") {
      const row = document.createElement("div");
      row.className = "cb-message-row cb-message-row--user";
      const bubble = document.createElement("div");
      bubble.className = appendClass("cb-message cb-message--user", extraClass);
      bubble.textContent = content;
      row.appendChild(bubble);
      container.appendChild(row);
    } else {
      const div = document.createElement("div");
      div.className = appendClass(`cb-message cb-message--${role}`, extraClass);
      div.textContent = content;
      container.appendChild(div);
    }
    scrollToBottom(container);
  }
  function finalizeStreamMessage(bubble, content) {
    bubble.classList.remove("cb-message--streaming");
    bubble.innerHTML = renderMarkdown(content);
  }
  function appendClass(base, extra) {
    return extra ? `${base} ${extra}` : base;
  }
  function buildAvatar(avatarUrl) {
    const img = document.createElement("img");
    img.className = "cb-avatar";
    img.src = avatarUrl;
    img.alt = "";
    img.setAttribute("aria-hidden", "true");
    return img;
  }
  function queryElements(widget2) {
    const launcher = widget2.querySelector(".cb-launcher");
    const panel = widget2.querySelector("#cb-panel");
    const messagesContainer = widget2.querySelector("#cb-messages");
    const inputEl = widget2.querySelector("#cb-input");
    const sendBtn = widget2.querySelector("#cb-send");
    const closeBtn = widget2.querySelector(".cb-panel__close");
    const expandBtn = widget2.querySelector(".cb-panel__expand");
    const liveRegion = widget2.querySelector("#cb-live-region");
    if (
      !launcher ||
      !panel ||
      !messagesContainer ||
      !inputEl ||
      !sendBtn ||
      !closeBtn ||
      !expandBtn
    ) {
      return null;
    }
    return {
      widget: widget2,
      launcher,
      panel,
      messagesContainer,
      inputEl,
      sendBtn,
      closeBtn,
      expandBtn,
      liveRegion,
    };
  }

  // Sources/TypeScript/types.ts
  var WIDGET_ID = "moinferdi-chatbot";
  var STORAGE_KEY = "moinferdi-chatbot-session";
  var SSE_DONE = "[DONE]";
  var SSE_DATA_PREFIX = "data: ";

  // Sources/TypeScript/storage.ts
  function save(session) {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    } catch {}
  }
  function restore() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return null;
      }
      const data = JSON.parse(raw);
      if (!data || !Array.isArray(data.messages)) {
        return null;
      }
      return {
        messages: data.messages,
        isOpen: Boolean(data.isOpen),
        isExpanded: Boolean(data.isExpanded),
      };
    } catch {
      try {
        sessionStorage.removeItem(STORAGE_KEY);
      } catch {}
      return null;
    }
  }

  // Sources/TypeScript/api.ts
  var ERROR_NETWORK = "Network error. Check your connection.";
  var ERROR_STREAM_LOST = "Stream connection lost. Please try again.";
  var ERROR_EMPTY = "Empty response from assistant.";
  var ERROR_GENERIC = "Something went wrong. Please try again.";
  async function sendStream(opts) {
    const body = {
      messages: opts.messages,
      model: opts.config.model,
      stream: true,
    };
    let response;
    try {
      response = await fetch(opts.config.endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
        signal: opts.signal,
      });
    } catch (err) {
      if (isAbortError(err)) {
        return { kind: "aborted" };
      }
      opts.onError(ERROR_NETWORK);
      return { kind: "error", message: ERROR_NETWORK };
    }
    if (!response.ok) {
      const message = (await readErrorMessage(response)) ?? ERROR_GENERIC;
      opts.onError(message);
      return { kind: "error", message };
    }
    const contentType = response.headers.get("content-type") ?? "";
    if (!contentType.includes("text/event-stream")) {
      const data = await response.json();
      const reply = data.content ?? data.message ?? "No response.";
      opts.onDelta(reply);
      opts.onDone(reply);
      return { kind: "ok" };
    }
    return consumeSse(response, opts);
  }
  async function consumeSse(response, opts) {
    const reader = response.body?.getReader();
    if (!reader) {
      opts.onError(ERROR_STREAM_LOST);
      return { kind: "error", message: ERROR_STREAM_LOST };
    }
    const decoder = new TextDecoder();
    let buffer = "";
    let full = "";
    for (;;) {
      const { done, value } = await reader.read();
      if (done) {
        break;
      }
      buffer += decoder.decode(value, { stream: true });
      const lines = buffer.split("\n");
      buffer = lines.pop() ?? "";
      for (const line of lines) {
        if (!line.startsWith(SSE_DATA_PREFIX)) {
          continue;
        }
        const payload = line.slice(SSE_DATA_PREFIX.length).trim();
        if (payload === SSE_DONE) {
          if (full) {
            opts.onDone(full);
          } else {
            opts.onError(ERROR_EMPTY);
          }
          return { kind: "ok" };
        }
        const frame = safeParse(payload);
        if (!frame) {
          continue;
        }
        if (frame.error) {
          const message =
            typeof frame.error === "string" ? frame.error : ERROR_GENERIC;
          opts.onError(message);
          return { kind: "error", message };
        }
        const choice = frame.choices?.[0];
        const delta = choice?.delta?.content ?? choice?.message?.content ?? "";
        if (delta) {
          full += delta;
          opts.onDelta(delta);
        }
      }
    }
    if (full) {
      opts.onDone(full);
      return { kind: "ok" };
    }
    opts.onError(ERROR_EMPTY);
    return { kind: "error", message: ERROR_EMPTY };
  }
  async function readErrorMessage(response) {
    try {
      const data = await response.json();
      if (typeof data.error === "string") {
        return data.error;
      }
      return null;
    } catch {
      return null;
    }
  }
  function safeParse(payload) {
    try {
      return JSON.parse(payload);
    } catch {
      return null;
    }
  }
  function isAbortError(err) {
    return err instanceof DOMException && err.name === "AbortError";
  }

  // Sources/TypeScript/controller.ts
  var ChatbotController = class {
    constructor(widget2, config) {
      // ── Widget state ──────────────────────────────────────────────────────
      this.isOpen = false;
      this.isExpanded = false;
      this.isLoading = false;
      this.messages = [];
      this.abortController = null;
      const els = queryElements(widget2);
      if (!els) {
        return;
      }
      this.els = els;
      this.config = config;
      this.restoreSession();
      this.bindEvents();
    }
    // ── Session persistence ─────────────────────────────────────────────────
    /** Persist user/assistant messages + UI flags. Transient errors are skipped. */
    persist() {
      const session = {
        messages: this.messages.filter(
          (m) => m.role === "user" || m.role === "assistant"
        ),
        isOpen: this.isOpen,
        isExpanded: this.isExpanded,
      };
      save(session);
    }
    /** Replay a stored session into the DOM and restore UI flags. */
    restoreSession() {
      const data = restore();
      if (!data) {
        return;
      }
      this.messages = data.messages;
      for (const msg of data.messages) {
        renderMessageDOM(
          this.els.messagesContainer,
          msg.role,
          msg.content,
          this.config.avatarUrl
        );
      }
      if (data.isOpen) {
        this.open();
        if (data.isExpanded) {
          this.toggleExpand(
            /* force */
            true
          );
        }
      }
    }
    // ── Panel open/close/expand ─────────────────────────────────────────────
    /** Open the panel; show the greeting on first open if configured. */
    open() {
      this.isOpen = true;
      this.els.widget.classList.add("cb-widget--open");
      this.els.panel.setAttribute("aria-hidden", "false");
      this.els.launcher.setAttribute("aria-expanded", "true");
      this.els.inputEl.focus();
      if (this.messages.length === 0 && this.config.startMessage) {
        this.addMessage("assistant", this.config.startMessage);
      }
      this.persist();
    }
    /** Close the panel and return focus to the launcher. */
    close() {
      this.isOpen = false;
      this.els.widget.classList.remove("cb-widget--open");
      this.els.panel.setAttribute("aria-hidden", "true");
      this.els.launcher.setAttribute("aria-expanded", "false");
      this.els.launcher.focus();
      this.persist();
    }
    /** Toggle (or force) the expanded state; updates aria-label. */
    toggleExpand(force) {
      this.isExpanded = force ?? !this.isExpanded;
      this.els.panel.classList.toggle("cb-panel--expanded", this.isExpanded);
      const shrinkLabel =
        this.els.expandBtn.dataset.shrinkLabel ?? "Shrink chat";
      const growLabel = this.els.expandBtn.dataset.growLabel ?? "Expand chat";
      this.els.expandBtn.setAttribute(
        "aria-label",
        this.isExpanded ? shrinkLabel : growLabel
      );
      this.persist();
    }
    // ── Messages ────────────────────────────────────────────────────────────
    /** Push a message into state + DOM. Persists user/assistant only. */
    addMessage(role, content, extraClass) {
      this.messages.push({ role, content });
      renderMessageDOM(
        this.els.messagesContainer,
        role,
        content,
        this.config.avatarUrl,
        extraClass
      );
      if (this.els.liveRegion) {
        this.els.liveRegion.textContent = content;
      }
      if (role === "user" || role === "assistant") {
        this.persist();
      }
    }
    // ── Sending ─────────────────────────────────────────────────────────────
    /**
     * Read the textarea, send the message, handle the response.
     *
     * The proxy's streaming response already covers the non-SSE JSON case
     * inline (when the upstream didn't actually stream, the proxy returns a
     * normal JSON body and sendStream treats the first chunk as the full
     * reply). A separate non-stream round-trip would just repeat the request,
     * so the stream path is the only transport invoked from the controller.
     */
    async sendMessage() {
      const text = this.els.inputEl.value.trim();
      if (!text || this.isLoading) {
        return;
      }
      this.els.inputEl.value = "";
      autoResize(this.els.inputEl);
      this.addMessage("user", text);
      this.setLoading(true);
      this.abortController = new AbortController();
      const bubble = { el: null };
      let full = "";
      try {
        await sendStream({
          config: this.config,
          messages: this.messages,
          signal: this.abortController.signal,
          stream: true,
          onDelta: (delta) => {
            if (!bubble.el) {
              removeLoading(this.els.messagesContainer);
              bubble.el = createStreamMessage(
                this.els.messagesContainer,
                this.config.avatarUrl
              );
            }
            full += delta;
            bubble.el.textContent = full;
            scrollToBottom(this.els.messagesContainer);
          },
          onDone: (fullContent) => {
            removeLoading(this.els.messagesContainer);
            if (bubble.el) {
              finalizeStreamMessage(bubble.el, fullContent);
              this.messages.push({ role: "assistant", content: fullContent });
              if (this.els.liveRegion) {
                this.els.liveRegion.textContent = fullContent;
              }
              this.persist();
            }
          },
          onError: (message) => {
            removeLoading(this.els.messagesContainer);
            if (bubble.el) {
              bubble.el.remove();
            }
            showError(this.els.messagesContainer, message);
          },
        });
      } catch {
        removeLoading(this.els.messagesContainer);
        if (bubble.el) {
          bubble.el.remove();
        }
        showError(
          this.els.messagesContainer,
          "Network error. Check your connection."
        );
      } finally {
        this.setLoading(false);
        this.els.inputEl.focus();
      }
    }
    /** Toggle loading state and input availability; shows the loader when on. */
    setLoading(loading) {
      this.isLoading = loading;
      setInputEnabled(this.els.inputEl, this.els.sendBtn, !loading);
      if (loading) {
        showLoading(this.els.messagesContainer);
      }
    }
    // ── Event wiring ─────────────────────────────────────────────────────────
    /** Bind all DOM listeners once. */
    bindEvents() {
      const { els } = this;
      els.launcher.addEventListener("click", () =>
        this.isOpen ? this.close() : this.open()
      );
      els.closeBtn.addEventListener("click", () => this.close());
      els.expandBtn.addEventListener("click", () => this.toggleExpand());
      els.sendBtn.addEventListener("click", () => this.sendMessage());
      els.inputEl.addEventListener("keydown", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          void this.sendMessage();
        }
      });
      els.inputEl.addEventListener("input", () => autoResize(els.inputEl));
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && this.isOpen) {
          if (this.isLoading && this.abortController) {
            this.abortController.abort();
          }
          this.close();
        }
      });
      els.panel.addEventListener("keydown", (e) => this.handleFocusTrap(e));
    }
    /** Keep Tab/Shift+Tab cycling inside the open panel. */
    handleFocusTrap(e) {
      if (e.key !== "Tab" || !this.isOpen) {
        return;
      }
      const focusable = this.els.panel.querySelectorAll(
        "button:not([disabled]), textarea:not([disabled])"
      );
      if (focusable.length === 0) {
        return;
      }
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const active = document.activeElement;
      if (e.shiftKey && active === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus();
      }
    }
  };

  // Sources/TypeScript/config.ts
  function findWidget() {
    return document.getElementById(WIDGET_ID);
  }
  function readConfig(widget2) {
    return {
      endpoint: widget2.dataset.endpoint ?? "/chatbot/api/chat",
      model: widget2.dataset.model ?? "gpt-4o",
      startMessage: widget2.dataset.startMessage ?? "",
      avatarUrl: widget2.dataset.avatarUrl ?? "",
    };
  }

  // Sources/TypeScript/chatbot.ts
  var widget = findWidget();
  if (widget) {
    const config = readConfig(widget);
    new ChatbotController(widget, config);
  }
})();
