/** DOM mutation helpers for rendering messages into the widget. */

import type { Role, WidgetElements } from './types';
import { renderMarkdown } from './markdown';

/** Scroll the message log to the bottom. */
export function scrollToBottom(container: HTMLElement): void {
  container.scrollTop = container.scrollHeight;
}

/** Auto-grow the textarea up to a sensible max height. */
export function autoResize(input: HTMLTextAreaElement): void {
  input.style.height = 'auto';
  input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
}

/** Toggle input/send button disabled state while loading. */
export function setInputEnabled(
  input: HTMLTextAreaElement,
  sendBtn: HTMLButtonElement,
  enabled: boolean,
): void {
  input.disabled = !enabled;
  sendBtn.disabled = !enabled;
}

/** Remove the "thinking" loader if present. */
export function removeLoading(container: HTMLElement): void {
  container.querySelector('.cb-message--loading')?.remove();
}

/** Show the three-dot "thinking" loader. */
export function showLoading(container: HTMLElement): void {
  const loader = document.createElement('div');
  loader.className = 'cb-message cb-message--loading';
  loader.innerHTML =
    '<span class="cb-typing-dot"></span>'.repeat(3);
  container.appendChild(loader);
  scrollToBottom(container);
}

/** Show an error as a transient system message (not persisted). */
export function showError(container: HTMLElement, text: string): void {
  const div = document.createElement('div');
  div.className = 'cb-message cb-message--system cb-message--error';
  div.textContent = text;
  container.appendChild(div);
  scrollToBottom(container);
}

/**
 * Create the avatar + bubble row for a streaming assistant message and
 * return the bubble element so the caller can fill it token-by-token.
 */
export function createStreamMessage(
  container: HTMLElement,
  avatarUrl: string,
): HTMLDivElement {
  const row = document.createElement('div');
  row.className = 'cb-message-row cb-message-row--assistant';

  if (avatarUrl) {
    row.appendChild(buildAvatar(avatarUrl));
  }

  const bubble = document.createElement('div');
  bubble.className = 'cb-message cb-message--assistant cb-message--streaming';
  bubble.textContent = '';
  row.appendChild(bubble);
  container.appendChild(row);
  return bubble;
}

/**
 * Render a fully-formed message row into the DOM (no state touched).
 * Used both for live messages and for replaying a restored session.
 */
export function renderMessageDOM(
  container: HTMLElement,
  role: Role,
  content: string,
  avatarUrl: string,
  extraClass?: string,
): void {
  if (role === 'assistant') {
    const row = document.createElement('div');
    row.className = 'cb-message-row cb-message-row--assistant';
    if (avatarUrl) {
      row.appendChild(buildAvatar(avatarUrl));
    }
    const bubble = document.createElement('div');
    bubble.className = appendClass(
      'cb-message cb-message--assistant',
      extraClass,
    );
    bubble.innerHTML = renderMarkdown(content);
    row.appendChild(bubble);
    container.appendChild(row);
  } else if (role === 'user') {
    const row = document.createElement('div');
    row.className = 'cb-message-row cb-message-row--user';
    const bubble = document.createElement('div');
    bubble.className = appendClass('cb-message cb-message--user', extraClass);
    bubble.textContent = content;
    row.appendChild(bubble);
    container.appendChild(row);
  } else {
    // system / error — no row wrapper, plain block.
    const div = document.createElement('div');
    div.className = appendClass(`cb-message cb-message--${role}`, extraClass);
    div.textContent = content;
    container.appendChild(div);
  }
  scrollToBottom(container);
}

/** Mark a streaming bubble as done and render its markdown. */
export function finalizeStreamMessage(
  bubble: HTMLDivElement,
  content: string,
): void {
  bubble.classList.remove('cb-message--streaming');
  bubble.innerHTML = renderMarkdown(content);
}

/** Append an optional extra class to a base class string. */
function appendClass(base: string, extra?: string): string {
  return extra ? `${base} ${extra}` : base;
}

/** Build an avatar <img> with consistent accessibility attributes. */
function buildAvatar(avatarUrl: string): HTMLImageElement {
  const img = document.createElement('img');
  img.className = 'cb-avatar';
  img.src = avatarUrl;
  img.alt = '';
  img.setAttribute('aria-hidden', 'true');
  return img;
}

/** Query the widget's required elements; returns null if any are missing. */
export function queryElements(widget: HTMLElement): WidgetElements | null {
  const launcher = widget.querySelector<HTMLButtonElement>('.cb-launcher');
  const panel = widget.querySelector<HTMLElement>('#cb-panel');
  const messagesContainer = widget.querySelector<HTMLElement>('#cb-messages');
  const inputEl = widget.querySelector<HTMLTextAreaElement>('#cb-input');
  const sendBtn = widget.querySelector<HTMLButtonElement>('#cb-send');
  const closeBtn = widget.querySelector<HTMLButtonElement>('.cb-panel__close');
  const expandBtn = widget.querySelector<HTMLButtonElement>('.cb-panel__expand');
  const liveRegion = widget.querySelector<HTMLElement>('#cb-live-region');

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
    widget,
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
