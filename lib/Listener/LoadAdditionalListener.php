<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\IntegrationSignd\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\IntegrationSignd\AppInfo\Application;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\Util;

/** @template-implements IEventListener<LoadAdditionalScriptsEvent> */
class LoadAdditionalListener implements IEventListener {
    public function __construct(
        private IInitialState $initialState,
        private IConfig $config,
    ) {
    }

    public function handle(Event $event): void {
        if (!($event instanceof LoadAdditionalScriptsEvent)) {
            return;
        }

        $apiKey = $this->config->getAppValue(Application::APP_ID, 'api_key', '');
        $this->initialState->provideInitialState('api_key_set', $apiKey !== '');

        Util::addInitScript(Application::APP_ID, 'integration_signd-main-files');
    }
}
