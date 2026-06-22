/**
 * Chat API client — talks only to the same-origin proxy.
 *
 * Two transports:
 *  - stream:    POST with stream=true, parses SSE deltas live
 *  - non-stream: fallback JSON round-trip
 *
 * The proxy sanitises upstream errors and never exposes the API key.
 */

import type { Message, StreamFrame, WidgetConfig } from './types';
import { SSE_DATA_PREFIX, SSE_DONE } from './types';

/** Request body sent to the proxy. */
interface ChatRequestBody {
  readonly messages: Message[];
  readonly model: string;
  readonly stream?: boolean;
}

/** Result of a stream attempt — describes what happened, if anything. */
export type StreamOutcome =
  | { readonly kind: 'ok' }
  | { readonly kind: 'aborted' }
  | { readonly kind: 'error'; readonly message: string };

/** Options for sending a message. */
export interface SendOptions {
  readonly config: WidgetConfig;
  readonly messages: Message[];
  /** Called with each delta token as it arrives. */
  readonly onDelta: (delta: string) => void;
  /** Called once when the stream ends successfully with the full text. */
  readonly onDone: (full: string) => void;
  /** Called when an error occurs; receives a user-facing message. */
  readonly onError: (message: string) => void;
  /** Optional abort signal for cancellation. */
  readonly signal: AbortSignal;
  /** Whether to attempt streaming. */
  readonly stream: boolean;
}

/** Default user-facing error strings — kept here so they're easy to tune. */
const ERROR_NETWORK = 'Network error. Check your connection.';
const ERROR_STREAM_LOST = 'Stream connection lost. Please try again.';
const ERROR_EMPTY = 'Empty response from assistant.';
const ERROR_GENERIC = 'Something went wrong. Please try again.';

/** Attempt a streaming round-trip; returns the outcome. */
export async function sendStream(opts: SendOptions): Promise<StreamOutcome> {
  const body: ChatRequestBody = {
    messages: opts.messages,
    model: opts.config.model,
    stream: true,
  };

  let response: Response;
  try {
    response = await fetch(opts.config.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
      signal: opts.signal,
    });
  } catch (err) {
    if (isAbortError(err)) {
      return { kind: 'aborted' };
    }
    opts.onError(ERROR_NETWORK);
    return { kind: 'error', message: ERROR_NETWORK };
  }

  if (!response.ok) {
    const message = await readErrorMessage(response) ?? ERROR_GENERIC;
    opts.onError(message);
    return { kind: 'error', message };
  }

  const contentType = response.headers.get('content-type') ?? '';
  // Non-SSE response — backend didn't actually stream.
  if (!contentType.includes('text/event-stream')) {
    const data = (await response.json()) as { content?: string; message?: string };
    const reply = data.content ?? data.message ?? 'No response.';
    opts.onDelta(reply);
    opts.onDone(reply);
    return { kind: 'ok' };
  }

  return consumeSse(response, opts);
}

/** Fall back to a plain JSON round-trip (no streaming). */
export async function sendNonStream(
  config: WidgetConfig,
  messages: Message[],
  onReply: (content: string) => void,
  onError: (message: string) => void,
  signal?: AbortSignal,
): Promise<void> {
  const body: ChatRequestBody = { messages, model: config.model };

  let response: Response;
  try {
    response = await fetch(config.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
      signal,
    });
  } catch (err) {
    if (isAbortError(err)) {
      return;
    }
    onError(ERROR_NETWORK);
    return;
  }

  if (!response.ok) {
    const message = await readErrorMessage(response) ?? ERROR_GENERIC;
    onError(message);
    return;
  }

  const data = (await response.json()) as { content?: string; message?: string };
  onReply(data.content ?? data.message ?? 'No response.');
}

/** Read and parse an SSE body, dispatching deltas/done/error. */
async function consumeSse(
  response: Response,
  opts: SendOptions,
): Promise<StreamOutcome> {
  const reader = response.body?.getReader();
  if (!reader) {
    opts.onError(ERROR_STREAM_LOST);
    return { kind: 'error', message: ERROR_STREAM_LOST };
  }

  const decoder = new TextDecoder();
  let buffer = '';
  let full = '';

  for (;;) {
    const { done, value } = await reader.read();
    if (done) {
      break;
    }
    buffer += decoder.decode(value, { stream: true });

    const lines = buffer.split('\n');
    buffer = lines.pop() ?? '';

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
        return { kind: 'ok' };
      }

      const frame = safeParse<StreamFrame>(payload);
      if (!frame) {
        continue; // non-JSON line, skip
      }

      if (frame.error) {
        const message =
          typeof frame.error === 'string' ? frame.error : ERROR_GENERIC;
        opts.onError(message);
        return { kind: 'error', message };
      }

      const choice = frame.choices?.[0];
      const delta = choice?.delta?.content ?? choice?.message?.content ?? '';
      if (delta) {
        full += delta;
        opts.onDelta(delta);
      }
    }
  }

  // Stream closed without an explicit [DONE].
  if (full) {
    opts.onDone(full);
    return { kind: 'ok' };
  }
  opts.onError(ERROR_EMPTY);
  return { kind: 'error', message: ERROR_EMPTY };
}

/** Best-effort extraction of a user-facing error message from a non-ok body. */
async function readErrorMessage(response: Response): Promise<string | null> {
  try {
    const data = (await response.json()) as { error?: unknown };
    if (typeof data.error === 'string') {
      return data.error;
    }
    return null;
  } catch {
    return null;
  }
}

/** Parse JSON defensively; returns null on failure. */
function safeParse<T>(payload: string): T | null {
  try {
    return JSON.parse(payload) as T;
  } catch {
    return null;
  }
}

/** True if the error is an AbortController cancellation. */
function isAbortError(err: unknown): boolean {
  return err instanceof DOMException && err.name === 'AbortError';
}
