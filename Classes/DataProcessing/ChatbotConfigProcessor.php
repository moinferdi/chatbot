<?php

declare(strict_types=1);

namespace Moinferdi\Chatbot\DataProcessing;

use Moinferdi\Chatbot\Service\ConfigurationResolver;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

final class ChatbotConfigProcessor implements DataProcessorInterface
{
    public function __construct(
        private readonly ConfigurationResolver $configResolver,
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
        $processedData['startMessage'] = $config->startMessage ?? '';
        $processedData['colorPrimary'] = $config->colorPrimary;
        $processedData['colorBackground'] = $config->colorBackground;
        $processedData['colorText'] = $config->colorText;
        $processedData['position'] = $config->position;

        return $processedData;
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
