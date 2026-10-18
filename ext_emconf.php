<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'MFA unlock',
    'description' => 'Unlock locked MFA providers of backend users: new view in the backend user module and a CLI command.',
    'category' => 'be',
    'author' => 'Georg Ringer',
    'author_email' => 'mail@ringer.it',
    'state' => 'alpha',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-15.99.99',
            'beuser' => '14.0.0-15.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'GeorgRinger\\MfaUnlock\\' => 'Classes/',
        ],
    ],
];
