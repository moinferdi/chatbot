/** sessionStorage-backed persistence of the chat session between page loads. */

import type { Message, PersistedSession } from './types';
import { STORAGE_KEY } from './types';

/**
 * Persist the current session. Degrades gracefully if storage is unavailable
 * or quota is exceeded — the widget stays usable, just stateless.
 */
export function save(session: PersistedSession): void {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
  } catch {
    // Quota exceeded or storage disabled — ignore.
  }
}

/**
 * Read and validate a previously persisted session.
 * @returns the session, or null if none exists / it is corrupt.
 */
export function restore(): PersistedSession | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) {
      return null;
    }
    const data = JSON.parse(raw) as Partial<PersistedSession>;
    if (!data || !Array.isArray(data.messages)) {
      return null;
    }
    return {
      messages: data.messages as Message[],
      isOpen: Boolean(data.isOpen),
      isExpanded: Boolean(data.isExpanded),
    };
  } catch {
    // Corrupt JSON — wipe and move on.
    try {
      sessionStorage.removeItem(STORAGE_KEY);
    } catch {
      // ignore
    }
    return null;
  }
}
