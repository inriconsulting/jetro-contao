<?php

use JetroTools\ContaoBundle\Backend\TokenAction;

$GLOBALS['BE_MOD']['content']['jetro'] = [
    'tables' => ['tl_jetro_connection', 'tl_jetro_calendar', 'tl_jetro_log'],
    'token' => [TokenAction::class, 'generate'],
    'revoke' => [TokenAction::class, 'revoke'],
];
