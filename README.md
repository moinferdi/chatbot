# Chatbot — TYPO3 v13 LLM Chatbot Extension

A TYPO3 v13.4 extension that injects an LLM-powered chatbot widget into your site.
Bring Your Own Key (BYOK), OpenWebUI-first design, OpenAI-compatible.

## Features

- **Secure by design** — API key never reaches the browser; all chat traffic
  proxies through a same-origin PSR-15 frontend middleware.
- **Two placement modes** — show globally on every page (toggle on site root),
  *or* drop a content element on specific pages. Both render the same widget.
- **Per-site configuration** — settings live on your site's root page (TCA fields
  on `pages`), naturally scoped per site.
- **Multi-language** — translatable start message via page language overlays;
  UI strings in XLIFF; German included.
- **Themable** — colors and position driven by CSS variables, set from the
  backend (TCA color pickers). No recompilation needed.
- **OpenAI-compatible endpoint** — works with OpenWebUI, OpenAI, Azure OpenAI,
  Ollama, and any backend that speaks `/api/chat/completions`.

## Requirements

- TYPO3 **v13.4 LTS** (Composer-based installation)
- PHP 8.2 or 8.3
- A running OpenWebUI (or OpenAI-compatible) instance accessible over HTTPS
- An API key from your OpenWebUI instance (*Settings → Account*)

## Quick Start

1. **Install the extension:**

   ```bash
   composer require moinferdi/chatbot
   ```

2. **Assign the Site Set** to your site in the TYPO3 backend:

   *Site Management → Sites → (your site) → Sets* — add `moinferdi/chatbot`.

3. **Configure the root page:**

   Open your site's root page in the Page module. You'll see a **Chatbot** tab
   with fields for:
   - Enable / Show everywhere
   - OpenWebUI Base URL (e.g. `https://chat.example.com`)
   - Model ID (e.g. `gpt-4o`)
   - **API Key** (your BYOK secret — stored server-side, never exposed)
   - Colors & position
   - Start message (the greeting text, translatable per language)

4. **Toggle the widget:**

   - Set *Show on every page* to yes → widget appears on all pages.
   - Or insert a **"Chatbot Widget"** content element on specific pages.

## How the proxy works

```
Browser  →  POST /chatbot/api/chat (same-origin, NO key)
                ↓
         ChatProxyMiddleware (PSR-15 frontend)
           • Validates same-origin (Sec-Fetch-Site / Origin / Referer)
           • Resolves API key from root page, server-side
           • Calls OpenWebUI: POST {baseUrl}/api/chat/completions
                ↓
         Returns JSON { content: "...", role: "assistant" }
```

The API key is **read from the site root page record** inside the middleware.
It is **never** returned to the browser, present in page source, network tabs,
or JavaScript code.

## Configuration

### Root page fields (TCA on `pages`, visible only on site root pages)

| Field | Description |
|-------|-------------|
| `tx_chatbot_enabled` | Master toggle for this site |
| `tx_chatbot_everywhere` | Show on every page vs. content-element only |
| `tx_chatbot_base_url` | OpenWebUI base URL (must be HTTPS) |
| `tx_chatbot_model` | Model ID (e.g. `gpt-4o`, `llama3`) |
| `tx_chatbot_api_key` | API key (input, masked; supports `%env(VAR)%`) |
| `tx_chatbot_color_primary` | Primary accent color |
| `tx_chatbot_color_background` | Panel background |
| `tx_chatbot_color_text` | Text color |
| `tx_chatbot_position` | Widget position: bottom-right / bottom-left |
| `tx_chatbot_start_message` | Greeting text (language-aware) |

### Site Set settings (fallback defaults, editable in Site Settings backend module)

Configured in `Configuration/Sets/Chatbot/settings.definitions.yaml`:

- `chatbot.enabled`
- `chatbot.openWebUiBaseUrl`
- `chatbot.defaultModel`
- `chatbot.color.primary` / `chatbot.color.background` / `chatbot.color.text`
- `chatbot.position`

### Environment variable for API key

Instead of storing the key in the database, use `%env(CHATBOT_API_KEY)%` in
the API key field. The extension resolves this at runtime using `getenv()`.

## Multi-language

- **Start message**: Translated via page language overlays on the site root.
  Each language has its own greeting.
- **UI strings**: Translated via XLIFF files in `Resources/Private/Language/`.
  German (`de.locallang.xlf`) is included.
- **Model tone**: The proxy injects a system message naming the site's current
  language, biasing the model's reply language.

## Security

- API key **never** reaches the client
- Same-origin enforcement on the proxy endpoint (Sec-Fetch-Site, Origin, Referer)
- HTTPS upstream enforced
- Input validation (message count, length, role whitelist)
- API key field masked with password input type; supports `%env()%` to keep
  secrets out of the database
- Asset loading via AssetCollector (CSP nonce-compatible)
- Error responses sanitized — no secrets leaked in logs or error messages

## Customization

Override the Fluid templates in your own site package:

```
lib.contentElement {
  templateRootPaths.300 = EXT:your_site/Resources/Private/Templates/
  partialRootPaths.300  = EXT:your_site/Resources/Private/Partials/
}
```

## License

MIT
