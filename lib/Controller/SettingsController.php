<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\IntegrationSignd\Controller;

use OCA\IntegrationSignd\AppInfo\Application;
use OCA\IntegrationSignd\Service\SignApiService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class SettingsController extends Controller {
    public function __construct(
        IRequest $request,
        private SignApiService $signApiService,
        private LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Get current configuration
     */
    public function getConfig(): JSONResponse {
        $apiKey = $this->signApiService->getApiKey();
        $apiKeySet = $apiKey !== '';

        $data = [
            'apiKeySet' => $apiKeySet,
            'userInfo' => null,
        ];

        if ($apiKeySet) {
            try {
                $data['userInfo'] = $this->signApiService->getUserInfo();
            } catch (\Exception $e) {
                $this->logger->warning('Failed to fetch signd user info', ['exception' => $e]);
            }
        }

        return new JSONResponse($data);
    }

    /**
     * Save API key manually
     */
    public function saveApiKey(string $apiKey): JSONResponse {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return new JSONResponse(['error' => 'API key cannot be empty'], Http::STATUS_BAD_REQUEST);
        }

        // Validate the key before saving
        try {
            $userInfo = $this->signApiService->validateApiKey($apiKey);
        } catch (\Exception $e) {
            $this->logger->warning('API key validation failed', ['exception' => $e]);
            return SignApiService::apiErrorResponse($e, 'Invalid API key. Please check the key and try again.', Http::STATUS_UNAUTHORIZED);
        }

        $this->signApiService->setApiKey($apiKey);

        return new JSONResponse([
            'success' => true,
            'userInfo' => $userInfo,
        ]);
    }

    /**
     * Login with email/password to get API key
     */
    public function login(string $email, string $password): JSONResponse {
        if (trim($email) === '' || trim($password) === '') {
            return new JSONResponse(['error' => 'Email and password are required'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $result = $this->signApiService->login($email, $password);

            if (!isset($result['apikey'])) {
                return new JSONResponse(['error' => 'Login failed: no API key returned'], Http::STATUS_UNAUTHORIZED);
            }

            $this->signApiService->setApiKey($result['apikey']);

            return new JSONResponse([
                'success' => true,
                'userInfo' => [
                    'email' => $email,
                    'clearName' => $result['clearName'] ?? null,
                    'language' => $result['language'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('signd login failed', ['exception' => $e]);
            return SignApiService::apiErrorResponse($e, 'Login failed. Please check your credentials.', Http::STATUS_UNAUTHORIZED);
        }
    }

    /**
     * Register a new signd account
     */
    public function register(
        string $productPlan,
        string $organisation,
        string $street,
        string $houseNumber,
        string $zipCode,
        string $city,
        string $clearName,
        string $email,
        string $password,
        bool $agbAccepted,
        bool $dsbAccepted,
        string $country = 'DE',
        string $vatId = '',
        string $couponCode = '',
    ): JSONResponse {
        if (!$agbAccepted || !$dsbAccepted) {
            return new JSONResponse(
                ['error' => 'Terms of service and privacy policy must be accepted'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $data = [
            'productPlan' => $productPlan,
            'organisation' => $organisation,
            'street' => $street,
            'houseNumber' => $houseNumber,
            'zipCode' => $zipCode,
            'city' => $city,
            'country' => $country,
            'clearName' => $clearName,
            'email' => $email,
            'password' => $password,
            'agbAccepted' => $agbAccepted,
            'dsbAccepted' => $dsbAccepted,
        ];

        if ($vatId !== '') {
            $data['vatId'] = $vatId;
        }
        if ($couponCode !== '') {
            $data['couponCode'] = $couponCode;
        }

        try {
            $result = $this->signApiService->registerAccount($data);

            if (!isset($result['apiKey'])) {
                return new JSONResponse(
                    ['error' => 'Registration failed: no API key returned'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            $this->signApiService->setApiKey($result['apiKey']);

            return new JSONResponse([
                'success' => true,
                'accountId' => $result['accountId'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('signd registration failed', ['exception' => $e]);
            return SignApiService::apiErrorResponse($e, 'Registration failed');
        }
    }

    /**
     * Delete the stored API key (disconnect)
     */
    public function deleteApiKey(): JSONResponse {
        $this->signApiService->setApiKey('');
        return new JSONResponse(['success' => true]);
    }

    /**
     * Get pricing information
     */
    public function getPrices(): JSONResponse {
        try {
            $prices = $this->signApiService->getPrices();
            return new JSONResponse($prices);
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch signd prices', ['exception' => $e]);
            return SignApiService::apiErrorResponse($e, 'Failed to fetch pricing information');
        }
    }

    /**
     * Validate the current API key
     */
    public function validate(): JSONResponse {
        $apiKey = $this->signApiService->getApiKey();
        if ($apiKey === '') {
            return new JSONResponse([
                'valid' => false,
                'error' => 'No API key configured',
            ]);
        }

        try {
            $userInfo = $this->signApiService->getUserInfo();
            return new JSONResponse([
                'valid' => true,
                'userInfo' => $userInfo,
            ]);
        } catch (\Exception $e) {
            return new JSONResponse([
                'valid' => false,
                'error' => 'API key is invalid or expired',
            ]);
        }
    }
}