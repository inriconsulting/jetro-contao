<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

use PHPUnit\Framework\TestCase;

final class DcaTest extends TestCase
{
    public function testTokenFelderNichtVersioniert(): void
    {
        $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] = '';
        include __DIR__.'/../../contao/dca/tl_jetro_connection.php';

        foreach (['tokenHash', 'tokenCreated'] as $field) {
            // Wiederherstellen einer Version darf ein widerrufenes Token nie zurückbringen
            $this->assertFalse($GLOBALS['TL_DCA']['tl_jetro_connection']['fields'][$field]['eval']['versionize'], $field);
        }
    }
}
