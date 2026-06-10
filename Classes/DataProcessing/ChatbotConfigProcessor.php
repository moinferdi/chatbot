<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\DataProcessing;

use Moinferdi\Chatbot\Service\ConfigurationResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

final class ChatbotConfigProcessor implements DataProcessorInterface
{
    private const DEFAULT_START_MESSAGE = 'LLL:EXT:chatbot/Resources/Private/Language/locallang.xlf:widget.startMessage';

    public function __construct(
        private readonly ConfigurationResolver $configResolver,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $request = $this->getRequest($cObj);
        if (!$request) {
            return ['render' => false] + $processedData;
        }

        $config = $this->configResolver->resolve($request);

        if (!$config->enabled) {
            return ['render' => false] + $processedData;
        }

        $isGlobalInjection = $this->isGlobalInjection($cObj);
        $render = $isGlobalInjection ? $config->everywhere : true;

        if (!$isGlobalInjection && $config->everywhere) {
            $render = false;
        }

        $processedData['render'] = $render;
        $processedData['endpointUrl'] = '/chatbot/api/chat';
        $processedData['model'] = $config->model;
        // Per-language greeting: the DB override (resolved from the current
        // language's root-page translation) wins; otherwise fall back to the
        // localized default greeting shipped via XLF.
        $override = (string)($config->startMessage ?? '');
        $processedData['startMessage'] = $override !== ''
            ? $override
            : $this->localizedDefaultStartMessage($request);
        $processedData['colorPrimary'] = $config->colorPrimary;
        $processedData['colorBackground'] = $config->colorBackground;
        $processedData['colorText'] = $config->colorText;
        $processedData['position'] = $config->position;

        return $processedData;
    }

    private function localizedDefaultStartMessage(ServerRequestInterface $request): string
    {
        $language = $request->getAttribute('language');
        $languageService = $language instanceof SiteLanguage
            ? $this->languageServiceFactory->createFromSiteLanguage($language)
            : $this->languageServiceFactory->create('default');

        return $languageService->sL(self::DEFAULT_START_MESSAGE);
    }

    private function getRequest(ContentObjectRenderer $cObj): ?\Psr\Http\Message\ServerRequestInterface
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof \Psr\Http\Message\ServerRequestInterface) {
            return $request;
        }
        if ($cObj->getRequest() instanceof \Psr\Http\Message\ServerRequestInterface) {
            return $cObj->getRequest();
        }
        return null;
    }

    private function isGlobalInjection(ContentObjectRenderer $cObj): bool
    {
        $data = $cObj->data;
        return empty($data['uid']) || empty($data['CType']);
    }
}
