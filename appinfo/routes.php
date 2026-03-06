<?php

declare(strict_types=1);

return [
    'routes' => [
        // Page (Overview)
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // Settings
        ['name' => 'settings#getConfig', 'url' => '/settings/config', 'verb' => 'GET'],
        ['name' => 'settings#saveApiKey', 'url' => '/settings/api-key', 'verb' => 'POST'],
        ['name' => 'settings#deleteApiKey', 'url' => '/settings/api-key', 'verb' => 'DELETE'],
        ['name' => 'settings#login', 'url' => '/settings/login', 'verb' => 'POST'],
        ['name' => 'settings#register', 'url' => '/settings/register', 'verb' => 'POST'],
        ['name' => 'settings#getPrices', 'url' => '/settings/prices', 'verb' => 'GET'],
        ['name' => 'settings#validate', 'url' => '/settings/validate', 'verb' => 'GET'],

        // Overview
        ['name' => 'overview#list', 'url' => '/api/overview/list', 'verb' => 'GET'],
        ['name' => 'overview#cancel', 'url' => '/api/overview/{processId}/cancel', 'verb' => 'POST'],

        // Processes
        ['name' => 'process#getByFileId', 'url' => '/api/processes/{fileId}', 'verb' => 'GET'],
        ['name' => 'process#startWizard', 'url' => '/api/processes/start-wizard', 'verb' => 'POST'],
        ['name' => 'process#refresh', 'url' => '/api/processes/{processId}/refresh', 'verb' => 'POST'],
        ['name' => 'process#resumeWizard', 'url' => '/api/processes/{processId}/resume-wizard', 'verb' => 'POST'],
        ['name' => 'process#cancelWizard', 'url' => '/api/processes/{processId}/cancel-wizard', 'verb' => 'POST'],
        ['name' => 'process#download', 'url' => '/api/processes/{processId}/download', 'verb' => 'GET'],
        ['name' => 'process#signatureImage', 'url' => '/api/signature/{signatureKey}', 'verb' => 'GET'],
    ],
];
