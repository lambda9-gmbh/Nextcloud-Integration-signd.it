<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\IntegrationSignd\Settings;

use GuzzleHttp\Exception\ClientException;
use OCA\IntegrationSignd\AppInfo\Application;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use Psr\Log\LoggerInterface;

class AdminSettings implements ISettings {
    public function __construct(
        private IInitialState $initialState,
        private SignApiService $signApiService,
        private LoggerInterface $logger,
    ) {
    }

    public function getForm(): TemplateResponse {
        $apiKey = $this->signApiService->getApiKey();
        $apiKeySet = $apiKey !== '';

        $this->initialState->provideInitialState('api_key_set', $apiKeySet);

        if ($apiKeySet) {
            try {
                $userInfo = $this->signApiService->getUserInfo();
                $this->initialState->provideInitialState('user_info', $userInfo);
                $this->initialState->provideInitialState('api_key_valid', true);
                $this->initialState->provideInitialState('signd_unreachable', false);
            } catch (ClientException $e) {
                // Server responded with 4xx → API key is invalid
                $this->logger->warning('signd API key validation failed', ['exception' => $e]);
                $this->initialState->provideInitialState('user_info', null);
                $this->initialState->provideInitialState('api_key_valid', false);
                $this->initialState->provideInitialState('signd_unreachable', false);
            } catch (\Exception $e) {
                // Connection error → service unreachable, key status unknown
                $this->logger->warning('Cannot reach signd.it server', ['exception' => $e]);
                $this->initialState->provideInitialState('user_info', null);
                $this->initialState->provideInitialState('api_key_valid', true);
                $this->initialState->provideInitialState('signd_unreachable', true);
            }
        }

        return new TemplateResponse(Application::APP_ID, 'settings/admin');
    }

    public function getSection(): string {
        return Application::APP_ID;
    }

    public function getPriority(): int {
        return 10;
    }
}
