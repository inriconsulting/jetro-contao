<?php

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_jetro_log'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'notDeletable' => true,
        'sql' => ['keys' => ['id' => 'primary']],
    ],
    'list' => [
        'sorting' => ['mode' => DataContainer::MODE_SORTED, 'fields' => ['tstamp'], 'flag' => DataContainer::SORT_DESC, 'panelLayout' => 'filter;search,limit'],
        'label' => ['fields' => ['tstamp', 'method', 'path', 'status', 'code'], 'format' => '%s'],
        'operations' => [
            'show' => ['href' => 'act=show', 'icon' => 'show.svg'],
        ],
    ],
    'fields' => [
        'id' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true]],
        'pid' => ['filter' => true, 'foreignKey' => 'tl_jetro_connection.title', 'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'tstamp' => ['eval' => ['rgxp' => 'datim'], 'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'method' => ['filter' => true, 'sql' => ['type' => 'string', 'length' => 10, 'default' => '']],
        'path' => ['search' => true, 'sql' => ['type' => 'string', 'length' => 255, 'default' => '']],
        'status' => ['filter' => true, 'sql' => ['type' => 'smallint', 'unsigned' => true, 'default' => 0]],
        'code' => ['filter' => true, 'sql' => ['type' => 'string', 'length' => 32, 'default' => '']],
    ],
];
