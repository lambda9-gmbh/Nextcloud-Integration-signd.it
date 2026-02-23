<?php

declare(strict_types=1);

namespace OCA\IntegrationSignd\Controller;

use OCA\IntegrationSignd\AppInfo\Application;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IConfig;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class OverviewController extends Controller {
    public function __construct(
        IRequest $request,
        private SignApiService $signApiService,
        private IRootFolder $rootFolder,
        private IUserSession $userSession,
        private IConfig $config,
        private LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * List processes from sign API, scoped to this NC instance.
     *
     * @NoAdminRequired
     */
    public function list(
        string $status = 'ALL',
        int $limit = 25,
        int $offset = 0,
        string $searchQuery = '',
        string $dateFrom = '',
        string $dateTo = '',
        string $sortCriteria = '',
        string $sortOrder = '',
        bool $onlyMine = false,
    ): JSONResponse {
        try {
            $instanceId = $this->config->getSystemValue('instanceid');

            // Build metadataSearch — always scope to this NC instance
            $metadataSearch = [
                'applicationMetaData.ncInstanceId' => $instanceId,
            ];

            // Filter to current user if requested
            if ($onlyMine) {
                $user = $this->userSession->getUser();
                if ($user !== null) {
                    $metadataSearch['applicationMetaData.ncUserId'] = $user->getUID();
                }
            }

            $params = [
                'status' => $status,
                'limit' => $limit,
                'offset' => $offset,
                'metadataSearch' => json_encode($metadataSearch),
            ];

            if ($searchQuery !== '') {
                $params['searchQuery'] = $searchQuery;
                $params['searchMatchType'] = 'LIKE';
            }

            if ($dateFrom !== '') {
                $params['dateFrom'] = $dateFrom;
            }

            if ($dateTo !== '') {
                $params['dateTo'] = $dateTo;
            }

            if ($sortCriteria !== '') {
                $params['sortCriteria'] = $sortCriteria;
            }

            if ($sortOrder !== '') {
                $params['sortOrder'] = $sortOrder;
            }

            $result = $this->signApiService->listProcesses($params);

            // Enrich processes with _ncFileExists flag
            $user = $this->userSession->getUser();
            if ($user !== null && !empty($result['processes'])) {
                $userFolder = $this->rootFolder->getUserFolder($user->getUID());

                // Decode apiClientMetaData (signd returns it as JSON string)
                foreach ($result['processes'] as $i => $proc) {
                    if (isset($proc['apiClientMetaData']) && is_string($proc['apiClientMetaData'])) {
                        $result['processes'][$i]['apiClientMetaData'] = json_decode($proc['apiClientMetaData'], true) ?? [];
                    }
                }

                // Collect all ncFileId values and batch-check existence
                $fileExistsMap = [];
                foreach ($result['processes'] as $proc) {
                    $fileId = $proc['apiClientMetaData']['applicationMetaData']['ncFileId'] ?? null;
                    if ($fileId !== null && !isset($fileExistsMap[$fileId])) {
                        $fileExistsMap[$fileId] = !empty($userFolder->getById((int) $fileId));
                    }
                }

                // Set _ncFileExists on each process
                foreach ($result['processes'] as $i => $proc) {
                    $fileId = $proc['apiClientMetaData']['applicationMetaData']['ncFileId'] ?? null;
                    if ($fileId !== null) {
                        $result['processes'][$i]['apiClientMetaData']['applicationMetaData']['_ncFileExists'] = $fileExistsMap[$fileId];
                    }
                }
            }

            return new JSONResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('Failed to list processes', ['exception' => $e]);
            return SignApiService::apiErrorResponse($e, 'Failed to list processes');
        }
    }

    /**
     * Cancel a signing process.
     *
     * @NoAdminRequired
     */
    public function cancel(string $processId, string $reason = ''): JSONResponse {
        try {
            $this->signApiService->cancelProcess($processId, $reason);
            return new JSONResponse(['status' => 'ok']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel process', [
                'processId' => $processId,
                'exception' => $e,
            ]);
            return SignApiService::apiErrorResponse($e, 'Failed to cancel process');
        }
    }
}
