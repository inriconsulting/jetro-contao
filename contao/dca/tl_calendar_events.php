<?php

// Eigentum je Objekt (V7b): nur Termine mit Jetro-ID gehören einer Verbindung
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['sql']['keys']['jetroId'] = 'unique';

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['jetroConnection'] = [
    'eval' => ['doNotCopy' => true],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['jetroId'] = [
    'eval' => ['doNotCopy' => true],
    'sql' => ['type' => 'string', 'length' => 36, 'notnull' => false],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['jetroCancelled'] = [
    'eval' => ['doNotCopy' => true],
    'sql' => ['type' => 'boolean', 'default' => false],
];
