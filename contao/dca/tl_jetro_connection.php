<?php

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_jetro_connection'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ctable' => ['tl_jetro_calendar'],
        'switchToEdit' => true,
        'enableVersioning' => true,
        'sql' => ['keys' => ['id' => 'primary', 'tokenHash' => 'index']],
    ],
    'list' => [
        'sorting' => ['mode' => DataContainer::MODE_SORTED, 'fields' => ['title'], 'flag' => DataContainer::SORT_INITIAL_LETTER_ASC, 'panelLayout' => 'search,limit'],
        'label' => ['fields' => ['title'], 'format' => '%s'],
        'global_operations' => [
            'log' => ['href' => 'table=tl_jetro_log', 'icon' => 'news.svg', 'class' => 'header_log'],
        ],
        'operations' => [
            'edit' => ['href' => 'act=edit', 'icon' => 'edit.svg'],
            'children' => ['href' => 'table=tl_jetro_calendar', 'icon' => 'children.svg'],
            'token' => ['href' => 'key=token', 'icon' => 'tablewizard.svg'],
            'revoke' => ['href' => 'key=revoke', 'icon' => 'unpublished.svg'],
            'delete' => ['href' => 'act=delete', 'icon' => 'delete.svg', 'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '').'\'))return false"'],
            'show' => ['href' => 'act=show', 'icon' => 'show.svg'],
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},title;{calendar_legend},defaultCalendar;{display_legend},showCredit',
    ],
    'fields' => [
        'id' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true]],
        'tstamp' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'title' => [
            'inputType' => 'text',
            'search' => true,
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
        ],
        'defaultCalendar' => [
            'inputType' => 'select',
            'foreignKey' => 'tl_calendar.title',
            'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
            'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
        ],
        'showCredit' => [
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 m12'],
            'sql' => ['type' => 'boolean', 'default' => true],
        ],
        'tokenHash' => ['eval' => ['doNotCopy' => true, 'doNotShow' => true], 'sql' => ['type' => 'string', 'length' => 64, 'default' => '']],
        'tokenCreated' => ['eval' => ['doNotCopy' => true, 'rgxp' => 'datim'], 'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
    ],
];
