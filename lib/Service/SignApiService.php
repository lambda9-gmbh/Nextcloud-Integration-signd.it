<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\IntegrationSignd\Service;

use GuzzleHttp\Exception\ClientException;
use OCA\IntegrationSignd\AppInfo\Application;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class SignApiService {
    private const DEFAULT_API_URL = 'https://signd.it';

    public function __construct(
        private IClientService $clientService,
        private IConfig $config,
        private LoggerInterface $logger,
    ) {
    }

    public function getApiUrl(): string {
        // 1. App config (set via occ or API)
        $configUrl = $this->config->getAppValue(Application::APP_ID, 'api_url', '');
        if ($configUrl !== '') {
            return rtrim($configUrl, '/');
        }

        // 2. Environment variable (e.g. via docker-compose)
        $envUrl = getenv('SIGND_BASE_URL');
        if ($envUrl !== false && $envUrl !== '') {
            return rtrim($envUrl, '/');
        }

        // 3. Default
        return self::DEFAULT_API_URL;
    }

    public function getApiKey(): string {
        return $this->config->getAppValue(Application::APP_ID, 'api_key', '');
    }

    public function setApiKey(string $apiKey): void {
        $this->config->setAppValue(Application::APP_ID, 'api_key', $apiKey);
    }

    // ──────────────────────────────────────
    // Auth & Account
    // ──────────────────────────────────────

    public function login(string $email, string $password): array {
        return $this->post('/api/v2/api-login', [
            'email' => $email,
            'password' => $password,
        ], false);
    }

    public function registerAccount(array $data): array {
        return $this->post('/api/register-account', $data, false);
    }

    public function getPrices(): array {
        return $this->post('/api/prices', [], false);
    }

    public function getUserInfo(): array {
        return $this->get('/api/user-info');
    }

    /**
     * Validate an API key by calling /api/user-info with it.
     * Returns the user info on success, throws on failure.
     */
    public function validateApiKey(string $apiKey): array {
        $client = $this->clientService->newClient();
        $url = $this->getApiUrl() . '/api/user-info';

        $response = $client->get($url, [
            'headers' => [
                'X-API-KEY' => $apiKey,
                'Accept' => 'application/json',
            ],
        ]);

        return json_decode($response->getBody(), true) ?? [];
    }

    // ──────────────────────────────────────
    // Processes
    // ──────────────────────────────────────

    public function startWizard(array $data): array {
        return $this->post('/api/start-wizard', $data);
    }

    public function resumeWizard(string $id): array {
        return $this->post('/api/resume-wizard', ['id' => $id]);
    }

    public function cancelWizard(string $id): void {
        $this->post('/api/cancel-wizard', ['id' => $id]);
    }

    public function getMeta(string $id): array {
        return $this->get('/api/get-meta', ['id' => $id]);
    }

    public function listProcesses(array $params = []): array {
        return $this->get('/api/list', $params);
    }

    public function listStatus(array $params = []): array {
        return $this->get('/api/list-status', $params);
    }

    public function getFinishedPdf(string $id): string {
        $client = $this->clientService->newClient();
        $url = $this->getApiUrl() . '/api/finished?' . http_build_query(['id' => $id]);

        $response = $client->get($url, [
            'headers' => [
                'X-API-KEY' => $this->getApiKey(),
            ],
        ]);

        return $response->getBody();
    }

    public function newFinished(string $gt): array {
        return $this->get('/api/new-finished', ['gt' => $gt]);
    }

    public function cancelProcess(string $id, string $reason): void {
        $this->post('/api/cancel-process', [
            'id' => $id,
            'reason' => $reason,
        ]);
    }

    public function resumeProcess(string $id): void {
        $this->post('/api/resume-process', ['id' => $id]);
    }

    public function getSignatureImage(string $signatureKey, int $height = 60): string {
        $client = $this->clientService->newClient();
        $url = $this->getApiUrl() . '/signature-animated/' . $signatureKey . '?h=' . $height;

        $response = $client->get($url);

        return $response->getBody();
    }

    public function findByOriginal(string $pdfBinary): array {
        $client = $this->clientService->newClient();
        $url = $this->getApiUrl() . '/api/find-by-original';

        $response = $client->post($url, [
            'headers' => [
                'X-API-KEY' => $this->getApiKey(),
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $pdfBinary,
        ]);

        return json_decode($response->getBody(), true);
    }

    // ──────────────────────────────────────
    // Internal HTTP helpers
    // ──────────────────────────────────────

    private function get(string $endpoint, array $query = []): array {
        $client = $this->clientService->newClient();
        $url = $this->getApiUrl() . $endpoint;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $options = [
            'headers' => [
                'X-API-KEY' => $this->getApiKey(),
                'Accept' => 'application/json',
            ],
        ];

        try {
            $response = $client->get($url, $options);
            return json_decode($response->getBody(), true) ?? [];
        } catch (\Exception $e) {
            $this->logger->error('signd API GET request failed', [
                'endpoint' => $endpoint,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    private function post(string $endpoint, array $data, bool $withApiKey = true): array {
        $client = $this->clientService->newClient();
        $url = $this->getApiUrl() . $endpoint;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($withApiKey) {
            $headers['X-API-KEY'] = $this->getApiKey();
        }

        $options = [
            'headers' => $headers,
            'body' => json_encode($data),
        ];

        try {
            $response = $client->post($url, $options);
            $body = $response->getBody();
            if ($body === '') {
                return [];
            }
            return json_decode($body, true) ?? [];
        } catch (\Exception $e) {
            $this->logger->error('signd API POST request failed', [
                'endpoint' => $endpoint,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Convert a signd API exception into a JSONResponse with the original error message and status.
     *
     * If the server responded (ClientException with 4xx) → extract error details.
     * Everything else (ConnectException, LocalServerException, timeout, 5xx, …) → SIGND_UNREACHABLE.
     */
    public static function apiErrorResponse(\Exception $e, string $fallbackMessage, int $fallbackStatus = 500): JSONResponse {
        if ($e instanceof ClientException && $e->hasResponse()) {
            $body = (string) $e->getResponse()->getBody();
            $status = $e->getResponse()->getStatusCode();
            $json = json_decode($body, true);
            $message = (is_array($json) && isset($json['error'])) ? $json['error'] : ($body !== '' ? $body : $fallbackMessage);
            $errorCode = ($status === 401 || $status === 403) ? 'SIGND_UNAUTHORIZED' : 'SIGND_API_ERROR';
            return new JSONResponse(['error' => $message, 'errorCode' => $errorCode], $status);
        }

        return new JSONResponse([
            'error' => 'Cannot reach signd.it server',
            'errorCode' => 'SIGND_UNREACHABLE',
        ], Http::STATUS_BAD_GATEWAY);
    }
}
