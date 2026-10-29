<?php

// Sichtbare Auswahl der Jetro-Darstellung an der Terminliste (V7 j)
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventlist'] = str_replace(
    '{template_legend:hide}',
    '{jetro_legend},jetroLayout;{template_legend:hide}',
    $GLOBALS['TL_DCA']['tl_module']['palettes']['eventlist'],
);

$GLOBALS['TL_DCA']['tl_module']['fields']['jetroLayout'] = [
    'inputType' => 'select',
    'options' => ['cards', 'large', 'compact', 'list'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['jetroLayout_options'],
    'eval' => ['includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_module']['jetroLayout_standard'], 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 16, 'default' => ''],
];
