/** Read server-side config from the widget element's data-* attributes. */

import type { WidgetConfig } from './types';
import { WIDGET_ID } from './types';

/**
 * Locate the widget root in the DOM.
 * @returns the widget element, or null if none is present on this page.
 */
export function findWidget(): HTMLElement | null {
  return document.getElementById(WIDGET_ID);
}

/**
 * Parse the widget's data-* attributes into a typed config object.
 * Falls back to safe defaults so the widget is usable even if a template
 * override drops an attribute.
 */
export function readConfig(widget: HTMLElement): WidgetConfig {
  return {
    endpoint: widget.dataset.endpoint ?? '/chatbot/api/chat',
    model: widget.dataset.model ?? 'gpt-4o',
    startMessage: widget.dataset.startMessage ?? '',
    avatarUrl: widget.dataset.avatarUrl ?? '',
  };
}
