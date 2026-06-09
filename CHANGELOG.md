# Changelog

## [1.0.0] — 2025-06-09

### Added
- Initial release: LLM chatbot widget for TYPO3 v13
- PSR-15 frontend middleware proxy (API key never reaches browser)
- Root-page configuration fields (TCA on `pages`, site-root-scoped)
- Content element `chatbot_widget` for per-page placement
- Global injection via TypoScript `page.1909` (gated by `tx_chatbot_everywhere`)
- Site Set (`moinferdi/chatbot`) with settings definitions
- Fluid widget partial with CSS-variable-based theming
- Vanilla JavaScript client (no framework, no secrets)
- Multi-language support (translatable start message, XLIFF UI strings)
- German translation
- `%env(VAR)%` resolution for API key field
- Same-origin enforcement, input validation, HTTPS upstream requirement
- AssetCollector integration for CSP-compatible asset loading
