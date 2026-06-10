# Chatbot Extension for TYPO3 v13

LLM chatbot widget for TYPO3 v13 — BYOK, OpenWebUI-first, proxy-backed.

## Features

- Chat widget for TYPO3 frontend pages
- Server-side proxy to protect your API key
- Same-origin enforcement to prevent unauthorized access
- Rate limiting to control API costs
- Streaming and non-streaming responses
- Configurable per site (root page)
- Customizable colors and position

## Requirements

- TYPO3 13.4+
- PHP 8.2 or 8.3
- OpenWebUI or OpenAI-compatible API endpoint

## Installation

```bash
composer require moinferdi/chatbot
```

After installation, run the **Database Analyzer** in the TYPO3 Install Tool
to create the required columns on the `pages` table:

> Admin Tools → Maintenance → Analyze Database → Apply changes

Then **flush all caches** (Admin Tools → Maintenance → Flush Cache).

## Configuration

### Site Settings

The extension can be configured via the site settings YAML file:

```yaml
settings:
  chatbot:
    enabled: true
    openWebUiBaseUrl: 'https://your-openwebui.example.com'
    defaultModel: 'gpt-4o'
    color:
      primary: '#4F9EF7'
      background: '#ffffff'
      text: '#1a1a1a'
    position: 'bottom-right'
```

### Page Properties

Each root page can override settings via the "Chatbot" tab in page properties:

- **Enable Chatbot** — Toggle the widget on/off
- **Show Everywhere** — Display on all subpages
- **Base URL** — OpenWebUI/OpenAI-compatible API URL
- **Model** — LLM model identifier
- **API Key** — Authentication key (use `%env(CHATBOT_API_KEY)%` for environment variable)
- **Colors** — Primary, background, and text colors
- **Position** — Widget placement (bottom-right or bottom-left)
- **Start Message** — Welcome message shown on first open. Per-language: each site
  language has a built-in localized default; translate the root page to override it for
  a specific language. All settings above are shared across languages.

### Environment Variables

Set the API key via environment variable for security:

```bash
# .env or server environment
CHATBOT_API_KEY=sk-...
```

Then use `%env(CHATBOT_API_KEY)%` in the page properties API key field.

## API Endpoint

The widget communicates with a same-origin proxy at `/chatbot/api/chat`.

### Request

```json
POST /chatbot/api/chat
Content-Type: application/json

{
  "messages": [
    {"role": "user", "content": "Hello!"}
  ],
  "model": "gpt-4o",
  "stream": false
}
```

### Response (non-streaming)

```json
{
  "content": "Hello! How can I help you?",
  "role": "assistant"
}
```

### Response (streaming)

When `"stream": true`, the endpoint returns Server-Sent Events (SSE):

```
data: {"choices":[{"delta":{"content":"Hello"}}]}
data: {"choices":[{"delta":{"content":"!"}}]}
data: [DONE]
```

## Rate Limiting

Requests are limited to 20 per minute per IP address. Exceeding this returns HTTP 429.

## Development

### Running Tests

```bash
composer test
```

### Static Analysis

```bash
composer phpstan
```

### Building JS/CSS

The frontend assets are plain vanilla JS and CSS. No build step required.
