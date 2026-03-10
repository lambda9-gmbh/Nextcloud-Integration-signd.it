<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\IntegrationSignd\Controller;

use OCA\IntegrationSignd\AppInfo\Application;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class PageController extends Controller {
    public function __construct(
        IRequest $request,
        private IInitialState $initialState,
        private SignApiService $signApiService,
        private IUserSession $userSession,
        private IURLGenerator $urlGenerator,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        $apiKey = $this->signApiService->getApiKey();
        $this->initialState->provideInitialState('api_key_set', $apiKey !== '');

        $user = $this->userSession->getUser();
        $this->initialState->provideInitialState('current_user_id', $user?->getUID() ?? '');

        $instanceUrl = $this->urlGenerator->getAbsoluteURL('/');
        $this->initialState->provideInitialState('instance_url', $instanceUrl);

        return new TemplateResponse(Application::APP_ID, 'overview/index');
    }
}
