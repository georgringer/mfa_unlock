<?php

use GeorgRinger\MfaUnlock\Controller\MfaUnlockController;

/**
 * Definitions for modules provided by EXT:mfa_unlock
 */
return [
    'backend_user_mfa' => [
        'parent' => 'admin',
        'access' => 'admin',
        'path' => '/module/users/mfa',
        'iconIdentifier' => 'module-user',
        'labels' => 'mfa_unlock.module',
        'appearance' => [
            'renderInModuleMenu' => false,
        ],
        'routes' => [
            '_default' => [
                'target' => MfaUnlockController::class,
            ],
        ],
    ],
];
