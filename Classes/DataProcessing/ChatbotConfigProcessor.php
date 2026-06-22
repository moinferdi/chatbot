<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\DataProcessing;

use Moinferdi\Chatbot\Service\ConfigurationResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

final class ChatbotConfigProcessor implements DataProcessorInterface
{
    private const DEFAULT_START_MESSAGE = 'LLL:EXT:chatbot/Resources/Private/Language/locallang.xlf:widget.startMessage';
    private const DEFAULT_TITLE = 'LLL:EXT:chatbot/Resources/Private/Language/locallang.xlf:widget.title';

    public function __construct(
        private readonly ConfigurationResolver $configResolver,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly FileRepository $fileRepository,
    ) {}

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     * @return array<string, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $request = $this->getRequest($cObj);
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
        // Per-language title: DB override wins, otherwise fall back to the
        // localized default title.
        $title = (string)($config->title ?? '');
        $processedData['chatTitle'] = $title !== ''
            ? $title
            : $this->localizedDefaultTitle($request);
        $processedData['colorPrimary'] = $config->colorPrimary;
        $processedData['colorText'] = $config->colorText;
        $processedData['colorUserText'] = $config->colorUserText;
        $processedData['position'] = $config->position;
        $processedData['avatarUrl'] = $this->resolveAvatarUrl($request);

        return $processedData;
    }

    private function localizedDefaultStartMessage(ServerRequestInterface $request): string
    {
        return $this->translate(self::DEFAULT_START_MESSAGE, $request);
    }

    private function localizedDefaultTitle(ServerRequestInterface $request): string
    {
        return $this->translate(self::DEFAULT_TITLE, $request);
    }

    private function translate(string $lllKey, ServerRequestInterface $request): string
    {
        $language = $request->getAttribute('language');
        $languageService = $language instanceof SiteLanguage
            ? $this->languageServiceFactory->createFromSiteLanguage($language)
            : $this->languageServiceFactory->create('default');

        return $languageService->sL($lllKey);
    }

    /**
     * Resolve the FAL avatar image URL from the site root page.
     * Returns empty string if no avatar is configured.
     */
    private function resolveAvatarUrl(ServerRequestInterface $request): string
    {
        /** @var Site|null $site */
        $site = $request->getAttribute('site');
        if (!$site) {
            return '';
        }

        $rootPageId = $site->getRootPageId();

        try {
            $fileObjects = $this->fileRepository->findByRelation(
                'pages',
                'tx_chatbot_avatar',
                $rootPageId
            );
            if (!empty($fileObjects)) {
                $file = $fileObjects[0];
                $publicUrl = $file->getPublicUrl();
                if ($publicUrl !== null) {
                    return $publicUrl;
                }
            }
        } catch (\Throwable) {
            // FAL lookup may fail in some contexts; degrade gracefully.
        }

        return '';
    }

    private function getRequest(ContentObjectRenderer $cObj): \Psr\Http\Message\ServerRequestInterface
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof \Psr\Http\Message\ServerRequestInterface) {
            return $request;
        }
        return $cObj->getRequest();
    }

    private function isGlobalInjection(ContentObjectRenderer $cObj): bool
    {
        $data = $cObj->data;
        return empty($data['uid']) || empty($data['CType']);
    }
}
