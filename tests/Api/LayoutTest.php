<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

use JetroTools\ContaoBundle\EventListener\LayoutListener;
use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase
{
    public function testDarstellungSetztBeideVorlagenUndBildgroesse(): void
    {
        $this->assertSame(
            ['cal_template' => 'event_jetro_list', 'customTpl' => 'mod_eventlist_jetro_list', 'imgSize' => serialize(['176', '176', 'crop'])],
            LayoutListener::fieldsFor('list', ['cal_template' => 'event_list', 'customTpl' => '', 'imgSize' => serialize(['', '', ''])]),
        );
    }

    public function testEigeneBildgroesseBleibt(): void
    {
        $own = serialize(['300', '200', 'proportional']);

        $this->assertArrayNotHasKey('imgSize', LayoutListener::fieldsFor('large', ['cal_template' => '', 'customTpl' => '', 'imgSize' => $own]));
    }

    public function testStandardSetztNurJetroVorlagenZurueck(): void
    {
        $this->assertSame(
            ['cal_template' => 'event_list', 'customTpl' => ''],
            LayoutListener::fieldsFor('', ['cal_template' => 'event_jetro_card', 'customTpl' => 'mod_eventlist_jetro_cards', 'imgSize' => '']),
        );
        $this->assertSame([], LayoutListener::fieldsFor('', ['cal_template' => 'event_upcoming', 'customTpl' => 'mod_eigene', 'imgSize' => '']));
    }
}
