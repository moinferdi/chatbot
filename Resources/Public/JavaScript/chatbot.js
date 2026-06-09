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
    if (e.key === "Escape" && isOpen) close();
  });

  function addMessage(role, content, extraClass) {
    messages.push({ role, content });
    const div = document.createElement("div");
    div.className =
      "cb-message cb-message--" + role + (extraClass ? " " + extraClass : "");
    div.textContent = content;
    messagesContainer.appendChild(div);
    scrollToBottom();
    if (liveRegion) liveRegion.textContent = content;
  }

  function removeLoading() {
    const loader = messagesContainer.querySelector(".cb-message--loading");
    if (loader) loader.remove();
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
    showLoading();
    setInputEnabled(false);

    try {
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
    } catch (err) {
      removeLoading();
      showError("Network error. Check your connection.");
    } finally {
      setInputEnabled(true);
      inputEl.focus();
    }
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
})();
