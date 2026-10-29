<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

final class DeleteEventTest extends ApiTestCase
{
    public function testV5LoeschtKuenftigenTermin(): void
    {
        $this->now = strtotime('2026-10-01');
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());
        $id = (int) $this->event()['id'];
        $this->db->insert('tl_content', ['pid' => $id, 'ptable' => 'tl_calendar_events']);

        $response = $this->call('DELETE', '/events/'.self::EVENT_ID);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertFalse($this->event());
        $this->assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_content'));
    }

    public function testKuenftigerTerminMitBuchungenWirdNurUnveroeffentlicht(): void
    {
        $this->now = strtotime('2026-10-01');
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());
        $this->db->insert('tl_calendar_events_member', ['pid' => $this->event()['id']]);

        $this->assertSame(204, $this->call('DELETE', '/events/'.self::EVENT_ID)->getStatusCode());
        $this->assertSame(0, (int) $this->event()['published']);
    }

    public function testVergangenerTerminBleibtStehen(): void
    {
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());

        $this->assertSame(204, $this->call('DELETE', '/events/'.self::EVENT_ID)->getStatusCode());
        $this->assertSame(1, (int) $this->event()['published']);
    }

    public function testLaufenderTerminBleibtStehen(): void
    {
        $this->now = strtotime('2026-11-01T10:30:00+01:00');
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());

        $this->assertSame(204, $this->call('DELETE', '/events/'.self::EVENT_ID)->getStatusCode());
        $this->assertSame(1, (int) $this->event()['published']);
    }

    public function testUnbekannt404(): void
    {
        $this->assertError($this->call('DELETE', '/events/'.self::EVENT_ID), 404, 'not_found');
    }

    public function testFremdeVerbindung409(): void
    {
        $this->now = strtotime('2026-10-01');
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(), ['HTTP_X_JETRO_TOKEN' => self::OTHER_TOKEN]);

        $this->assertError($this->call('DELETE', '/events/'.self::EVENT_ID), 409, 'foreign');
        $this->assertNotFalse($this->event());
    }
}
