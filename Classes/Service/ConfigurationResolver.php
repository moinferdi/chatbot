<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class ConfigurationResolver
{
    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {}

    public function resolve(ServerRequestInterface $request): ChatbotConfig
    {
        /** @var Site|null $site */
        $site = $request->getAttribute('site');
        if (!$site) {
            return ChatbotConfig::disabled();
        }

        /** @var SiteLanguage|null $language */
        $language = $request->getAttribute('language');
        $languageId = $language ? $language->getLanguageId() : 0;

        $rootPageId = $site->getRootPageId();

        $rootPage = $this->pageRepository->getPage($rootPageId);
        if (!$rootPage) {
            return ChatbotConfig::disabled();
        }

        if ($languageId > 0 && $language instanceof SiteLanguage) {
            // Overlay the root page with its translation, honouring the site
            // language's configured fallback chain. Connection fields are
            // l10n_mode=exclude, so only the start message changes per language.
            $overlay = $this->pageRepository->getLanguageOverlay(
                'pages',
                $rootPage,
                LanguageAspectFactory::createFromSiteLanguage($language),
            );
            if (is_array($overlay)) {
                $rootPage = $overlay;
            }
        }

        $settings = $site->getConfiguration()['settings']['chatbot'] ?? [];

        $enabled = (bool)($rootPage['tx_chatbot_enabled'] ?? $settings['enabled'] ?? false);
        if (!$enabled) {
            return ChatbotConfig::disabled();
        }

        $everywhere = (bool)($rootPage['tx_chatbot_everywhere'] ?? false);
        $baseUrl = ($rootPage['tx_chatbot_base_url'] ?? '') ?: ($settings['openWebUiBaseUrl'] ?? '');
        $model = ($rootPage['tx_chatbot_model'] ?? '') ?: ($settings['defaultModel'] ?? 'gpt-4o');
        $apiKey = $this->resolveEnvPlaceholder((string)($rootPage['tx_chatbot_api_key'] ?? ''));

        $colorPrimary = ($rootPage['tx_chatbot_color_primary'] ?? '') ?: ($settings['color']['primary'] ?? '#4F9EF7');
        $colorText = ($rootPage['tx_chatbot_color_text'] ?? '') ?: ($settings['color']['text'] ?? '#ffffff');
        $colorUserText = ($rootPage['tx_chatbot_color_user_text'] ?? '') ?: ($settings['color']['userText'] ?? '#1a1a1a');
        $position = ($rootPage['tx_chatbot_position'] ?? '') ?: ($settings['position'] ?? 'bottom-right');
        $startMessage = ($rootPage['tx_chatbot_start_message'] ?? '') ?: null;
        $title = ($rootPage['tx_chatbot_title'] ?? '') ?: null;

        return ChatbotConfig::enabled(
            baseUrl: $baseUrl, apiKey: $apiKey, model: $model, everywhere: $everywhere,
            colorPrimary: $colorPrimary, colorText: $colorText, colorUserText: $colorUserText,
            position: $position, startMessage: $startMessage, title: $title,
        );
    }

    private function resolveEnvPlaceholder(string $value): string
    {
        if ($value === '' || !str_contains($value, '%env(')) {
            return $value;
        }
        return (string) preg_replace_callback(
            '/%env\(([A-Za-z_][A-Za-z0-9_]*)\)%/',
            static fn(array $matches): string => getenv($matches[1]) ?: '',
            $value
        );
    }
}
