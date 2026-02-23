<?php

declare(strict_types=1);

namespace OCA\IntegrationSignd\Controller;

use OCA\IntegrationSignd\AppInfo\Application;
use OCA\IntegrationSignd\Db\Process;
use OCA\IntegrationSignd\Db\ProcessMapper;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IConfig;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ProcessController extends Controller {
    public function __construct(
        IRequest $request,
        private SignApiService $signApiService,
        private ProcessMapper $processMapper,
        private IRootFolder $rootFolder,
        private IUserSession $userSession,
        private IConfig $config,
        private LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Get all processes for a file
     *
     * @NoAdminRequired
     */
    public function getByFileId(int $fileId): JSONResponse {
        try {
            $processes = $this->processMapper->findByFileId($fileId);
            $result = [];

            foreach ($processes as $process) {
                $baseData = $this->processToArray($process);

                // Check if finished PDF still exists in NC storage
                if ($process->getFinishedPdfPath()) {
                    try {
                        $file = $this->rootFolder->get($process->getFinishedPdfPath());
                        $baseData['finishedPdfFileId'] = $file->getId();
                    } catch (NotFoundException) {
                        // Don't clear DB — keep path so we detect deletion on every load
                        $baseData['finishedPdfPath'] = null;
                        $baseData['finishedPdfDeleted'] = true;
                    }
                }

                // Try to fetch meta from sign API
                try {
                    $meta = $this->signApiService->getMeta($process->getProcessId());

                    // Draft: wizard started but not completed
                    if (!empty($meta['drafts'])) {
                        $draft = $meta['drafts'][0];
                        $data = $baseData;
                        $data['isDraft'] = true;
                        $data['meta'] = [
                            'draftId' => $draft['draftId'] ?? null,
                            'name' => $draft['name'] ?? null,
                            'created' => $draft['created'] ?? null,
                            'filename' => $draft['filename'] ?? null,
                        ];
                        $result[] = $data;
                    }

                    // All completed/active processes
                    if (!empty($meta['processes'])) {
                        foreach ($meta['processes'] as $processMeta) {
                            // Decode apiClientMetaData (signd returns it as JSON string)
                            if (isset($processMeta['apiClientMetaData']) && is_string($processMeta['apiClientMetaData'])) {
                                $processMeta['apiClientMetaData'] = json_decode($processMeta['apiClientMetaData'], true) ?? [];
                            }
                            $data = $baseData;
                            $data['meta'] = $processMeta;
                            $result[] = $data;
                        }
                    }

                    // No drafts and no processes — include base entry without meta
                    if (empty($meta['drafts']) && empty($meta['processes'])) {
                        $result[] = $baseData;
                    }
                } catch (\Exception $e) {
                    $this->logger->debug('Failed to fetch meta for process', [
                        'processId' => $process->getProcessId(),
                        'exception' => $e,
                    ]);
                    $result[] = $baseData;
                }
            }

            return new JSONResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get processes for file', [
                'fileId' => $fileId,
                'exception' => $e,
            ]);
            return SignApiService::apiErrorResponse($e, 'Failed to load processes');
        }
    }

    /**
     * Start a new signing wizard
     *
     * @NoAdminRequired
     */
    public function startWizard(int $fileId): JSONResponse {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $userId = $user->getUID();

        try {
            // Get the file from NC storage
            $userFolder = $this->rootFolder->getUserFolder($userId);
            $files = $userFolder->getById($fileId);

            if (empty($files)) {
                return new JSONResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
            }

            $file = $files[0];

            if (!($file instanceof \OCP\Files\File)) {
                return new JSONResponse(['error' => 'Not a file'], Http::STATUS_BAD_REQUEST);
            }

            // Read file content and encode as base64
            $content = $file->getContent();
            $base64Content = base64_encode($content);

            // Build the request for sign API
            $instanceId = $this->config->getSystemValue('instanceid');
            $wizardData = [
                'pdfFilename' => $file->getName(),
                'pdfData' => $base64Content,
                'name' => $file->getName(),
                'apiClientMetaData' => json_encode([
                    'applicationName' => 'nextcloud-signd',
                    'applicationMetaData' => [
                        'ncFileId' => (string) $fileId,
                        'ncFilePath' => $file->getPath(),
                        'ncFileName' => $file->getName(),
                        'ncUserId' => $userId,
                        'ncInstanceId' => $instanceId,
                    ],
                ]),
            ];

            // Call sign API
            $result = $this->signApiService->startWizard($wizardData);

            if (!isset($result['processId']) || !isset($result['wizardUrl'])) {
                return new JSONResponse(
                    ['error' => 'Invalid response from sign API'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            // Save to our database
            $process = new Process();
            $process->setFileId($fileId);
            $process->setProcessId($result['processId']);
            $process->setUserId($userId);
            $process->setTargetDir($file->getParent()->getPath());
            $this->processMapper->insert($process);

            return new JSONResponse([
                'wizardUrl' => $result['wizardUrl'],
                'processId' => $result['processId'],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to start wizard', [
                'fileId' => $fileId,
                'exception' => $e,
            ]);
            return SignApiService::apiErrorResponse($e, 'Failed to start signing process');
        }
    }

    /**
     * Refresh a process status
     *
     * @NoAdminRequired
     */
    public function refresh(string $processId): JSONResponse {
        try {
            $process = $this->processMapper->findByProcessId($processId);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Process not found'], Http::STATUS_NOT_FOUND);
        }

        try {
            $meta = $this->signApiService->getMeta($processId);

            $data = $this->processToArray($process);
            if (isset($meta['processes'][0])) {
                $data['meta'] = $meta['processes'][0];
            }

            return new JSONResponse($data);
        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh process', [
                'processId' => $processId,
                'exception' => $e,
            ]);
            return SignApiService::apiErrorResponse($e, 'Failed to refresh process status');
        }
    }

    /**
     * Download signed PDF and save to NC
     *
     * @NoAdminRequired
     */
    public function download(string $processId, string $filename = ''): JSONResponse {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $process = $this->processMapper->findByProcessId($processId);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Process not found'], Http::STATUS_NOT_FOUND);
        }

        if ($process->getFinishedPdfPath()) {
            // Check if the previously downloaded file still exists
            try {
                $this->rootFolder->get($process->getFinishedPdfPath());
                return new JSONResponse([
                    'path' => $process->getFinishedPdfPath(),
                    'message' => 'Already downloaded',
                ]);
            } catch (NotFoundException) {
                // File was deleted, allow re-download
            }
        }

        try {
            // Download from sign API
            $pdfData = $this->signApiService->getFinishedPdf($processId);

            $userId = $user->getUID();
            $targetDirMissing = false;

            // Determine target folder from stored target_dir
            try {
                $targetDir = $process->getTargetDir();
                if ($targetDir) {
                    $parent = $this->rootFolder->get($targetDir);
                } else {
                    $parent = $this->rootFolder->getUserFolder($userId);
                    $targetDirMissing = true;
                }
            } catch (NotFoundException) {
                $parent = $this->rootFolder->getUserFolder($userId);
                $targetDirMissing = true;
                $this->logger->warning('Target directory no longer exists, falling back to user root', [
                    'processId' => $processId,
                    'targetDir' => $process->getTargetDir(),
                ]);
            }

            // Determine filename
            if ($filename !== '') {
                $baseName = pathinfo($filename, PATHINFO_FILENAME);
                $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'pdf';
            } else {
                $baseName = 'signed_' . substr($processId, 0, 8);
                $extension = 'pdf';
            }
            $signedName = $baseName . '_signed.' . $extension;

            // Handle duplicates
            $counter = 1;
            while ($parent->nodeExists($signedName)) {
                $signedName = $baseName . '_signed_' . $counter . '.' . $extension;
                $counter++;
            }

            // Save file
            try {
                $newFile = $parent->newFile($signedName, $pdfData);
            } catch (NotPermittedException $e) {
                return new JSONResponse(
                    ['error' => 'Insufficient permissions or storage full', 'errorCode' => 'STORAGE_ERROR'],
                    Http::STATUS_INSUFFICIENT_STORAGE
                );
            }

            // Update process record
            $process->setFinishedPdfPath($newFile->getPath());
            $this->processMapper->update($process);

            $response = [
                'path' => $newFile->getPath(),
                'name' => $signedName,
                'fileId' => $newFile->getId(),
                'size' => $newFile->getSize(),
                'mtime' => $newFile->getMTime(),
                'owner' => $userId,
            ];
            if ($targetDirMissing) {
                $response['targetDirMissing'] = true;
            }

            return new JSONResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Failed to download signed PDF', [
                'processId' => $processId,
                'exception' => $e,
            ]);
            return SignApiService::apiErrorResponse($e, 'Failed to download signed PDF');
        }
    }

    /**
     * Resume an unfinished wizard (draft)
     *
     * @NoAdminRequired
     */
    public function resumeWizard(string $processId): JSONResponse {
        try {
            $this->processMapper->findByProcessId($processId);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Process not found'], Http::STATUS_NOT_FOUND);
        }

        try {
            $result = $this->signApiService->resumeWizard($processId);
            return new JSONResponse(['wizardUrl' => $result['wizardUrl'] ?? '']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to resume wizard', [
                'processId' => $processId,
                'exception' => $e,
            ]);
            return SignApiService::apiErrorResponse($e, 'Failed to resume wizard');
        }
    }

    /**
     * Cancel an unfinished wizard (draft)
     *
     * @NoAdminRequired
     */
    public function cancelWizard(string $processId): JSONResponse {
        try {
            $process = $this->processMapper->findByProcessId($processId);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Process not found'], Http::STATUS_NOT_FOUND);
        }

        try {
            $this->signApiService->cancelWizard($processId);
            $this->processMapper->delete($process);
            return new JSONResponse(['status' => 'ok']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel wizard', [
                'processId' => $processId,
                'exception' => $e,
            ]);
            return SignApiService::apiErrorResponse($e, 'Failed to cancel wizard');
        }
    }

    private function processToArray(Process $process): array {
        return [
            'id' => $process->getId(),
            'fileId' => $process->getFileId(),
            'processId' => $process->getProcessId(),
            'userId' => $process->getUserId(),
            'targetDir' => $process->getTargetDir(),
            'finishedPdfPath' => $process->getFinishedPdfPath(),
        ];
    }
}
