/**
 * Shared types and constants for the chatbot widget frontend.
 *
 * Frontend code never sees the API key — it talks only to the same-origin
 * proxy at POST /chatbot/api/chat. These types describe the browser side.
 */

/** Roles allowed in the persisted message log. */
export type Role = 'user' | 'assistant' | 'system';

/** A single chat message. */
export interface Message {
  readonly role: Role;
  readonly content: string;
}

/** Server-side config surfaced into the DOM via data-* attributes. */
export interface WidgetConfig {
  /** Same-origin proxy endpoint, e.g. /chatbot/api/chat. */
  readonly endpoint: string;
  /** Model id forwarded to the proxy, e.g. gpt-4o. */
  readonly model: string;
  /** Greeting shown on first open; empty if none. */
  readonly startMessage: string;
  /** Avatar image URL; empty string renders no avatar. */
  readonly avatarUrl: string;
}

/** Resolved DOM handles for the widget. Required elements are non-null. */
export interface WidgetElements {
  readonly widget: HTMLElement;
  readonly launcher: HTMLButtonElement;
  readonly panel: HTMLElement;
  readonly messagesContainer: HTMLElement;
  readonly inputEl: HTMLTextAreaElement;
  readonly sendBtn: HTMLButtonElement;
  readonly closeBtn: HTMLButtonElement;
  readonly expandBtn: HTMLButtonElement;
  /** May be absent in stripped-down template overrides. */
  readonly liveRegion: HTMLElement | null;
}

/** Shape persisted in sessionStorage between page loads. */
export interface PersistedSession {
  readonly messages: Message[];
  readonly isOpen: boolean;
  readonly isExpanded: boolean;
}

/** A parsed SSE frame coming back from the proxy. */
export interface StreamFrame {
  /** Proxy-emitted error frame, if present. */
  readonly error?: string;
  /** OpenAI-shaped choice; delta for streaming, message for non-streaming. */
  readonly choices?: ReadonlyArray<{
    readonly delta?: { readonly content?: string };
    readonly message?: { readonly content?: string };
  }>;
}

/** Tag identifying the widget root in the DOM. */
export const WIDGET_ID = 'moinferdi-chatbot';

/** sessionStorage key for the chat session. */
export const STORAGE_KEY = 'moinferdi-chatbot-session';

/** SSE sentinel signalling end of stream. */
export const SSE_DONE = '[DONE]';

/** SSE data-line prefix. */
export const SSE_DATA_PREFIX = 'data: ';
