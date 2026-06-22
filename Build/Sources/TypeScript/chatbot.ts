/**
 * Chatbot widget entry point.
 *
 * Boots once per page that contains the widget element. Talks ONLY to the
 * same-origin proxy at POST /chatbot/api/chat — no API key ever lives here.
 *
 * This module is the esbuild entry; everything else is imported. There is
 * no IIFE, no global, no DOMContentLoaded race — the compiled bundle is
 * loaded with `defer` via TYPO3's AssetCollector, so by the time this runs
 * the DOM (and the widget partial) is already parsed.
 */

import { ChatbotController } from './controller';
import { findWidget, readConfig } from './config';

const widget = findWidget();
if (widget) {
  const config = readConfig(widget);
  new ChatbotController(widget, config);
}
