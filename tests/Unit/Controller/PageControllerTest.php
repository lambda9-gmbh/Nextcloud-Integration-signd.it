<?php

declare(strict_types=1);

namespace OCA\IntegrationSignd\Tests\Unit\Controller;

use OCA\IntegrationSignd\Controller\PageController;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PageControllerTest extends TestCase {
    private SignApiService&MockObject $signApiService;
    private IInitialState&MockObject $initialState;
    private IUserSession&MockObject $userSession;
    private IURLGenerator&MockObject $urlGenerator;
    private PageController $controller;

    protected function setUp(): void {
        $request = $this->createMock(IRequest::class);
        $this->signApiService = $this->createMock(SignApiService::class);
        $this->initialState = $this->createMock(IInitialState::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->urlGenerator = $this->createMock(IURLGenerator::class);

        $this->controller = new PageController(
            $request,
            $this->initialState,
            $this->signApiService,
            $this->userSession,
            $this->urlGenerator,
        );
    }

    public function testIndexProvidesApiKeySetTrue(): void {
        $this->signApiService->method('getApiKey')->willReturn('some-key');
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('admin');
        $this->userSession->method('getUser')->willReturn($user);
        $this->urlGenerator->method('getAbsoluteURL')->willReturn('https://cloud.example.com/');

        $calls = [];
        $this->initialState->method('provideInitialState')
            ->willReturnCallback(function (string $key, mixed $value) use (&$calls): void {
                $calls[$key] = $value;
            });

        $this->controller->index();

        $this->assertTrue($calls['api_key_set']);
    }

    public function testIndexProvidesApiKeySetFalse(): void {
        $this->signApiService->method('getApiKey')->willReturn('');
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('admin');
        $this->userSession->method('getUser')->willReturn($user);
        $this->urlGenerator->method('getAbsoluteURL')->willReturn('https://cloud.example.com/');

        $calls = [];
        $this->initialState->method('provideInitialState')
            ->willReturnCallback(function (string $key, mixed $value) use (&$calls): void {
                $calls[$key] = $value;
            });

        $this->controller->index();

        $this->assertFalse($calls['api_key_set']);
    }

    public function testIndexProvidesCurrentUserId(): void {
        $this->signApiService->method('getApiKey')->willReturn('');
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('john');
        $this->userSession->method('getUser')->willReturn($user);
        $this->urlGenerator->method('getAbsoluteURL')->willReturn('https://cloud.example.com/');

        $calls = [];
        $this->initialState->method('provideInitialState')
            ->willReturnCallback(function (string $key, mixed $value) use (&$calls): void {
                $calls[$key] = $value;
            });

        $this->controller->index();

        $this->assertSame('john', $calls['current_user_id']);
    }

    public function testIndexHandlesNullUser(): void {
        $this->signApiService->method('getApiKey')->willReturn('');
        $this->userSession->method('getUser')->willReturn(null);
        $this->urlGenerator->method('getAbsoluteURL')->willReturn('https://cloud.example.com/');

        $calls = [];
        $this->initialState->method('provideInitialState')
            ->willReturnCallback(function (string $key, mixed $value) use (&$calls): void {
                $calls[$key] = $value;
            });

        $this->controller->index();

        $this->assertSame('', $calls['current_user_id']);
    }

    public function testIndexReturnsTemplateResponse(): void {
        $this->signApiService->method('getApiKey')->willReturn('');
        $this->userSession->method('getUser')->willReturn(null);
        $this->urlGenerator->method('getAbsoluteURL')->willReturn('https://cloud.example.com/');

        $response = $this->controller->index();

        $this->assertInstanceOf(TemplateResponse::class, $response);
        $this->assertSame('overview/index', $response->getTemplateName());
    }
}
