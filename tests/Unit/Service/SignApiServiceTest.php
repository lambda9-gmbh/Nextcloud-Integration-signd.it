<?php

declare(strict_types=1);

namespace OCA\IntegrationSignd\Tests\Unit\Service;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OCA\IntegrationSignd\AppInfo\Application;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\Http\Client\LocalServerException;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SignApiServiceTest extends TestCase {
    private IClientService&MockObject $clientService;
    private IConfig&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private SignApiService $service;

    protected function setUp(): void {
        $this->clientService = $this->createMock(IClientService::class);
        $this->config = $this->createMock(IConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new SignApiService(
            $this->clientService,
            $this->config,
            $this->logger,
        );
    }

    // ── getApiUrl() 3-Wege-Fallback ──

    public function testGetApiUrlReturnsAppConfigWhenSet(): void {
        $this->config->method('getAppValue')
            ->with(Application::APP_ID, 'api_url', '')
            ->willReturn('https://custom.signd.it/');

        $this->assertSame('https://custom.signd.it', $this->service->getApiUrl());
    }

    public function testGetApiUrlReturnsEnvVarWhenNoAppConfig(): void {
        $this->config->method('getAppValue')
            ->with(Application::APP_ID, 'api_url', '')
            ->willReturn('');

        putenv('SIGND_BASE_URL=http://localhost:7755/');
        try {
            $this->assertSame('http://localhost:7755', $this->service->getApiUrl());
        } finally {
            putenv('SIGND_BASE_URL');
        }
    }

    public function testGetApiUrlReturnsDefaultWhenNothingConfigured(): void {
        $this->config->method('getAppValue')
            ->with(Application::APP_ID, 'api_url', '')
            ->willReturn('');

        putenv('SIGND_BASE_URL');

        $this->assertSame('https://signd.it', $this->service->getApiUrl());
    }

    // ── apiErrorResponse() ──

    public function testApiErrorResponseForLocalServerException(): void {
        $exception = new LocalServerException();
        $response = SignApiService::apiErrorResponse($exception, 'Something failed');

        $this->assertSame(Http::STATUS_BAD_GATEWAY, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('Cannot reach signd.it server', $data['error']);
        $this->assertSame('SIGND_UNREACHABLE', $data['errorCode']);
    }

    public function testApiErrorResponseForClientExceptionWithJsonBody(): void {
        $psrResponse = new Response(401, [], json_encode(['error' => 'Invalid API key']));
        $psrRequest = new Request('GET', 'https://signd.it/api/test');
        $exception = new ClientException('Unauthorized', $psrRequest, $psrResponse);

        $response = SignApiService::apiErrorResponse($exception, 'Fallback');

        $this->assertSame(401, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('Invalid API key', $data['error']);
        $this->assertSame('SIGND_UNAUTHORIZED', $data['errorCode']);
    }

    public function testApiErrorResponseForClientExceptionWithPlainBody(): void {
        $psrResponse = new Response(400, [], 'Bad Request');
        $psrRequest = new Request('POST', 'https://signd.it/api/test');
        $exception = new ClientException('Error', $psrRequest, $psrResponse);

        $response = SignApiService::apiErrorResponse($exception, 'Fallback');

        $this->assertSame(400, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('Bad Request', $data['error']);
        $this->assertSame('SIGND_API_ERROR', $data['errorCode']);
    }

    public function testApiErrorResponseForGenericException(): void {
        $exception = new \RuntimeException('Connection timeout');
        $response = SignApiService::apiErrorResponse($exception, 'Something went wrong', 503);

        $this->assertSame(503, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('Something went wrong', $data['error']);
        $this->assertSame('SIGND_UNKNOWN_ERROR', $data['errorCode']);
    }

    // ── login() ohne API-Key ──

    public function testLoginSendsRequestWithoutApiKey(): void {
        // getApiUrl() needs api_url config → return empty to fall through to default
        $this->config->method('getAppValue')
            ->willReturnMap([
                [Application::APP_ID, 'api_url', '', ''],
                [Application::APP_ID, 'api_key', '', ''],
            ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')
            ->willReturn(json_encode(['apiKey' => 'test-key-123']));

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/v2/api-login'),
                $this->callback(function (array $options): bool {
                    // Verify no X-API-KEY header is sent
                    $this->assertArrayNotHasKey('X-API-KEY', $options['headers']);
                    // Verify correct payload
                    $body = json_decode($options['body'], true);
                    $this->assertSame('test@example.com', $body['email']);
                    $this->assertSame('secret', $body['password']);
                    return true;
                })
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);

        $result = $this->service->login('test@example.com', 'secret');
        $this->assertSame('test-key-123', $result['apiKey']);
    }

    // ── getApiKey / setApiKey ──

    public function testGetApiKeyReturnsStoredKey(): void {
        $this->config->method('getAppValue')
            ->with(Application::APP_ID, 'api_key', '')
            ->willReturn('stored-key-abc');

        $this->assertSame('stored-key-abc', $this->service->getApiKey());
    }

    public function testGetApiKeyReturnsEmptyWhenNotSet(): void {
        $this->config->method('getAppValue')
            ->with(Application::APP_ID, 'api_key', '')
            ->willReturn('');

        $this->assertSame('', $this->service->getApiKey());
    }

    public function testSetApiKeyPersistsValue(): void {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with(Application::APP_ID, 'api_key', 'new-key-xyz');

        $this->service->setApiKey('new-key-xyz');
    }

    // ── HTTP helpers (tested via public methods) ──

    private function mockConfigForHttpCalls(): void {
        $this->config->method('getAppValue')
            ->willReturnMap([
                [Application::APP_ID, 'api_url', '', ''],
                [Application::APP_ID, 'api_key', '', 'test-api-key'],
            ]);
    }

    private function mockClientGet(string $responseBody): IClient&MockObject {
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $mockClient = $this->createMock(IClient::class);
        $mockClient->method('get')->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        return $mockClient;
    }

    private function mockClientPost(string $responseBody): IClient&MockObject {
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $mockClient = $this->createMock(IClient::class);
        $mockClient->method('post')->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        return $mockClient;
    }

    public function testGetRequestLogsAndRethrowsOnException(): void {
        $this->mockConfigForHttpCalls();

        $mockClient = $this->createMock(IClient::class);
        $mockClient->method('get')
            ->willThrowException(new \RuntimeException('Connection refused'));
        $this->clientService->method('newClient')->willReturn($mockClient);

        $this->logger->expects($this->once())->method('error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->service->getUserInfo();
    }

    public function testGetRequestReturnsEmptyArrayForNullJson(): void {
        $this->mockConfigForHttpCalls();
        $this->mockClientGet('null');

        $result = $this->service->getUserInfo();
        $this->assertSame([], $result);
    }

    public function testPostRequestWithEmptyResponseBody(): void {
        $this->mockConfigForHttpCalls();
        $this->mockClientPost('');

        $result = $this->service->startWizard(['doc' => 'base64data']);
        $this->assertSame([], $result);
    }

    public function testPostRequestWithApiKeyIncludesHeader(): void {
        $this->mockConfigForHttpCalls();

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')->willReturn('{}');

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $this->assertArrayHasKey('X-API-KEY', $options['headers']);
                    $this->assertSame('test-api-key', $options['headers']['X-API-KEY']);
                    return true;
                })
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        $this->service->startWizard(['doc' => 'test']);
    }

    public function testPostRequestWithoutApiKeyOmitsHeader(): void {
        $this->mockConfigForHttpCalls();

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')->willReturn('{}');

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $this->assertArrayNotHasKey('X-API-KEY', $options['headers']);
                    return true;
                })
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        $this->service->registerAccount(['email' => 'test@example.com']);
    }

    // ── validateApiKey ──

    public function testValidateApiKeyCallsUserInfoWithProvidedKey(): void {
        $this->config->method('getAppValue')
            ->willReturnMap([
                [Application::APP_ID, 'api_url', '', ''],
                [Application::APP_ID, 'api_key', '', 'stored-key'],
            ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')
            ->willReturn(json_encode(['email' => 'test@example.com']));

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('get')
            ->with(
                $this->stringContains('/api/user-info'),
                $this->callback(function (array $options): bool {
                    // Must use the PASSED key, not the stored one
                    $this->assertSame('provided-key', $options['headers']['X-API-KEY']);
                    return true;
                })
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        $this->service->validateApiKey('provided-key');
    }

    public function testValidateApiKeyReturnsUserInfoOnSuccess(): void {
        $this->config->method('getAppValue')
            ->willReturnMap([
                [Application::APP_ID, 'api_url', '', ''],
            ]);

        $userInfo = ['email' => 'test@example.com', 'clearName' => 'Test User'];

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')->willReturn(json_encode($userInfo));

        $mockClient = $this->createMock(IClient::class);
        $mockClient->method('get')->willReturn($mockResponse);
        $this->clientService->method('newClient')->willReturn($mockClient);

        $result = $this->service->validateApiKey('valid-key');
        $this->assertSame('test@example.com', $result['email']);
        $this->assertSame('Test User', $result['clearName']);
    }

    public function testValidateApiKeyThrowsOnInvalidKey(): void {
        $this->config->method('getAppValue')
            ->willReturnMap([
                [Application::APP_ID, 'api_url', '', ''],
            ]);

        $psrResponse = new Response(401, [], 'Unauthorized');
        $psrRequest = new Request('GET', 'https://signd.it/api/user-info');

        $mockClient = $this->createMock(IClient::class);
        $mockClient->method('get')
            ->willThrowException(new ClientException('Unauthorized', $psrRequest, $psrResponse));
        $this->clientService->method('newClient')->willReturn($mockClient);

        $this->expectException(ClientException::class);
        $this->service->validateApiKey('bad-key');
    }

    // ── registerAccount / getPrices ──

    public function testRegisterAccountSendsWithoutApiKey(): void {
        $this->mockConfigForHttpCalls();

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')
            ->willReturn(json_encode(['apiKey' => 'new-key']));

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/register-account'),
                $this->callback(function (array $options): bool {
                    $this->assertArrayNotHasKey('X-API-KEY', $options['headers']);
                    return true;
                })
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        $this->service->registerAccount(['email' => 'test@example.com']);
    }

    public function testGetPricesSendsWithoutApiKey(): void {
        $this->mockConfigForHttpCalls();

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')
            ->willReturn(json_encode(['premium' => ['perProcess' => 1.5]]));

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/prices'),
                $this->callback(function (array $options): bool {
                    $this->assertArrayNotHasKey('X-API-KEY', $options['headers']);
                    return true;
                })
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        $this->service->getPrices();
    }

    // ── Process methods ──

    public function testStartWizardSendsWithApiKey(): void {
        $this->mockConfigForHttpCalls();

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')
            ->willReturn(json_encode(['processId' => 'p1', 'wizardUrl' => 'https://signd.it/wizard/p1']));

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/start-wizard'),
                $this->callback(function (array $options): bool {
                    $this->assertSame('test-api-key', $options['headers']['X-API-KEY']);
                    return true;
                })
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        $this->service->startWizard(['doc' => 'base64']);
    }

    public function testGetMetaPassesIdAsQueryParam(): void {
        $this->mockConfigForHttpCalls();

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')
            ->willReturn(json_encode(['processId' => 'proc-123']));

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('get')
            ->with(
                $this->callback(function (string $url): bool {
                    $this->assertStringContainsString('/api/get-meta?', $url);
                    $this->assertStringContainsString('id=proc-123', $url);
                    return true;
                }),
                $this->anything()
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        $this->service->getMeta('proc-123');
    }

    public function testListProcessesBuildsQueryString(): void {
        $this->mockConfigForHttpCalls();

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')
            ->willReturn(json_encode(['numHits' => 0, 'processes' => []]));

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('get')
            ->with(
                $this->callback(function (string $url): bool {
                    $this->assertStringContainsString('status=RUNNING', $url);
                    $this->assertStringContainsString('limit=10', $url);
                    $this->assertStringContainsString('offset=20', $url);
                    return true;
                }),
                $this->anything()
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        $this->service->listProcesses(['status' => 'RUNNING', 'limit' => 10, 'offset' => 20]);
    }

    public function testCancelProcessPassesIdAndReason(): void {
        $this->mockConfigForHttpCalls();

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')->willReturn('');

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/cancel-process'),
                $this->callback(function (array $options): bool {
                    $body = json_decode($options['body'], true);
                    $this->assertSame('proc-123', $body['id']);
                    $this->assertSame('Not needed', $body['reason']);
                    return true;
                })
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        $this->service->cancelProcess('proc-123', 'Not needed');
    }

    public function testGetFinishedPdfReturnsBinaryBody(): void {
        $this->mockConfigForHttpCalls();

        $pdfBinary = '%PDF-1.4 binary content here';

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getBody')->willReturn($pdfBinary);

        $mockClient = $this->createMock(IClient::class);
        $mockClient->expects($this->once())
            ->method('get')
            ->with(
                $this->callback(function (string $url): bool {
                    $this->assertStringContainsString('/api/finished?', $url);
                    $this->assertStringContainsString('id=proc-123', $url);
                    return true;
                }),
                $this->anything()
            )
            ->willReturn($mockResponse);

        $this->clientService->method('newClient')->willReturn($mockClient);
        $result = $this->service->getFinishedPdf('proc-123');

        // Must return raw string, not JSON-decoded
        $this->assertSame($pdfBinary, $result);
    }

    public function testCancelWizardReturnsVoid(): void {
        $this->mockConfigForHttpCalls();
        $this->mockClientPost('');

        // Should not throw on empty response
        $this->service->cancelWizard('proc-123');
        $this->assertTrue(true); // Assertion to prove no exception
    }
}
