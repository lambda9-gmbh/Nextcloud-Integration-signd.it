<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\IntegrationSignd\BackgroundJob;

use OCA\IntegrationSignd\Db\ProcessMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\IRootFolder;
use Psr\Log\LoggerInterface;

class CleanupOrphanedProcessesJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private ProcessMapper $processMapper,
        private IRootFolder $rootFolder,
        private LoggerInterface $logger,
    ) {
        parent::__construct($time);
        $this->setInterval(86400);
        $this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
    }

    protected function run(mixed $argument): void {
        $processes = $this->processMapper->findAll();
        if (empty($processes)) {
            return;
        }

        $fileIdExists = [];
        foreach ($processes as $process) {
            $fileId = $process->getFileId();
            if (!isset($fileIdExists[$fileId])) {
                try {
                    $nodes = $this->rootFolder->getById($fileId);
                    $fileIdExists[$fileId] = !empty($nodes);
                } catch (\Exception $e) {
                    $this->logger->warning('CleanupOrphanedProcesses: Error checking file {fileId}: {error}', [
                        'fileId' => $fileId,
                        'error' => $e->getMessage(),
                    ]);
                    $fileIdExists[$fileId] = true;
                }
            }
        }

        $deleted = 0;
        foreach ($processes as $process) {
            if (!$fileIdExists[$process->getFileId()]) {
                $this->processMapper->delete($process);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->logger->info('CleanupOrphanedProcesses: Checked {total} processes, deleted {deleted} orphaned entries', [
                'total' => count($processes),
                'deleted' => $deleted,
            ]);
        }
    }
}
