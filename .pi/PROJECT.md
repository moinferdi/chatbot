# Project Context: TYPO3 Chatbot Extension

> **Read this at the start of every session in this project.**  
> Path: `/home/ferdi/development/typo3-ai-chatbot/chatbot`

---

## 1. What Is This?

A TYPO3 v13.4 LTS extension (`moinferdi/chatbot`) that injects an LLM-powered chatbot widget into TYPO3 frontend sites. BYOK (Bring Your Own Key), OpenWebUI-first design, OpenAI-compatible.

**Core principle:** The API key **never** reaches the browser. All chat traffic proxies through a same-origin PSR-15 middleware.

| Property | Value |
|----------|-------|
| PHP namespace | `Moinferdi\Chatbot\` |
| Composer name | `moinferdi/chatbot` |
| Extension key | `chatbot` |
| TYPO3 version | `^13.4` |
| PHP version | `^8.2 \|\| ^8.3` |
| License | MIT |

## 2. Architecture Overview

```
Browser Widget (TypeScript → esbuild IIFE bundle)
    │  POST /chatbot/api/chat  (same-origin, NO key)
    ▼
ChatProxyMiddleware (PSR-15 frontend middleware)
    │  • Validates same-origin (Sec-Fetch-Site / Origin / Referer)
    │  • Resolves API key from site root page, server-side
    │  • Rate-limits per IP (20 req/min, TYPO3 cache backend)
    │  • Enforces HTTPS upstream
    ▼
ChatClient (GuzzleHttp)
    │  POST {baseUrl}/api/chat/completions
    │  • Non-streaming: JSON response → { content, role }
    │  • Streaming: yields raw SSE bytes via SseStream (TYPO3 SelfEmittableStreamInterface)
    ▼
OpenWebUI / OpenAI-compatible backend
```

## 3. Directory & File Map

```
chatbot/
├── Classes/                              # PHP source (PSR-4: Moinferdi\Chatbot\)
│   ├── DataProcessing/
│   │   └── ChatbotConfigProcessor.php    # Fluid data processor: resolves config for templates
│   ├── Dto/
│   │   └── ChatRequest.php              # Validates chat requests (messages, model)
│   ├── Http/
│   │   └── SseStream.php                # Self-emitting SSE stream for streaming responses
│   ├── Middleware/
│   │   └── ChatProxyMiddleware.php      # PSR-15 middleware: main proxy logic
│   └── Service/
│       ├── ChatbotConfig.php            # Immutable config value object
│       ├── ChatClient.php               # GuzzleHttp client: calls upstream API
│       ├── ConfigurationResolver.php    # Resolves config from site + root page
│       ├── RateLimiter.php              # IP-based rate limiter (custom TYPO3 cache)
│       └── UpstreamException.php        # Exception for non-200 upstream responses
├── Configuration/
│   ├── Icons.php                        # Registers SVG icon for content element
│   ├── RequestMiddlewares.php           # Registers ChatProxyMiddleware (after site, before page-resolver)
│   ├── Services.yaml                    # DI container: services, guzzle client, rate limiter cache
│   ├── page.tsconfig                    # Import redirect → Sets/Chatbot/page.tsconfig
│   ├── TCA/Overrides/
│   │   ├── pages.php                    # All TCA fields on pages (always on root pages only)
│   │   └── tt_content.php              # Registers 'chatbot_widget' CType
│   └── Sets/Chatbot/                    # TYPO3 v13 Site Set
│       ├── config.yaml                  # Site set metadata
│       ├── settings.definitions.yaml    # Site-level settings definitions
│       ├── setup.typoscript             # Fluid template paths + global/page rendering
│       └── page.tsconfig               # New Content Element Wizard entry
├── Resources/
│   ├── Private/
│   │   ├── Language/
│   │   │   ├── locallang.xlf           # English UI strings (widget labels, errors)
│   │   │   ├── de.locallang.xlf        # German translations
│   │   │   └── locallang_db.xlf        # Backend labels (TCA fields, settings)
│   │   ├── Partials/Chatbot/Widget.html # Fluid partial: the full widget HTML
│   │   └── Templates/ContentElements/ChatbotWidget.html  # Fluid template wrapper
│   └── Public/
│       ├── Css/chatbot.css              # ~450 lines, themed via CSS variables
│       ├── Icons/Extension.svg          # Extension icon
│       └── JavaScript/chatbot.js        # esbuild-bundled output (~20kb IIFE), committed so installs don't need Node
├── Tests/Unit/
│   ├── Dto/ChatRequestTest.php
│   └── Service/
│       ├── ChatClientTest.php           # 3 tests: success, UpstreamException, RuntimeException
│       ├── ChatbotConfigTest.php
│       └── RateLimiterTest.php
├── Build/
│   ├── Sources/TypeScript/              # Frontend source (ES modules, strict TS)
│   │   ├── chatbot.ts                   # Entry point — boots the controller
│   │   ├── controller.ts                # ChatbotController class — state + events
│   │   ├── api.ts                       # Same-origin proxy transport (SSE + JSON)
│   │   ├── render.ts                    # DOM rendering helpers
│   │   ├── markdown.ts                  # Lightweight Markdown → HTML renderer
│   │   ├── storage.ts                   # sessionStorage persistence
│   │   ├── config.ts                    # Reads data-* attributes
│   │   └── types.ts                     # Shared types & constants
│   ├── build.mjs                        # esbuild bundler → Resources/Public/JavaScript/chatbot.js
│   ├── package.json                     # esbuild + typescript dev deps
│   ├── package-lock.json                # Committed for reproducible CI
│   └── tsconfig.json                    # strict TS config (typecheck-only; esbuild transpiles)
├── ext_localconf.php                    # Rate limiter cache config + TypoScript import
├── ext_emconf.php                       # Extension metadata for TYPO3 Extension Manager
├── ext_tables.sql                       # DB schema: adds 13 columns to pages table
├── composer.json                        # Dependencies: guzzlehttp/guzzle, typo3/cms-core
├── phpstan.neon                         # PHPStan level 8, analyses Classes/
├── phpunit.xml.dist                     # PHPUnit 11 config
└── .github/workflows/ci.yml            # CI: PHPStan + PHPUnit on PHP 8.2 & 8.3
```

## 4. Key Design Decisions

### Security
- **API key server-side only.** Resolved from site root page record; never exposed to browser.
- **Same-origin enforcement.** `Sec-Fetch-Site`, `Origin`, `Referer` triple-check.
- **HTTPS upstream enforced.** Non-HTTPS base URLs are rejected.
- **`%env(VAR)%` support.** API key field accepts environment variable placeholders.
- **Input validation.** Max 50 messages, 4000 chars each, role whitelist (`user|assistant|system`).
- **Error sanitization.** No upstream secrets leaked in error responses.
- **CSP-compatible.** Assets loaded via TYPO3 AssetCollector (supports CSP nonces).

### Configuration Hierarchy
1. **Root page TCA fields** (highest priority, per-site via site root page)
2. **Site Set settings** (`config.yaml` or Site Settings backend module, shared fallback)
3. **Hardcoded defaults** (e.g., `gpt-4o`, `#4F9EF7`)

### Multi-Language Design
- **Connection settings shared across languages** (`l10n_mode=exclude` on all config fields).
- **Only `start_message` and `title` are per-language.** Translate the root page to set custom greetings/titles per language.
- **German XLIFF included** (`de.locallang.xlf`) — UI strings + start message translated.
- **System prompt injection:** The proxy adds the site language to a system message so the LLM replies in the correct language.

### Rate Limiting
- 20 requests per minute per IP.
- Uses TYPO3's cache framework (`chatbot_ratelimit` cache, NOT in "pages" or "all" groups — "clear cache" must not reset counters).
- Custom cache: `VariableFrontend` + `SimpleFileBackend`, 120s default TTL.

### Streaming
- `SseStream` implements `SelfEmittableStreamInterface` — TYPO3's response emitter calls `emit()` directly.
- `X-Accel-Buffering: no` header sent to bypass nginx proxy buffering.
- `read_timeout` of 30s (not total timeout) — connection stays open for long generations.
- Errors mid-stream sent as SSE error events (HTTP 200 is already on the wire).

## 5. Key Classes & Their Dependencies

### `ChatProxyMiddleware`
- **Where:** Registered in `Configuration/RequestMiddlewares.php`
- **Position:** After `typo3/cms-frontend/site`, before `typo3/cms-frontend/page-resolver`
- **Endpoint:** `POST /chatbot/api/chat` — intercepts, validates, proxies
- **Dependencies:** `ConfigurationResolver`, `ChatClient`, `RateLimiter`, `ResponseFactoryInterface`, `LoggerInterface`

### `ConfigurationResolver`
- Resolves `ChatbotConfig` from `ServerRequestInterface`
- Reads site root page via `PageRepository`
- Handles language overlays for multi-language
- Resolves `%env(KEY)%` placeholders
- Falls back through: root page → site settings → defaults

### `ChatClient`
- Uses a dedicated `GuzzleHttp\Client` with `timeout: 30`, `connect_timeout: 5`, `http_errors: false`
- `complete()` — non-streaming, returns content string
- `completeStream()` — yields raw SSE chunks via generator
- Smart endpoint resolution: if base URL contains `/chat/completions`, use verbatim; otherwise append `/api/chat/completions`
- Error body parsing: handles OpenAI, OpenWebUI, and generic error formats

### `ChatbotConfigProcessor`
- TYPO3 Data Processor (`identifier: chatbot-config`)
- Runs during Fluid rendering
- Provides variables to template: `render`, `endpointUrl`, `model`, `startMessage`, `chatTitle`, colors, `position`, `avatarUrl`
- Handles global injection logic: `page.1909` renders globally; `tt_content.chatbot_widget` renders per-element

### `RateLimiter`
- Simple IP-based counter in TYPO3 cache
- Window: 60 seconds, max 20 requests
- `retryAfter()` for `Retry-After` header

### `ChatRequest` (DTO)
- Validates incoming JSON from POST body
- Max 50 messages, max 4000 chars per message
- Role whitelist: `user`, `assistant`, `system`

### `SseStream`
- Write-only self-emitting stream
- `emit()` echoes + flushes each chunk
- `__toString()` fallback for non-streaming emitters

## 6. Configuration Touch Points

| File | Purpose |
|------|---------|
| `ext_tables.sql` | DB schema for pages table (13 columns) |
| `Configuration/Services.yaml` | DI wiring (services + guzzle + cache) |
| `Configuration/RequestMiddlewares.php` | Middleware registration |
| `Configuration/TCA/Overrides/pages.php` | Backend form fields on root pages |
| `Configuration/TCA/Overrides/tt_content.php` | Content element registration |
| `Configuration/Sets/Chatbot/setup.typoscript` | Fluid rendering + content element |
| `Configuration/Sets/Chatbot/page.tsconfig` | New CE wizard entry |
| `Configuration/Sets/Chatbot/settings.definitions.yaml` | Site settings definitions |
| `ext_localconf.php` | Cache config + TypoScript import |
| `Configuration/Icons.php` | SVG icon registration |

## 7. Tests

Run with: `composer test` (PHPUnit 11, `phpunit.xml.dist`)

```
Tests/Unit/Dto/ChatRequestTest.php
Tests/Unit/Service/ChatClientTest.php    # 3 tests: success, upstream error, connection failure
Tests/Unit/Service/ChatbotConfigTest.php
Tests/Unit/Service/RateLimiterTest.php
```

Static analysis: `composer phpstan` (level 8)

## 8. Common Development Tasks

### Add a new TCA field
1. Add column to `ext_tables.sql`
2. Add field definition in `Configuration/TCA/Overrides/pages.php`
3. Add label in `Resources/Private/Language/locallang_db.xlf`
4. Update `ChatbotConfig` value object (constructor + factory methods)
5. Update `ConfigurationResolver::resolve()` to read the field
6. Pass through `ChatbotConfigProcessor::process()` → template
7. Use in `Widget.html` partial

### Add a new language
1. Create `Resources/Private/Language/{lang}.locallang.xlf`
2. Set `<target>` tags for all trans-unit IDs

### Add a new service
1. Create class in `Classes/Service/`
2. Autowiring is enabled — just add constructor dependencies
3. Test in `Tests/Unit/Service/`

### Run the full CI pipeline locally
```bash
composer install
composer phpstan
composer test
```

## 9. Upstream Compatibility

The `ChatClient::endpointUrl()` method auto-detects:
- **OpenAI/OpenRouter:** If base URL already contains `/chat/completions`, use verbatim
- **OpenWebUI:** Appends `/api/chat/completions` to base URL

Error body parsing handles:
- OpenAI: `{"error": {"message": "..."}}`
- OpenWebUI/FastAPI: `{"detail": "..."}`
- Generic: `{"error": "..."}` or `{"message": "..."}`

## 10. Frontend Assets

- **CSS:** `Resources/Public/Css/chatbot.css` — flat monochrome-on-primary theme via `--cb-primary`, `--cb-text`, `--cb-user-text` CSS custom properties. No border-radius; panel slides in from the configured side and grows from the corner when expanded.
- **JS source:** `Build/Sources/TypeScript/*.ts` — strict TypeScript, split into 8 focused ES modules (entry, controller, api, render, markdown, storage, config, types). No IIFE; modern TS (`interface`/`type`, `readonly`, `class` with `private`, literal-union types, `async`/`await`, `AbortController`, `??`/`?.`).
- **JS artifact:** `Resources/Public/JavaScript/chatbot.js` — esbuild IIFE bundle of the above (~20kb). Source is modular ESM; artifact is one file (TYPO3 core's approach).
- **Build:** `cd Build && npm install && npm run build` (or `watch` / `typecheck`). esbuild + TypeScript. Output committed so TYPO3 installs don't need Node.
- **JS features:** Session persistence via `sessionStorage`; SSE streaming with `AbortController` cancellation; auto-resize textarea; accessibility (`aria-live`, `role="log"`, `aria-expanded`, `aria-modal`, focus trap).
- **Assets loaded via** `f:asset.css` and `f:asset.script` (TYPO3 AssetCollector, CSP-compatible)

## 11. Notes for AI Assistants

- This is a **Composer-installed TYPO3 extension**, not a standalone app.
- **Frontend build:** `cd Build && npm install && npm run build` (esbuild). The committed `Resources/Public/JavaScript/chatbot.js` is the bundle output — rebuild it after editing any `.ts` file. `npm run typecheck` is the strict-TS gate (mirrors CI).
- The project root is `/home/ferdi/development/typo3-ai-chatbot/chatbot` — the parent directory (`typo3-ai-chatbot`) is likely the full TYPO3 installation.
- Method naming follows PSR-12, PHP 8.2+ features used (readonly properties, named arguments).
- All classes use `final` and `declare(strict_types=1)`.
- Dependency injection via TYPO3's Symfony DI — `Services.yaml` with `autowire: true`.
- When you need TYPO3 Core API references, check the vendor directory at the parent project level.
