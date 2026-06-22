/**
 * ChatbotController — owns widget state, wires DOM events, and delegates
 * rendering, persistence, and transport to the sibling modules.
 *
 * One controller per widget instance. Constructed from the entry module once
 * the widget element and its config have been located.
 */

import {
  autoResize,
  createStreamMessage,
  finalizeStreamMessage,
  queryElements,
  removeLoading,
  renderMessageDOM,
  setInputEnabled,
  scrollToBottom,
  showError,
  showLoading,
} from './render';
import { restore, save } from './storage';
import { sendStream } from './api';
import type {
  Message,
  PersistedSession,
  WidgetConfig,
  WidgetElements,
} from './types';

export class ChatbotController {
  // ── Config + DOM ──────────────────────────────────────────────────────
  // Assigned in the constructor's happy path; `!:` asserts that to TS since
  // the early-return-on-missing-elements path skips assignment.
  private readonly config!: WidgetConfig;
  private readonly els!: WidgetElements;

  // ── Widget state ──────────────────────────────────────────────────────
  private isOpen = false;
  private isExpanded = false;
  private isLoading = false;
  private messages: Message[] = [];
  private abortController: AbortController | null = null;

  constructor(widget: HTMLElement, config: WidgetConfig) {
    const els = queryElements(widget);
    if (!els) {
      // Template override dropped required hooks — bail out silently.
      return;
    }
    this.els = els;
    this.config = config;

    this.restoreSession();
    this.bindEvents();
  }

  // ── Session persistence ─────────────────────────────────────────────────

  /** Persist user/assistant messages + UI flags. Transient errors are skipped. */
  private persist(): void {
    const session: PersistedSession = {
      messages: this.messages.filter(
        (m) => m.role === 'user' || m.role === 'assistant',
      ),
      isOpen: this.isOpen,
      isExpanded: this.isExpanded,
    };
    save(session);
  }

  /** Replay a stored session into the DOM and restore UI flags. */
  private restoreSession(): void {
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
        this.config.avatarUrl,
      );
    }

    if (data.isOpen) {
      this.open();
      if (data.isExpanded) {
        this.toggleExpand(/* force */ true);
      }
    }
  }

  // ── Panel open/close/expand ─────────────────────────────────────────────

  /** Open the panel; show the greeting on first open if configured. */
  open(): void {
    this.isOpen = true;
    this.els.widget.classList.add('cb-widget--open');
    this.els.panel.setAttribute('aria-hidden', 'false');
    this.els.launcher.setAttribute('aria-expanded', 'true');
    this.els.inputEl.focus();
    if (this.messages.length === 0 && this.config.startMessage) {
      this.addMessage('assistant', this.config.startMessage);
    }
    this.persist();
  }

  /** Close the panel and return focus to the launcher. */
  close(): void {
    this.isOpen = false;
    this.els.widget.classList.remove('cb-widget--open');
    this.els.panel.setAttribute('aria-hidden', 'true');
    this.els.launcher.setAttribute('aria-expanded', 'false');
    this.els.launcher.focus();
    this.persist();
  }

  /** Toggle (or force) the expanded state; updates aria-label. */
  toggleExpand(force?: boolean): void {
    this.isExpanded = force ?? !this.isExpanded;
    this.els.panel.classList.toggle('cb-panel--expanded', this.isExpanded);
    const shrinkLabel =
      this.els.expandBtn.dataset.shrinkLabel ?? 'Shrink chat';
    const growLabel =
      this.els.expandBtn.dataset.growLabel ?? 'Expand chat';
    this.els.expandBtn.setAttribute(
      'aria-label',
      this.isExpanded ? shrinkLabel : growLabel,
    );
    this.persist();
  }

  // ── Messages ────────────────────────────────────────────────────────────

  /** Push a message into state + DOM. Persists user/assistant only. */
  private addMessage(
    role: Message['role'],
    content: string,
    extraClass?: string,
  ): void {
    this.messages.push({ role, content });
    renderMessageDOM(
      this.els.messagesContainer,
      role,
      content,
      this.config.avatarUrl,
      extraClass,
    );
    if (this.els.liveRegion) {
      this.els.liveRegion.textContent = content;
    }
    if (role === 'user' || role === 'assistant') {
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
  private async sendMessage(): Promise<void> {
    const text = this.els.inputEl.value.trim();
    if (!text || this.isLoading) {
      return;
    }
    this.els.inputEl.value = '';
    autoResize(this.els.inputEl);
    this.addMessage('user', text);
    this.setLoading(true);

    this.abortController = new AbortController();

    // `bubble` is mutated inside the streaming callbacks and read here in
    // the surrounding try/catch. TS won't track cross-closure narrowing of
    // a local, so we hold it in a ref object — a property access is widened
    // back to its declared type after each call, which is exactly what we
    // need so `if (bubble.el)` narrows correctly at every use site.
    const bubble: { el: HTMLDivElement | null } = { el: null };
    let full = '';

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
              this.config.avatarUrl,
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
            this.messages.push({ role: 'assistant', content: fullContent });
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
      // Defensive: sendStream swallows its own errors, but a throw from the
      // reader path should still leave the UI in a clean state.
      removeLoading(this.els.messagesContainer);
      if (bubble.el) {
        bubble.el.remove();
      }
      showError(this.els.messagesContainer, 'Network error. Check your connection.');
    } finally {
      this.setLoading(false);
      this.els.inputEl.focus();
    }
  }

  /** Toggle loading state and input availability; shows the loader when on. */
  private setLoading(loading: boolean): void {
    this.isLoading = loading;
    setInputEnabled(this.els.inputEl, this.els.sendBtn, !loading);
    if (loading) {
      showLoading(this.els.messagesContainer);
    }
  }

  // ── Event wiring ─────────────────────────────────────────────────────────

  /** Bind all DOM listeners once. */
  private bindEvents(): void {
    const { els } = this;

    els.launcher.addEventListener('click', () =>
      this.isOpen ? this.close() : this.open(),
    );
    els.closeBtn.addEventListener('click', () => this.close());
    els.expandBtn.addEventListener('click', () => this.toggleExpand());

    els.sendBtn.addEventListener('click', () => this.sendMessage());
    els.inputEl.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        void this.sendMessage();
      }
    });
    els.inputEl.addEventListener('input', () => autoResize(els.inputEl));

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.isOpen) {
        if (this.isLoading && this.abortController) {
          this.abortController.abort();
        }
        this.close();
      }
    });

    // Focus trap inside the panel dialog.
    els.panel.addEventListener('keydown', (e) => this.handleFocusTrap(e));
  }

  /** Keep Tab/Shift+Tab cycling inside the open panel. */
  private handleFocusTrap(e: KeyboardEvent): void {
    if (e.key !== 'Tab' || !this.isOpen) {
      return;
    }
    const focusable = this.els.panel.querySelectorAll<HTMLElement>(
      'button:not([disabled]), textarea:not([disabled])',
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
}
