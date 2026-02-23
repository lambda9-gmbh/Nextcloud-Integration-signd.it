<?php

declare(strict_types=1);

namespace OCA\IntegrationSignd\Tests\Unit\Controller;

use OCA\IntegrationSignd\Controller\OverviewController;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Http;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OverviewControllerTest extends TestCase {
    private SignApiService&MockObject $signApiService;
    private IRootFolder&MockObject $rootFolder;
    private IUserSession&MockObject $userSession;
    private IConfig&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private OverviewController $controller;

    protected function setUp(): void {
        $request = $this->createMock(IRequest::class);
        $this->signApiService = $this->createMock(SignApiService::class);
        $this->rootFolder = $this->createMock(IRootFolder::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->config = $this->createMock(IConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->config->method('getSystemValue')
            ->with('instanceid')
            ->willReturn('oc-test-instance-id');

        $this->controller = new OverviewController(
            $request,
            $this->signApiService,
            $this->rootFolder,
            $this->userSession,
            $this->config,
            $this->logger,
        );
    }

    private function mockUser(string $uid = 'admin'): void {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn($uid);
        $this->userSession->method('getUser')->willReturn($user);

        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('getById')->willReturn([]);
        $this->rootFolder->method('getUserFolder')->with($uid)->willReturn($userFolder);
    }

    // ── list: Instance-Scoping ──

    public function testListScopesToInstanceId(): void {
        $this->mockUser();
        $this->signApiService->expects($this->once())
            ->method('listProcesses')
            ->with($this->callback(function (array $params): bool {
                $meta = json_decode($params['metadataSearch'], true);
                $this->assertSame('oc-test-instance-id', $meta['applicationMetaData.ncInstanceId']);
                return true;
            }))
            ->willReturn(['numHits' => 0, 'processes' => []]);

        $this->controller->list();
    }

    public function testListUsesStableInstanceIdNotUrl(): void {
        $this->mockUser();
        $this->config->expects($this->atLeastOnce())
            ->method('getSystemValue')
            ->with('instanceid');

        $this->signApiService->method('listProcesses')
            ->willReturn(['numHits' => 0, 'processes' => []]);

        $this->controller->list();
    }

    // ── list: User filtering ──

    public function testListFiltersToCurrentUserWhenOnlyMine(): void {
        $this->mockUser('testuser');

        $this->signApiService->expects($this->once())
            ->method('listProcesses')
            ->with($this->callback(function (array $params): bool {
                $meta = json_decode($params['metadataSearch'], true);
                $this->assertSame('testuser', $meta['applicationMetaData.ncUserId']);
                return true;
            }))
            ->willReturn(['numHits' => 0, 'processes' => []]);

        $this->controller->list(onlyMine: true);
    }

    public function testListDoesNotFilterUserWhenOnlyMineFalse(): void {
        $this->mockUser('testuser');

        $this->signApiService->expects($this->once())
            ->method('listProcesses')
            ->with($this->callback(function (array $params): bool {
                $meta = json_decode($params['metadataSearch'], true);
                $this->assertArrayNotHasKey('applicationMetaData.ncUserId', $meta);
                return true;
            }))
            ->willReturn(['numHits' => 0, 'processes' => []]);

        $this->controller->list(onlyMine: false);
    }

    // ── list: Parameter forwarding ──

    public function testListPassesStatusFilter(): void {
        $this->mockUser();
        $this->signApiService->expects($this->once())
            ->method('listProcesses')
            ->with($this->callback(fn(array $p) => $p['status'] === 'RUNNING'))
            ->willReturn(['numHits' => 0, 'processes' => []]);

        $this->controller->list(status: 'RUNNING');
    }

    public function testListPassesPaginationParams(): void {
        $this->mockUser();
        $this->signApiService->expects($this->once())
            ->method('listProcesses')
            ->with($this->callback(fn(array $p) => $p['limit'] === 10 && $p['offset'] === 20))
            ->willReturn(['numHits' => 0, 'processes' => []]);

        $this->controller->list(limit: 10, offset: 20);
    }

    public function testListPassesSearchQueryWithMatchType(): void {
        $this->mockUser();
        $this->signApiService->expects($this->once())
            ->method('listProcesses')
            ->with($this->callback(function (array $p): bool {
                $this->assertSame('contract', $p['searchQuery']);
                $this->assertSame('LIKE', $p['searchMatchType']);
                return true;
            }))
            ->willReturn(['numHits' => 0, 'processes' => []]);

        $this->controller->list(searchQuery: 'contract');
    }

    public function testListPassesDateRange(): void {
        $this->mockUser();
        $this->signApiService->expects($this->once())
            ->method('listProcesses')
            ->with($this->callback(fn(array $p) =>
                $p['dateFrom'] === '2025-01-01' && $p['dateTo'] === '2025-12-31'
            ))
            ->willReturn(['numHits' => 0, 'processes' => []]);

        $this->controller->list(dateFrom: '2025-01-01', dateTo: '2025-12-31');
    }

    public function testListPassesSortParams(): void {
        $this->mockUser();
        $this->signApiService->expects($this->once())
            ->method('listProcesses')
            ->with($this->callback(fn(array $p) =>
                $p['sortCriteria'] === 'CREATED' && $p['sortOrder'] === 'ASC'
            ))
            ->willReturn(['numHits' => 0, 'processes' => []]);

        $this->controller->list(sortCriteria: 'CREATED', sortOrder: 'ASC');
    }

    public function testListOmitsEmptyOptionalParams(): void {
        $this->mockUser();
        $this->signApiService->expects($this->once())
            ->method('listProcesses')
            ->with($this->callback(function (array $p): bool {
                $this->assertArrayNotHasKey('searchQuery', $p);
                $this->assertArrayNotHasKey('dateFrom', $p);
                $this->assertArrayNotHasKey('dateTo', $p);
                $this->assertArrayNotHasKey('sortCriteria', $p);
                $this->assertArrayNotHasKey('sortOrder', $p);
                return true;
            }))
            ->willReturn(['numHits' => 0, 'processes' => []]);

        $this->controller->list();
    }

    // ── list: File existence enrichment ──

    public function testListEnrichesWithFileExistenceFlag(): void {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('admin');
        $this->userSession->method('getUser')->willReturn($user);

        $mockFile = $this->createMock(\OCP\Files\File::class);
        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('getById')
            ->willReturnCallback(fn(int $id) => $id === 42 ? [$mockFile] : []);
        $this->rootFolder->method('getUserFolder')->willReturn($userFolder);

        $this->signApiService->method('listProcesses')->willReturn([
            'numHits' => 2,
            'processes' => [
                [
                    'processId' => 'p1',
                    'apiClientMetaData' => json_encode(['applicationMetaData' => ['ncFileId' => '42']]),
                ],
                [
                    'processId' => 'p2',
                    'apiClientMetaData' => json_encode(['applicationMetaData' => ['ncFileId' => '999']]),
                ],
            ],
        ]);

        $response = $this->controller->list();
        $data = $response->getData();

        $this->assertTrue($data['processes'][0]['apiClientMetaData']['applicationMetaData']['_ncFileExists']);
        $this->assertFalse($data['processes'][1]['apiClientMetaData']['applicationMetaData']['_ncFileExists']);
    }

    public function testListHandlesProcessWithoutMetadata(): void {
        $this->mockUser();
        $this->signApiService->method('listProcesses')->willReturn([
            'numHits' => 1,
            'processes' => [
                ['processId' => 'p1'], // no apiClientMetaData at all
            ],
        ]);

        $response = $this->controller->list();
        $data = $response->getData();

        $this->assertSame(200, $response->getStatus());
        $this->assertCount(1, $data['processes']);
    }

    // ── list: Error handling ──

    public function testListReturnsErrorOnApiFailure(): void {
        $this->mockUser();
        $this->signApiService->method('listProcesses')
            ->willThrowException(new \RuntimeException('API down'));

        $response = $this->controller->list();

        $this->assertGreaterThanOrEqual(400, $response->getStatus());
    }

    // ── cancel ──

    public function testCancelCallsApiWithProcessIdAndReason(): void {
        $this->signApiService->expects($this->once())
            ->method('cancelProcess')
            ->with('proc-123', 'Not needed anymore');

        $response = $this->controller->cancel('proc-123', 'Not needed anymore');
        $data = $response->getData();

        $this->assertSame('ok', $data['status']);
    }

    public function testCancelReturnsOkOnSuccess(): void {
        $response = $this->controller->cancel('proc-123');

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('ok', $response->getData()['status']);
    }

    public function testCancelPassesEmptyReasonWhenOmitted(): void {
        $this->signApiService->expects($this->once())
            ->method('cancelProcess')
            ->with('proc-123', '');

        $this->controller->cancel('proc-123');
    }

    public function testCancelReturnsErrorOnApiFailure(): void {
        $this->signApiService->method('cancelProcess')
            ->willThrowException(new \RuntimeException('Not found'));

        $response = $this->controller->cancel('proc-123');

        $this->assertGreaterThanOrEqual(400, $response->getStatus());
    }
}
