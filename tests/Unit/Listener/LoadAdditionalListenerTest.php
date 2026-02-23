<?php

declare(strict_types=1);

namespace OCA\IntegrationSignd\Tests\Unit\Listener;

use OCA\IntegrationSignd\AppInfo\Application;
use OCA\IntegrationSignd\Listener\LoadAdditionalListener;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// Stub for OCA\Files\Event\LoadAdditionalScriptsEvent (not in nextcloud/ocp)
if (!class_exists(\OCA\Files\Event\LoadAdditionalScriptsEvent::class, false)) {
    eval('namespace OCA\Files\Event; class LoadAdditionalScriptsEvent extends \OCP\EventDispatcher\Event {}');
}

class LoadAdditionalListenerTest extends TestCase {
    private IInitialState&MockObject $initialState;
    private IConfig&MockObject $config;
    private LoadAdditionalListener $listener;

    protected function setUp(): void {
        $this->initialState = $this->createMock(IInitialState::class);
        $this->config = $this->createMock(IConfig::class);

        $this->listener = new LoadAdditionalListener(
            $this->initialState,
            $this->config,
        );
    }

    public function testHandlesLoadAdditionalScriptsEvent(): void {
        $this->config->method('getAppValue')
            ->with(Application::APP_ID, 'api_key', '')
            ->willReturn('test-key');

        $this->initialState->expects($this->once())
            ->method('provideInitialState')
            ->with('api_key_set', true);

        $event = new \OCA\Files\Event\LoadAdditionalScriptsEvent();
        $this->listener->handle($event);
    }

    public function testSetsApiKeySetFalseWhenNoKey(): void {
        $this->config->method('getAppValue')
            ->with(Application::APP_ID, 'api_key', '')
            ->willReturn('');

        $this->initialState->expects($this->once())
            ->method('provideInitialState')
            ->with('api_key_set', false);

        $event = new \OCA\Files\Event\LoadAdditionalScriptsEvent();
        $this->listener->handle($event);
    }

    public function testIgnoresOtherEvents(): void {
        $this->initialState->expects($this->never())
            ->method('provideInitialState');

        $event = new Event();
        $this->listener->handle($event);
    }
}
