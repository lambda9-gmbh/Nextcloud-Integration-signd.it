<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\IntegrationSignd\Tests\Unit\BackgroundJob;

use OCA\IntegrationSignd\BackgroundJob\CleanupOrphanedProcessesJob;
use OCA\IntegrationSignd\Db\Process;
use OCA\IntegrationSignd\Db\ProcessMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CleanupOrphanedProcessesJobTest extends TestCase {
    private ProcessMapper&MockObject $processMapper;
    private IRootFolder&MockObject $rootFolder;
    private LoggerInterface&MockObject $logger;
    private CleanupOrphanedProcessesJob $job;

    protected function setUp(): void {
        $time = $this->createMock(ITimeFactory::class);
        $this->processMapper = $this->createMock(ProcessMapper::class);
        $this->rootFolder = $this->createMock(IRootFolder::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->job = new CleanupOrphanedProcessesJob(
            $time,
            $this->processMapper,
            $this->rootFolder,
            $this->logger,
        );
    }

    private function runJob(): void {
        $method = new \ReflectionMethod($this->job, 'run');
        $method->invoke($this->job, null);
    }

    private function createProcess(string $processId, int $fileId): Process {
        $process = new Process();
        $process->setProcessId($processId);
        $process->setFileId($fileId);
        $process->setUserId('admin');
        return $process;
    }

    public function testDeletesEntriesWhenFileNoLongerExists(): void {
        $process = $this->createProcess('proc-1', 42);
        $this->processMapper->method('findAll')->willReturn([$process]);
        $this->rootFolder->method('getById')->with(42)->willReturn([]);

        $this->processMapper->expects($this->once())
            ->method('delete')
            ->with($process);

        $this->runJob();
    }

    public function testKeepsEntriesWhenFileStillExists(): void {
        $process = $this->createProcess('proc-1', 42);
        $this->processMapper->method('findAll')->willReturn([$process]);

        $node = $this->createMock(Node::class);
        $this->rootFolder->method('getById')->with(42)->willReturn([$node]);

        $this->processMapper->expects($this->never())->method('delete');

        $this->runJob();
    }

    public function testMixedScenarioDeletesOnlyOrphans(): void {
        $existing = $this->createProcess('proc-1', 10);
        $orphan = $this->createProcess('proc-2', 20);

        $this->processMapper->method('findAll')->willReturn([$existing, $orphan]);

        $node = $this->createMock(Node::class);
        $this->rootFolder->method('getById')
            ->willReturnCallback(fn(int $id) => $id === 10 ? [$node] : []);

        $this->processMapper->expects($this->once())
            ->method('delete')
            ->with($orphan);

        $this->runJob();
    }

    public function testDeletesAllProcessesForSameOrphanedFileId(): void {
        $proc1 = $this->createProcess('proc-1', 42);
        $proc2 = $this->createProcess('proc-2', 42);

        $this->processMapper->method('findAll')->willReturn([$proc1, $proc2]);
        $this->rootFolder->method('getById')->with(42)->willReturn([]);

        $this->processMapper->expects($this->exactly(2))
            ->method('delete');

        $this->runJob();
    }

    public function testEmptyDatabaseDoesNothing(): void {
        $this->processMapper->method('findAll')->willReturn([]);

        $this->rootFolder->expects($this->never())->method('getById');
        $this->processMapper->expects($this->never())->method('delete');

        $this->runJob();
    }

    public function testExceptionOnGetByIdIsHandledGracefully(): void {
        $process = $this->createProcess('proc-1', 42);
        $this->processMapper->method('findAll')->willReturn([$process]);

        $this->rootFolder->method('getById')
            ->willThrowException(new \RuntimeException('Storage error'));

        $this->logger->expects($this->atLeastOnce())->method('warning');

        // On exception, file is assumed to exist → no deletion
        $this->processMapper->expects($this->never())->method('delete');

        $this->runJob();
    }

    public function testLogsCountWhenEntriesDeleted(): void {
        $process = $this->createProcess('proc-1', 42);
        $this->processMapper->method('findAll')->willReturn([$process]);
        $this->rootFolder->method('getById')->willReturn([]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('deleted'),
                $this->callback(function (array $context): bool {
                    $this->assertSame(1, $context['total']);
                    $this->assertSame(1, $context['deleted']);
                    return true;
                }),
            );

        $this->runJob();
    }

    public function testDoesNotLogWhenNothingDeleted(): void {
        $process = $this->createProcess('proc-1', 42);
        $this->processMapper->method('findAll')->willReturn([$process]);

        $node = $this->createMock(Node::class);
        $this->rootFolder->method('getById')->willReturn([$node]);

        $this->logger->expects($this->never())->method('info');

        $this->runJob();
    }

    public function testChecksEachFileIdOnlyOnce(): void {
        $proc1 = $this->createProcess('proc-1', 42);
        $proc2 = $this->createProcess('proc-2', 42);
        $proc3 = $this->createProcess('proc-3', 99);

        $this->processMapper->method('findAll')->willReturn([$proc1, $proc2, $proc3]);

        $node = $this->createMock(Node::class);
        // getById should be called exactly twice (once for 42, once for 99)
        $this->rootFolder->expects($this->exactly(2))
            ->method('getById')
            ->willReturnCallback(fn(int $id) => $id === 42 ? [$node] : []);

        $this->runJob();
    }
}
