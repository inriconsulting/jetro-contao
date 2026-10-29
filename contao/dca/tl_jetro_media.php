<?php

// Zuordnung Jetro-Datei → Contao-Datei (V4); ohne eigene Backend-Ansicht
$GLOBALS['TL_DCA']['tl_jetro_media'] = [
    'config' => [
        'sql' => ['keys' => ['id' => 'primary', 'jetroId' => 'unique']],
    ],
    'fields' => [
        'id' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true]],
        'pid' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'tstamp' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'jetroId' => ['sql' => ['type' => 'string', 'length' => 36, 'notnull' => false]],
        'uuid' => ['sql' => ['type' => 'binary', 'length' => 16, 'fixed' => true, 'notnull' => false]],
    ],
];
