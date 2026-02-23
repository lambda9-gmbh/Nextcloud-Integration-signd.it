<?php

declare(strict_types=1);

namespace OCA\IntegrationSignd\Tests\Unit\Controller;

use OCA\IntegrationSignd\Controller\SettingsController;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SettingsControllerTest extends TestCase {
    private SignApiService&MockObject $signApiService;
    private LoggerInterface&MockObject $logger;
    private SettingsController $controller;

    protected function setUp(): void {
        $request = $this->createMock(IRequest::class);
        $this->signApiService = $this->createMock(SignApiService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new SettingsController(
            $request,
            $this->signApiService,
            $this->logger,
        );
    }

    // ── getConfig ──

    public function testGetConfigWithNoApiKey(): void {
        $this->signApiService->method('getApiKey')->willReturn('');

        $response = $this->controller->getConfig();
        $data = $response->getData();

        $this->assertFalse($data['apiKeySet']);
        $this->assertNull($data['userInfo']);
    }

    public function testGetConfigWithValidKey(): void {
        $this->signApiService->method('getApiKey')->willReturn('valid-key');
        $this->signApiService->method('getUserInfo')
            ->willReturn(['email' => 'test@example.com', 'clearName' => 'Test User']);

        $response = $this->controller->getConfig();
        $data = $response->getData();

        $this->assertTrue($data['apiKeySet']);
        $this->assertSame('test@example.com', $data['userInfo']['email']);
    }

    public function testGetConfigWithInvalidKeyLogsWarning(): void {
        $this->signApiService->method('getApiKey')->willReturn('expired-key');
        $this->signApiService->method('getUserInfo')
            ->willThrowException(new \RuntimeException('Unauthorized'));

        $this->logger->expects($this->once())->method('warning');

        $response = $this->controller->getConfig();
        $data = $response->getData();

        $this->assertTrue($data['apiKeySet']);
        $this->assertNull($data['userInfo']);
    }

    // ── saveApiKey ──

    public function testSaveApiKeyRejectsEmptyString(): void {
        $response = $this->controller->saveApiKey('');
        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    public function testSaveApiKeyRejectsWhitespaceOnly(): void {
        $response = $this->controller->saveApiKey('   ');
        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    public function testSaveApiKeyValidatesBeforeSaving(): void {
        $userInfo = ['email' => 'test@example.com'];

        // validateApiKey must be called BEFORE setApiKey
        $callOrder = [];
        $this->signApiService->expects($this->once())
            ->method('validateApiKey')
            ->with('test-key')
            ->willReturnCallback(function () use (&$callOrder, $userInfo) {
                $callOrder[] = 'validate';
                return $userInfo;
            });
        $this->signApiService->expects($this->once())
            ->method('setApiKey')
            ->with('test-key')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'setApiKey';
            });

        $response = $this->controller->saveApiKey('test-key');
        $data = $response->getData();

        $this->assertSame(['validate', 'setApiKey'], $callOrder);
        $this->assertTrue($data['success']);
        $this->assertSame($userInfo, $data['userInfo']);
    }

    public function testSaveApiKeyReturnsErrorOnInvalidKey(): void {
        $this->signApiService->method('validateApiKey')
            ->willThrowException(new \RuntimeException('Invalid key'));

        $response = $this->controller->saveApiKey('bad-key');

        $this->assertGreaterThanOrEqual(400, $response->getStatus());
        $this->signApiService->expects($this->never())->method('setApiKey');
    }

    // ── login ──

    public function testLoginRejectsEmptyEmail(): void {
        $response = $this->controller->login('', 'password');
        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    public function testLoginRejectsEmptyPassword(): void {
        $response = $this->controller->login('test@example.com', '');
        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    public function testLoginRejectsWhitespaceOnlyFields(): void {
        $response = $this->controller->login('  ', '  ');
        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    public function testLoginSavesReturnedApiKey(): void {
        $this->signApiService->method('login')
            ->willReturn(['apikey' => 'new-key-123', 'clearName' => 'Test', 'language' => 'de']);

        $this->signApiService->expects($this->once())
            ->method('setApiKey')
            ->with('new-key-123');

        $response = $this->controller->login('test@example.com', 'secret');
        $data = $response->getData();

        $this->assertTrue($data['success']);
        $this->assertSame('test@example.com', $data['userInfo']['email']);
        $this->assertSame('Test', $data['userInfo']['clearName']);
    }

    public function testLoginReturns401WhenNoApiKeyInResponse(): void {
        $this->signApiService->method('login')
            ->willReturn(['email' => 'test@example.com']); // no 'apikey' field

        $response = $this->controller->login('test@example.com', 'secret');

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }

    public function testLoginReturnsErrorOnApiFailure(): void {
        $this->signApiService->method('login')
            ->willThrowException(new \RuntimeException('Connection failed'));

        $response = $this->controller->login('test@example.com', 'secret');

        $this->assertGreaterThanOrEqual(400, $response->getStatus());
    }

    // ── register ──

    public function testRegisterRejectsWithoutAgb(): void {
        $response = $this->controller->register(
            'premium', 'Org', 'Street', '1', '12345', 'City',
            'Test User', 'test@example.com', 'pass123',
            false, true, // agbAccepted=false
        );
        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    public function testRegisterRejectsWithoutDsb(): void {
        $response = $this->controller->register(
            'premium', 'Org', 'Street', '1', '12345', 'City',
            'Test User', 'test@example.com', 'pass123',
            true, false, // dsbAccepted=false
        );
        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    public function testRegisterSavesReturnedApiKey(): void {
        $this->signApiService->method('registerAccount')
            ->willReturn(['apiKey' => 'new-key', 'accountId' => 'acc-123']);

        $this->signApiService->expects($this->once())
            ->method('setApiKey')
            ->with('new-key');

        $response = $this->controller->register(
            'premium', 'Org', 'Street', '1', '12345', 'City',
            'Test User', 'test@example.com', 'pass123',
            true, true,
        );
        $data = $response->getData();

        $this->assertTrue($data['success']);
        $this->assertSame('acc-123', $data['accountId']);
    }

    public function testRegisterIncludesVatIdWhenProvided(): void {
        $this->signApiService->expects($this->once())
            ->method('registerAccount')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame('DE123456789', $data['vatId']);
                return true;
            }))
            ->willReturn(['apiKey' => 'k', 'accountId' => 'a']);

        $this->controller->register(
            'premium', 'Org', 'Street', '1', '12345', 'City',
            'Test', 'test@example.com', 'pass',
            true, true, 'DE', 'DE123456789',
        );
    }

    public function testRegisterExcludesVatIdWhenEmpty(): void {
        $this->signApiService->expects($this->once())
            ->method('registerAccount')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayNotHasKey('vatId', $data);
                return true;
            }))
            ->willReturn(['apiKey' => 'k', 'accountId' => 'a']);

        $this->controller->register(
            'premium', 'Org', 'Street', '1', '12345', 'City',
            'Test', 'test@example.com', 'pass',
            true, true, 'DE', '', // vatId empty
        );
    }

    public function testRegisterIncludesCouponCodeWhenProvided(): void {
        $this->signApiService->expects($this->once())
            ->method('registerAccount')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame('SAVE50', $data['couponCode']);
                return true;
            }))
            ->willReturn(['apiKey' => 'k', 'accountId' => 'a']);

        $this->controller->register(
            'premium', 'Org', 'Street', '1', '12345', 'City',
            'Test', 'test@example.com', 'pass',
            true, true, 'DE', '', 'SAVE50',
        );
    }

    public function testRegisterReturns500WhenNoApiKeyInResponse(): void {
        $this->signApiService->method('registerAccount')
            ->willReturn(['accountId' => 'a']); // no apiKey

        $response = $this->controller->register(
            'premium', 'Org', 'Street', '1', '12345', 'City',
            'Test', 'test@example.com', 'pass',
            true, true,
        );

        $this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
    }

    // ── deleteApiKey ──

    public function testDeleteApiKeySetsEmptyString(): void {
        $this->signApiService->expects($this->once())
            ->method('setApiKey')
            ->with('');

        $response = $this->controller->deleteApiKey();
        $data = $response->getData();

        $this->assertTrue($data['success']);
    }

    // ── validate ──

    public function testValidateReturnsInvalidWhenNoKey(): void {
        $this->signApiService->method('getApiKey')->willReturn('');

        $response = $this->controller->validate();
        $data = $response->getData();

        $this->assertSame(200, $response->getStatus());
        $this->assertFalse($data['valid']);
        $this->assertSame('No API key configured', $data['error']);
    }

    public function testValidateReturnsValidWithWorkingKey(): void {
        $this->signApiService->method('getApiKey')->willReturn('good-key');
        $this->signApiService->method('getUserInfo')
            ->willReturn(['email' => 'test@example.com']);

        $response = $this->controller->validate();
        $data = $response->getData();

        $this->assertSame(200, $response->getStatus());
        $this->assertTrue($data['valid']);
        $this->assertSame('test@example.com', $data['userInfo']['email']);
    }

    public function testValidateReturnsInvalidWithExpiredKey(): void {
        $this->signApiService->method('getApiKey')->willReturn('expired-key');
        $this->signApiService->method('getUserInfo')
            ->willThrowException(new \RuntimeException('Expired'));

        $response = $this->controller->validate();
        $data = $response->getData();

        $this->assertSame(200, $response->getStatus());
        $this->assertFalse($data['valid']);
        $this->assertSame('API key is invalid or expired', $data['error']);
    }

    // ── getPrices ──

    public function testGetPricesReturnsApiResponse(): void {
        $prices = ['premium' => ['perProcess' => 1.5], 'enterprise' => ['perProcess' => 0.8]];
        $this->signApiService->method('getPrices')->willReturn($prices);

        $response = $this->controller->getPrices();

        $this->assertSame(200, $response->getStatus());
        $this->assertSame($prices, $response->getData());
    }

    public function testGetPricesReturnsErrorWhenApiDown(): void {
        $this->signApiService->method('getPrices')
            ->willThrowException(new \RuntimeException('Connection failed'));

        $response = $this->controller->getPrices();

        $this->assertGreaterThanOrEqual(400, $response->getStatus());
    }
}
