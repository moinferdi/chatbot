/**
 * Chatbot Widget — vanilla JS.
 * Talks ONLY to the same-origin proxy /chatbot/api/chat. No API key here.
 */
(function () {
  "use strict";

  const widget = document.getElementById("moinferdi-chatbot");
  if (!widget) return;

  const endpoint = widget.dataset.endpoint || "/chatbot/api/chat";
  const model = widget.dataset.model || "gpt-4o";
  const startMessage = widget.dataset.startMessage || "";
  const avatarUrl = widget.dataset.avatarUrl || "";

  const launcher = widget.querySelector(".cb-launcher");
  const panel = widget.querySelector("#cb-panel");
  const messagesContainer = widget.querySelector("#cb-messages");
  const inputEl = widget.querySelector("#cb-input");
  const sendBtn = widget.querySelector("#cb-send");
  const closeBtn = widget.querySelector(".cb-panel__close");
  const liveRegion = widget.querySelector("#cb-live-region");

  let isOpen = false;
  let isLoading = false;
  let messages = [];
  let abortController = null;

  function open() {
    isOpen = true;
    widget.classList.add("cb-widget--open");
    panel.setAttribute("aria-hidden", "false");
    launcher.setAttribute("aria-expanded", "true");
    inputEl.focus();
    if (messages.length === 0 && startMessage) {
      addMessage("assistant", startMessage);
    }
  }

  function close() {
    isOpen = false;
    widget.classList.remove("cb-widget--open");
    panel.setAttribute("aria-hidden", "true");
    launcher.setAttribute("aria-expanded", "false");
    launcher.focus();
  }

  launcher.addEventListener("click", () => (isOpen ? close() : open()));
  closeBtn.addEventListener("click", () => close());
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && isOpen) {
      if (isLoading && abortController) {
        abortController.abort();
      }
      close();
    }
  });

  function addMessage(role, content, extraClass) {
    messages.push({ role, content });

    if (role === "assistant") {
      const row = document.createElement("div");
      row.className = "cb-message-row cb-message-row--assistant";

      if (avatarUrl) {
        const img = document.createElement("img");
        img.className = "cb-avatar";
        img.src = avatarUrl;
        img.alt = "";
        img.setAttribute("aria-hidden", "true");
        row.appendChild(img);
      }

      const bubble = document.createElement("div");
      bubble.className =
        "cb-message cb-message--assistant" + (extraClass ? " " + extraClass : "");
      bubble.innerHTML = renderMarkdown(content);
      row.appendChild(bubble);
      messagesContainer.appendChild(row);
    } else if (role === "user") {
      const row = document.createElement("div");
      row.className = "cb-message-row cb-message-row--user";

      const bubble = document.createElement("div");
      bubble.className =
        "cb-message cb-message--user" + (extraClass ? " " + extraClass : "");
      bubble.textContent = content;
      row.appendChild(bubble);
      messagesContainer.appendChild(row);
    } else {
      // system / error messages — no row wrapper needed
      const div = document.createElement("div");
      div.className =
        "cb-message cb-message--" + role + (extraClass ? " " + extraClass : "");
      div.textContent = content;
      messagesContainer.appendChild(div);
    }

    scrollToBottom();
    if (liveRegion) liveRegion.textContent = content;
  }

  function removeLoading() {
    const loader = messagesContainer.querySelector(".cb-message--loading");
    if (loader) loader.remove();
  }

  function createStreamMessage() {
    const row = document.createElement("div");
    row.className = "cb-message-row cb-message-row--assistant";

    if (avatarUrl) {
      const img = document.createElement("img");
      img.className = "cb-avatar";
      img.src = avatarUrl;
      img.alt = "";
      img.setAttribute("aria-hidden", "true");
      row.appendChild(img);
    }

    const div = document.createElement("div");
    div.className = "cb-message cb-message--assistant cb-message--streaming";
    div.textContent = "";
    row.appendChild(div);
    messagesContainer.appendChild(row);
    return div;
  }

  function finalizeStreamMessage(el, content) {
    el.classList.remove("cb-message--streaming");
    el.innerHTML = renderMarkdown(content);
    messages.push({ role: "assistant", content });
    if (liveRegion) liveRegion.textContent = content;
  }

  function showLoading() {
    const loader = document.createElement("div");
    loader.className = "cb-message cb-message--loading";
    loader.innerHTML =
      '<span class="cb-typing-dot"></span><span class="cb-typing-dot"></span><span class="cb-typing-dot"></span>';
    messagesContainer.appendChild(loader);
    scrollToBottom();
  }

  function showError(text) {
    addMessage("system", text, "cb-message--error");
  }

  function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
  }

  async function sendMessage() {
    const text = inputEl.value.trim();
    if (!text || isLoading) return;
    inputEl.value = "";
    autoResize();
    addMessage("user", text);
    setInputEnabled(false);
    showLoading();

    try {
      const streamed = await sendStream(text);
      if (!streamed) {
        await sendNonStream(text);
      }
    } catch (err) {
      removeLoading();
      showError("Network error. Check your connection.");
    } finally {
      setInputEnabled(true);
      inputEl.focus();
    }
  }

  async function sendStream(text) {
    abortController = new AbortController();

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ messages, model, stream: true }),
        signal: abortController.signal,
      });

      if (!response.ok) {
        removeLoading();
        let errorMsg = "Something went wrong. Please try again.";
        try {
          const err = await response.json();
          if (err.error) errorMsg = err.error;
        } catch (_) {}
        showError(errorMsg);
        return true;
      }

  // Non-SSE response — backend didn't stream
      const contentType = response.headers.get("content-type") || "";
      if (!contentType.includes("text/event-stream")) {
        removeLoading();
        const data = await response.json();
        const reply = data.content || data.message || "No response.";
        addMessage("assistant", reply);
        return true;
      }

      let streamContainer = null;
      let fullContent = "";
      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = "";

      // Keep the "thinking" loader visible until the first token arrives,
      // then swap it for the answer bubble that fills in as tokens stream.
      const appendDelta = (delta) => {
        if (!streamContainer) {
          removeLoading();
          streamContainer = createStreamMessage();
        }
        fullContent += delta;
        streamContainer.textContent = fullContent;
        scrollToBottom();
      };

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });

        const lines = buffer.split("\n");
        buffer = lines.pop() || "";

        for (const line of lines) {
          if (!line.startsWith("data: ")) continue;
          const payload = line.slice(6).trim();

          if (payload === "[DONE]") {
            removeLoading();
            if (streamContainer) finalizeStreamMessage(streamContainer, fullContent);
            return true;
          }

          try {
            const parsed = JSON.parse(payload);
            // Proxy-emitted error frame (event: error) — surface it instead of
            // silently ending up with an empty reply.
            if (parsed.error) {
              removeLoading();
              if (streamContainer) streamContainer.remove();
              showError(
                typeof parsed.error === "string"
                  ? parsed.error
                  : "Something went wrong. Please try again."
              );
              return true;
            }
            const choice = parsed.choices?.[0];
            const delta = choice?.delta?.content ?? choice?.message?.content ?? "";
            if (delta) appendDelta(delta);
          } catch (_) {
            // Non-JSON line, skip
          }
        }
      }

      removeLoading();
      if (streamContainer && fullContent) {
        finalizeStreamMessage(streamContainer, fullContent);
      } else {
        if (streamContainer) streamContainer.remove();
        showError("Empty response from assistant.");
      }
      return true;
    } catch (err) {
      if (err.name === "AbortError") return true;
      removeLoading();
      showError("Stream connection lost. Please try again.");
      return true;
    }
  }

  async function sendNonStream(text) {
    const response = await fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ messages, model }),
    });

    removeLoading();

    if (!response.ok) {
      let errorMsg = "Something went wrong. Please try again.";
      try {
        const err = await response.json();
        if (err.error) errorMsg = err.error;
      } catch (_) {}
      showError(errorMsg);
      return;
    }

    const data = await response.json();
    const reply = data.content || data.message || "No response.";
    addMessage("assistant", reply);
  }

  function setInputEnabled(enabled) {
    isLoading = !enabled;
    inputEl.disabled = !enabled;
    sendBtn.disabled = !enabled;
  }

  function autoResize() {
    inputEl.style.height = "auto";
    inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + "px";
  }

  sendBtn.addEventListener("click", sendMessage);
  inputEl.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });
  inputEl.addEventListener("input", autoResize);

  // Focus trap
  panel.addEventListener("keydown", (e) => {
    if (e.key === "Tab" && isOpen) {
      const focusable = panel.querySelectorAll(
        "button:not([disabled]), textarea:not([disabled])"
      );
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  /**
   * Lightweight Markdown-to-HTML renderer.
   * Handles bold, italic, inline code, code blocks, links, images,
   * headings, blockquotes, lists, horizontal rules, and paragraphs.
   */
  function renderMarkdown(text) {
    if (!text) return "";

    // Escape HTML first
    let html = text
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");

    // Code blocks — protect from other transforms
    const codeBlocks = [];
    html = html.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => {
      codeBlocks.push(code.replace(/^\n|\n$/g, ""));
      return "\u0000CODEBLOCK" + (codeBlocks.length - 1) + "\u0000";
    });

    // Inline code
    html = html.replace(/`([^`]+)`/g, (_, code) => {
      return "<code>" + code + "</code>";
    });

    // Bold (*** or ___) and italic (** or __)
    html = html.replace(/\*\*\*(.+?)\*\*\*/g, "<strong><em>$1</em></strong>");
    html = html.replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>");
    html = html.replace(/\*(.+?)\*/g, "<em>$1</em>");
    html = html.replace(/___(.+?)___/g, "<strong><em>$1</em></strong>");
    html = html.replace(/__(.+?)__/g, "<strong>$1</strong>");

    // Images ![alt](url) — before links so ![ doesn't trigger link rule
    html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" style="max-width:100%">');

    // Links [text](url)
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

    // Headings — process longest first to avoid # being consumed by ##
    html = html.replace(/^#### (.+)$/gm, "<h4>$1</h4>");
    html = html.replace(/^### (.+)$/gm, "<h3>$1</h3>");
    html = html.replace(/^## (.+)$/gm, "<h2>$1</h2>");
    html = html.replace(/^# (.+)$/gm, "<h1>$1</h1>");

    // Horizontal rules
    html = html.replace(/^(---|\*\*\*|___)\s*$/gm, "<hr>");

    // Blockquotes
    html = html.replace(/^&gt; (.+)$/gm, "<blockquote>$1</blockquote>");
    // Merge consecutive blockquotes
    html = html.replace(/<\/blockquote>\n<blockquote>/g, "\n");

    // Unordered lists
    html = html.replace(/^[\-\*] (.+)$/gm, "<li>$1</li>");
    html = html.replace(/((?:<li>.*<\/li>\n?)+)/g, "<ul>$1</ul>");

    // Ordered lists
    html = html.replace(/^\d+\. (.+)$/gm, "<li>$1</li>");

    // Restore code blocks
    html = html.replace(/\u0000CODEBLOCK(\d+)\u0000/g, (_, i) => {
      return "<pre><code>" + codeBlocks[parseInt(i)] + "</code></pre>";
    });

    // Paragraphs: wrap remaining text blocks in <p>
    const lines = html.split("\n");
    const result = [];
    let para = [];
    const blockTags = /^<(h[1-4]|ul|ol|li|pre|blockquote|hr|table)/;

    function flush() {
      if (para.length) {
        result.push("<p>" + para.join("\n") + "</p>");
        para = [];
      }
    }

    for (const line of lines) {
      const trimmed = line.trim();
      if (trimmed === "") {
        flush();
      } else if (blockTags.test(trimmed)) {
        flush();
        result.push(trimmed);
      } else {
        para.push(line);
      }
    }
    flush();

    return result.join("\n");
  }
})();
