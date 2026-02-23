<?php

declare(strict_types=1);

namespace OCA\IntegrationSignd\Tests\Unit\Controller;

use OCA\IntegrationSignd\Controller\ProcessController;
use OCA\IntegrationSignd\Db\Process;
use OCA\IntegrationSignd\Db\ProcessMapper;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProcessControllerTest extends TestCase {
    private SignApiService&MockObject $signApiService;
    private ProcessMapper&MockObject $processMapper;
    private IRootFolder&MockObject $rootFolder;
    private IUserSession&MockObject $userSession;
    private IConfig&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private ProcessController $controller;

    protected function setUp(): void {
        $request = $this->createMock(IRequest::class);
        $this->signApiService = $this->createMock(SignApiService::class);
        $this->processMapper = $this->createMock(ProcessMapper::class);
        $this->rootFolder = $this->createMock(IRootFolder::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->config = $this->createMock(IConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new ProcessController(
            $request,
            $this->signApiService,
            $this->processMapper,
            $this->rootFolder,
            $this->userSession,
            $this->config,
            $this->logger,
        );
    }

    private function mockUser(string $uid = 'admin'): IUser&MockObject {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn($uid);
        $this->userSession->method('getUser')->willReturn($user);
        return $user;
    }

    private function createProcess(string $processId = 'proc-123', int $fileId = 42): Process {
        $process = new Process();
        $process->setProcessId($processId);
        $process->setFileId($fileId);
        $process->setUserId('admin');
        return $process;
    }

    // ── getByFileId ──

    public function testGetByFileIdReturnsEmptyForNoProcesses(): void {
        $this->processMapper->method('findByFileId')->willReturn([]);

        $response = $this->controller->getByFileId(42);

        $this->assertSame(200, $response->getStatus());
        $this->assertSame([], $response->getData());
    }

    public function testGetByFileIdEnrichesWithMeta(): void {
        $process = $this->createProcess();
        $this->processMapper->method('findByFileId')->willReturn([$process]);

        $this->signApiService->method('getMeta')->willReturn([
            'drafts' => [],
            'processes' => [
                ['name' => 'Contract', 'signersCompleted' => [], 'signersPending' => []],
            ],
        ]);

        $response = $this->controller->getByFileId(42);
        $data = $response->getData();

        $this->assertCount(1, $data);
        $this->assertSame('Contract', $data[0]['meta']['name']);
    }

    public function testGetByFileIdHandlesMetaFailureGracefully(): void {
        $process = $this->createProcess();
        $this->processMapper->method('findByFileId')->willReturn([$process]);

        $this->signApiService->method('getMeta')
            ->willThrowException(new \RuntimeException('API down'));

        $response = $this->controller->getByFileId(42);
        $data = $response->getData();

        $this->assertSame(200, $response->getStatus());
        $this->assertCount(1, $data);
        $this->assertArrayNotHasKey('meta', $data[0]);
    }

    public function testGetByFileIdReturnsDraftsAndProcesses(): void {
        $process = $this->createProcess();
        $this->processMapper->method('findByFileId')->willReturn([$process]);

        $this->signApiService->method('getMeta')->willReturn([
            'drafts' => [['draftId' => 'd1', 'name' => 'Draft', 'created' => '2025-01-01', 'filename' => 'test.pdf']],
            'processes' => [['name' => 'Completed']],
        ]);

        $response = $this->controller->getByFileId(42);
        $data = $response->getData();

        $this->assertCount(2, $data);
        $this->assertTrue($data[0]['isDraft']);
        $this->assertSame('Draft', $data[0]['meta']['name']);
        $this->assertSame('Completed', $data[1]['meta']['name']);
    }

    public function testGetByFileIdReturnsBaseDataWhenNoMetaContent(): void {
        $process = $this->createProcess();
        $this->processMapper->method('findByFileId')->willReturn([$process]);

        $this->signApiService->method('getMeta')->willReturn([
            'drafts' => [],
            'processes' => [],
        ]);

        $response = $this->controller->getByFileId(42);
        $data = $response->getData();

        $this->assertCount(1, $data);
        $this->assertSame('proc-123', $data[0]['processId']);
        $this->assertArrayNotHasKey('meta', $data[0]);
    }

    public function testGetByFileIdReturnsFinishedPdfFileIdWhenFileExists(): void {
        $process = $this->createProcess();
        $process->setFinishedPdfPath('/admin/files/Documents/contract_signed.pdf');
        $this->processMapper->method('findByFileId')->willReturn([$process]);

        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(99);
        $this->rootFolder->method('get')
            ->with('/admin/files/Documents/contract_signed.pdf')
            ->willReturn($file);

        $this->signApiService->method('getMeta')->willReturn([
            'drafts' => [],
            'processes' => [['name' => 'Contract', 'signersCompleted' => [], 'signersPending' => []]],
        ]);

        $response = $this->controller->getByFileId(42);
        $data = $response->getData();

        $this->assertCount(1, $data);
        $this->assertSame(99, $data[0]['finishedPdfFileId']);
        $this->assertSame('/admin/files/Documents/contract_signed.pdf', $data[0]['finishedPdfPath']);
    }

    public function testGetByFileIdHandlesDeletedFinishedPdf(): void {
        $process = $this->createProcess();
        $process->setFinishedPdfPath('/admin/files/Documents/contract_signed.pdf');
        $this->processMapper->method('findByFileId')->willReturn([$process]);

        $this->rootFolder->method('get')
            ->with('/admin/files/Documents/contract_signed.pdf')
            ->willThrowException(new NotFoundException());

        // DB should NOT be updated — path is kept so deletion is detected on every load
        $this->processMapper->expects($this->never())->method('update');

        $this->signApiService->method('getMeta')->willReturn([
            'drafts' => [],
            'processes' => [['name' => 'Contract', 'signersCompleted' => [], 'signersPending' => []]],
        ]);

        $response = $this->controller->getByFileId(42);
        $data = $response->getData();

        $this->assertCount(1, $data);
        $this->assertNull($data[0]['finishedPdfPath']);
        $this->assertTrue($data[0]['finishedPdfDeleted']);
        $this->assertArrayNotHasKey('finishedPdfFileId', $data[0]);
    }

    // ── startWizard ──

    public function testStartWizardReturns401WhenNotAuthenticated(): void {
        $this->userSession->method('getUser')->willReturn(null);

        $response = $this->controller->startWizard(42);

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }

    public function testStartWizardReturns404WhenFileNotFound(): void {
        $this->mockUser();
        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('getById')->willReturn([]);
        $this->rootFolder->method('getUserFolder')->willReturn($userFolder);

        $response = $this->controller->startWizard(999);

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }

    public function testStartWizardReturns400ForDirectory(): void {
        $this->mockUser();
        $folder = $this->createMock(Folder::class);
        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('getById')->willReturn([$folder]);
        $this->rootFolder->method('getUserFolder')->willReturn($userFolder);

        $response = $this->controller->startWizard(42);

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    public function testStartWizardSendsMetadataWithInstanceId(): void {
        $this->mockUser('testuser');
        $this->config->method('getSystemValue')
            ->with('instanceid')
            ->willReturn('oc-stable-id');

        $parentFolder = $this->createMock(Folder::class);
        $parentFolder->method('getPath')->willReturn('/testuser/files/Documents');

        $file = $this->createMock(File::class);
        $file->method('getName')->willReturn('contract.pdf');
        $file->method('getContent')->willReturn('%PDF-1.4 content');
        $file->method('getPath')->willReturn('/testuser/files/Documents/contract.pdf');
        $file->method('getParent')->willReturn($parentFolder);

        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('getById')->willReturn([$file]);
        $this->rootFolder->method('getUserFolder')->willReturn($userFolder);

        $this->signApiService->expects($this->once())
            ->method('startWizard')
            ->with($this->callback(function (array $data): bool {
                $this->assertIsString($data['apiClientMetaData']);
                $decoded = json_decode($data['apiClientMetaData'], true);
                $meta = $decoded['applicationMetaData'];
                $this->assertSame('42', $meta['ncFileId']);
                $this->assertSame('testuser', $meta['ncUserId']);
                $this->assertSame('oc-stable-id', $meta['ncInstanceId']);
                $this->assertSame('contract.pdf', $meta['ncFileName']);
                $this->assertSame('contract.pdf', $data['pdfFilename']);
                // Verify base64 encoded content
                $this->assertSame(base64_encode('%PDF-1.4 content'), $data['pdfData']);
                return true;
            }))
            ->willReturn(['processId' => 'proc-new', 'wizardUrl' => 'https://signd.it/wizard/123']);

        $this->processMapper->expects($this->once())->method('insert');

        $response = $this->controller->startWizard(42);
        $data = $response->getData();

        $this->assertSame('https://signd.it/wizard/123', $data['wizardUrl']);
        $this->assertSame('proc-new', $data['processId']);
    }

    public function testStartWizardStoresTargetDir(): void {
        $this->mockUser();

        $parentFolder = $this->createMock(Folder::class);
        $parentFolder->method('getPath')->willReturn('/admin/files/Documents');

        $file = $this->createMock(File::class);
        $file->method('getName')->willReturn('test.pdf');
        $file->method('getContent')->willReturn('content');
        $file->method('getPath')->willReturn('/admin/files/Documents/test.pdf');
        $file->method('getParent')->willReturn($parentFolder);

        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('getById')->willReturn([$file]);
        $this->rootFolder->method('getUserFolder')->willReturn($userFolder);
        $this->config->method('getSystemValue')->willReturn('inst-id');

        $this->signApiService->method('startWizard')
            ->willReturn(['processId' => 'p1', 'wizardUrl' => 'https://signd.it/w']);

        $this->processMapper->expects($this->once())
            ->method('insert')
            ->with($this->callback(function (Process $p): bool {
                $this->assertSame('/admin/files/Documents', $p->getTargetDir());
                return true;
            }));

        $this->controller->startWizard(42);
    }

    public function testStartWizardReturns500WhenMissingProcessId(): void {
        $this->mockUser();

        $file = $this->createMock(File::class);
        $file->method('getName')->willReturn('test.pdf');
        $file->method('getContent')->willReturn('content');
        $file->method('getPath')->willReturn('/admin/files/test.pdf');
        $file->method('getParent')->willReturn($this->createMock(Folder::class));

        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('getById')->willReturn([$file]);
        $this->rootFolder->method('getUserFolder')->willReturn($userFolder);
        $this->config->method('getSystemValue')->willReturn('inst-id');

        $this->signApiService->method('startWizard')
            ->willReturn(['wizardUrl' => 'https://signd.it/w']); // no processId

        $response = $this->controller->startWizard(42);

        $this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
    }

    // ── download ──

    public function testDownloadReturns401WhenNotAuthenticated(): void {
        $this->userSession->method('getUser')->willReturn(null);

        $response = $this->controller->download('proc-123');

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }

    public function testDownloadReturns404WhenProcessNotFound(): void {
        $this->mockUser();
        $this->processMapper->method('findByProcessId')
            ->willThrowException(new DoesNotExistException(''));

        $response = $this->controller->download('proc-123');

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }

    public function testDownloadReturnsCachedPathWhenAlreadyDownloaded(): void {
        $this->mockUser();
        $process = $this->createProcess();
        $process->setFinishedPdfPath('/admin/files/contract_signed.pdf');
        $this->processMapper->method('findByProcessId')->willReturn($process);

        // File still exists in NC
        $existingFile = $this->createMock(File::class);
        $this->rootFolder->method('get')
            ->with('/admin/files/contract_signed.pdf')
            ->willReturn($existingFile);

        // Should NOT call getFinishedPdf
        $this->signApiService->expects($this->never())->method('getFinishedPdf');

        $response = $this->controller->download('proc-123');
        $data = $response->getData();

        $this->assertSame('/admin/files/contract_signed.pdf', $data['path']);
    }

    public function testDownloadAllowsRedownloadWhenFileDeleted(): void {
        $this->mockUser();
        $process = $this->createProcess();
        $process->setFinishedPdfPath('/admin/files/contract_signed.pdf');
        $process->setTargetDir('/admin/files/Documents');
        $this->processMapper->method('findByProcessId')->willReturn($process);

        // Previously downloaded file was deleted
        $this->rootFolder->method('get')
            ->willReturnCallback(function (string $path) {
                if ($path === '/admin/files/contract_signed.pdf') {
                    throw new NotFoundException();
                }
                // Return target folder for the re-download path
                $folder = $this->createMock(Folder::class);
                $newFile = $this->createMock(File::class);
                $newFile->method('getPath')->willReturn('/admin/files/Documents/contract_signed.pdf');
                $newFile->method('getId')->willReturn(101);
                $newFile->method('getSize')->willReturn(5000);
                $newFile->method('getMTime')->willReturn(1700000000);
                $folder->method('nodeExists')->willReturn(false);
                $folder->method('newFile')->willReturn($newFile);
                return $folder;
            });

        $this->signApiService->expects($this->once())
            ->method('getFinishedPdf')
            ->willReturn('%PDF-re-downloaded');

        $this->processMapper->expects($this->once())->method('update');

        $response = $this->controller->download('proc-123', 'contract.pdf');
        $data = $response->getData();

        $this->assertSame(200, $response->getStatus());
        $this->assertSame(101, $data['fileId']);
    }

    public function testDownloadSavesToTargetDir(): void {
        $this->mockUser();
        $process = $this->createProcess();
        $process->setTargetDir('/admin/files/Documents');
        $this->processMapper->method('findByProcessId')->willReturn($process);

        $this->signApiService->method('getFinishedPdf')->willReturn('%PDF-signed-content');

        $newFile = $this->createMock(File::class);
        $newFile->method('getPath')->willReturn('/admin/files/Documents/contract_signed.pdf');
        $newFile->method('getId')->willReturn(99);
        $newFile->method('getSize')->willReturn(45678);
        $newFile->method('getMTime')->willReturn(1700000000);

        $targetFolder = $this->createMock(Folder::class);
        $targetFolder->method('nodeExists')->willReturn(false);
        $targetFolder->method('newFile')->willReturn($newFile);
        $this->rootFolder->method('get')
            ->with('/admin/files/Documents')
            ->willReturn($targetFolder);

        $this->processMapper->expects($this->once())->method('update');

        $response = $this->controller->download('proc-123', 'contract.pdf');
        $data = $response->getData();

        $this->assertSame('/admin/files/Documents/contract_signed.pdf', $data['path']);
        $this->assertSame(99, $data['fileId']);
        $this->assertSame(45678, $data['size']);
        $this->assertSame(1700000000, $data['mtime']);
        $this->assertSame('admin', $data['owner']);
        $this->assertArrayNotHasKey('targetDirMissing', $data);
    }

    public function testDownloadFallsBackToUserRootWhenTargetDirDeleted(): void {
        $this->mockUser();
        $process = $this->createProcess();
        $process->setTargetDir('/admin/files/DeletedFolder');
        $this->processMapper->method('findByProcessId')->willReturn($process);

        $this->signApiService->method('getFinishedPdf')->willReturn('%PDF-content');

        $this->rootFolder->method('get')
            ->willThrowException(new NotFoundException());

        $newFile = $this->createMock(File::class);
        $newFile->method('getPath')->willReturn('/admin/files/contract_signed.pdf');
        $newFile->method('getId')->willReturn(50);
        $newFile->method('getSize')->willReturn(1000);
        $newFile->method('getMTime')->willReturn(1700000000);

        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('nodeExists')->willReturn(false);
        $userFolder->method('newFile')->willReturn($newFile);
        $this->rootFolder->method('getUserFolder')->willReturn($userFolder);

        $this->logger->expects($this->atLeastOnce())->method('warning');

        $response = $this->controller->download('proc-123', 'contract.pdf');
        $data = $response->getData();

        $this->assertTrue($data['targetDirMissing']);
    }

    public function testDownloadHandlesDuplicateFilenames(): void {
        $this->mockUser();
        $process = $this->createProcess();
        $process->setTargetDir('/admin/files/Documents');
        $this->processMapper->method('findByProcessId')->willReturn($process);

        $this->signApiService->method('getFinishedPdf')->willReturn('%PDF-content');

        $newFile = $this->createMock(File::class);
        $newFile->method('getPath')->willReturn('/admin/files/Documents/contract_signed_1.pdf');
        $newFile->method('getId')->willReturn(77);
        $newFile->method('getSize')->willReturn(2000);
        $newFile->method('getMTime')->willReturn(1700000000);

        $targetFolder = $this->createMock(Folder::class);
        // First name exists, second doesn't
        $targetFolder->method('nodeExists')
            ->willReturnCallback(fn(string $name) => $name === 'contract_signed.pdf');
        $targetFolder->expects($this->once())
            ->method('newFile')
            ->with('contract_signed_1.pdf', '%PDF-content')
            ->willReturn($newFile);
        $this->rootFolder->method('get')->willReturn($targetFolder);

        $response = $this->controller->download('proc-123', 'contract.pdf');
        $data = $response->getData();

        $this->assertSame('contract_signed_1.pdf', $data['name']);
    }

    public function testDownloadReturns507OnStorageFull(): void {
        $this->mockUser();
        $process = $this->createProcess();
        $process->setTargetDir('/admin/files/Documents');
        $this->processMapper->method('findByProcessId')->willReturn($process);

        $this->signApiService->method('getFinishedPdf')->willReturn('%PDF-content');

        $targetFolder = $this->createMock(Folder::class);
        $targetFolder->method('nodeExists')->willReturn(false);
        $targetFolder->method('newFile')
            ->willThrowException(new NotPermittedException());
        $this->rootFolder->method('get')->willReturn($targetFolder);

        $response = $this->controller->download('proc-123', 'contract.pdf');

        $this->assertSame(Http::STATUS_INSUFFICIENT_STORAGE, $response->getStatus());
        $this->assertSame('STORAGE_ERROR', $response->getData()['errorCode']);
    }

    public function testDownloadUpdatesDbWithPath(): void {
        $this->mockUser();
        $process = $this->createProcess();
        $process->setTargetDir('/admin/files/Documents');
        $this->processMapper->method('findByProcessId')->willReturn($process);

        $this->signApiService->method('getFinishedPdf')->willReturn('%PDF-content');

        $newFile = $this->createMock(File::class);
        $newFile->method('getPath')->willReturn('/admin/files/Documents/signed.pdf');
        $newFile->method('getId')->willReturn(88);
        $newFile->method('getSize')->willReturn(3000);
        $newFile->method('getMTime')->willReturn(1700000000);

        $targetFolder = $this->createMock(Folder::class);
        $targetFolder->method('nodeExists')->willReturn(false);
        $targetFolder->method('newFile')->willReturn($newFile);
        $this->rootFolder->method('get')->willReturn($targetFolder);

        $this->processMapper->expects($this->once())
            ->method('update')
            ->with($this->callback(function (Process $p): bool {
                $this->assertSame('/admin/files/Documents/signed.pdf', $p->getFinishedPdfPath());
                return true;
            }));

        $this->controller->download('proc-123', 'doc.pdf');
    }

    // ── refresh ──

    public function testRefreshReturns404WhenNotFound(): void {
        $this->processMapper->method('findByProcessId')
            ->willThrowException(new DoesNotExistException(''));

        $response = $this->controller->refresh('proc-123');

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }

    public function testRefreshReturnsEnrichedProcessData(): void {
        $process = $this->createProcess();
        $this->processMapper->method('findByProcessId')->willReturn($process);

        $meta = ['name' => 'Refreshed', 'signersCompleted' => []];
        $this->signApiService->method('getMeta')
            ->willReturn(['processes' => [$meta]]);

        $response = $this->controller->refresh('proc-123');
        $data = $response->getData();

        $this->assertSame('Refreshed', $data['meta']['name']);
        $this->assertSame('proc-123', $data['processId']);
    }

    // ── resumeWizard ──

    public function testResumeWizardReturns404WhenNotFound(): void {
        $this->processMapper->method('findByProcessId')
            ->willThrowException(new DoesNotExistException(''));

        $response = $this->controller->resumeWizard('proc-123');

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }

    public function testResumeWizardReturnsWizardUrl(): void {
        $this->processMapper->method('findByProcessId')->willReturn($this->createProcess());
        $this->signApiService->method('resumeWizard')
            ->willReturn(['wizardUrl' => 'https://signd.it/wizard/resume']);

        $response = $this->controller->resumeWizard('proc-123');

        $this->assertSame('https://signd.it/wizard/resume', $response->getData()['wizardUrl']);
    }

    public function testResumeWizardReturnsEmptyUrlOnMissingResponse(): void {
        $this->processMapper->method('findByProcessId')->willReturn($this->createProcess());
        $this->signApiService->method('resumeWizard')->willReturn([]);

        $response = $this->controller->resumeWizard('proc-123');

        $this->assertSame('', $response->getData()['wizardUrl']);
    }

    // ── cancelWizard ──

    public function testCancelWizardReturns404WhenNotFound(): void {
        $this->processMapper->method('findByProcessId')
            ->willThrowException(new DoesNotExistException(''));

        $response = $this->controller->cancelWizard('proc-123');

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }

    public function testCancelWizardDeletesDbEntry(): void {
        $process = $this->createProcess();
        $this->processMapper->method('findByProcessId')->willReturn($process);

        $this->signApiService->expects($this->once())
            ->method('cancelWizard')
            ->with('proc-123');
        $this->processMapper->expects($this->once())
            ->method('delete')
            ->with($process);

        $response = $this->controller->cancelWizard('proc-123');

        $this->assertSame('ok', $response->getData()['status']);
    }

    public function testCancelWizardCallsApiBeforeDbDelete(): void {
        $process = $this->createProcess();
        $this->processMapper->method('findByProcessId')->willReturn($process);

        $callOrder = [];
        $this->signApiService->expects($this->once())
            ->method('cancelWizard')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'api';
            });
        $this->processMapper->expects($this->once())
            ->method('delete')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'db';
                return $this->createProcess();
            });

        $this->controller->cancelWizard('proc-123');

        $this->assertSame(['api', 'db'], $callOrder);
    }
}
