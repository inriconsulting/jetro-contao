<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

final class PutEventTest extends ApiTestCase
{
    public function testV2LegtTerminAnMitKernFeldern(): void
    {
        $response = $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['id' => self::EVENT_ID], $this->json($response));

        $row = $this->event();
        $this->assertSame(1, (int) $row['pid'], 'Standard-Kalender');
        $this->assertSame('Gottesdienst', $row['title']);
        $this->assertSame('gottesdienst-0192f3a4', $row['alias']);
        $this->assertSame(1, (int) $row['addTime']);
        $this->assertSame(strtotime('2026-11-01T10:00:00+01:00'), (int) $row['startTime']);
        $this->assertSame(strtotime('2026-11-01T11:30:00+01:00'), (int) $row['endTime']);
        $this->assertSame(mktime(0, 0, 0, 11, 1, 2026), (int) $row['startDate']);
        $this->assertNull($row['endDate']);
        $this->assertSame('Gemeindehaus', $row['location']);
        $this->assertSame('Musterweg 1, 74906 Musterstadt', $row['address']);
        $this->assertSame(1, (int) $row['published']);
        $this->assertSame(1, (int) $row['jetroConnection']);
        $this->assertSame(0, (int) $row['jetroCancelled']);
        $this->assertStringContainsString('<p>Klartext mit<br>Zeilenumbrüchen</p>', $row['teaser']);
        $this->assertStringContainsString('<a href="https://musterkirche.jetro.tools/anmeldung/abc"', $row['teaser']);
        $this->assertStringContainsString('Zur Anmeldung', $row['teaser']);
    }

    public function testV2AendertTerminAliasBleibtUndRedaktionsfelderBleiben(): void
    {
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());
        $this->db->update('tl_calendar_events', ['published' => 0, 'featured' => 1], ['jetroId' => self::EVENT_ID]);

        $response = $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['title' => 'Familiengottesdienst', 'location' => null]));

        $this->assertSame(200, $response->getStatusCode());
        $row = $this->event();
        $this->assertSame('Familiengottesdienst', $row['title']);
        $this->assertSame('gottesdienst-0192f3a4', $row['alias']);
        $this->assertSame(0, (int) $row['published']);
        $this->assertSame(1, (int) $row['featured']);
        $this->assertSame('', $row['location']);
        $this->assertSame('', (string) $row['address']);
    }

    public function testGanztaegigUeberMehrereTage(): void
    {
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['allDay' => true, 'start' => '2026-12-24', 'end' => '2026-12-26']));

        $row = $this->event();
        $this->assertSame(0, (int) $row['addTime']);
        $this->assertSame(mktime(0, 0, 0, 12, 24, 2026), (int) $row['startDate']);
        $this->assertSame(mktime(0, 0, 0, 12, 26, 2026), (int) $row['endDate']);
        $this->assertSame(mktime(0, 0, 0, 12, 26, 2026), (int) $row['endTime']);
    }

    public function testTitelMitHtmlUndInsertTagsWirdKodiert(): void
    {
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture([
            'title' => '<script>alert(1)</script> {{date}}',
            'description' => '<b>fett</b>',
        ]));

        $row = $this->event();
        $this->assertStringNotContainsString('<script>', $row['title']);
        $this->assertStringNotContainsString('{{', $row['title']);
        $this->assertStringContainsString('&lt;script&gt;', $row['title']);
        $this->assertStringNotContainsString('<b>', $row['teaser']);
    }

    public function testKalenderZuordnungUndUmhaengen(): void
    {
        $this->db->insert('tl_jetro_calendar', ['pid' => 1, 'jetroKey' => 'gottesdienst', 'calendar' => 2]);
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());
        $this->assertSame(2, (int) $this->event()['pid']);

        $this->db->delete('tl_jetro_calendar', ['pid' => 1]);
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());
        $this->assertSame(1, (int) $this->event()['pid']);
        $this->assertSame('Gottesdienste', $this->db->fetchOne('SELECT title FROM tl_calendar WHERE id = 2'), 'Name aus Jetro ändert keinen Kalender');
    }

    public function testOhneStandardKalender422Calendar(): void
    {
        $this->db->update('tl_jetro_connection', ['defaultCalendar' => 0], ['id' => 1]);

        $response = $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());

        $this->assertError($response, 422, 'validation');
        $this->assertSame('calendar', $this->json($response)['field']);
    }

    public function testUngueltigesJson400(): void
    {
        $this->assertError($this->call('PUT', '/events/'.self::EVENT_ID, '{kaputt'), 400, 'invalid_json');
    }

    public function testLinkOhneHttp422(): void
    {
        $response = $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['videoUrl' => 'javascript:alert(1)']));

        $this->assertError($response, 422, 'validation');
        $this->assertSame('videoUrl', $this->json($response)['field']);
    }

    public function testLeererTitel422(): void
    {
        $response = $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['title' => '  ']));

        $this->assertError($response, 422, 'validation');
        $this->assertSame('title', $this->json($response)['field']);
    }

    public function testIdEinerAnderenVerbindung409Foreign(): void
    {
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(), ['HTTP_X_JETRO_TOKEN' => self::OTHER_TOKEN]);

        $this->assertError($this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture()), 409, 'foreign');
        $this->assertSame(2, (int) $this->event()['jetroConnection']);
    }

    public function testFremderTerminOhneJetroIdBleibtUnberuehrt(): void
    {
        $this->db->insert('tl_calendar_events', ['id' => 99, 'pid' => 1, 'title' => 'Redaktion', 'alias' => 'gottesdienst-0192f3a4', 'published' => 1]);

        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());

        $this->assertSame('Redaktion', $this->db->fetchOne('SELECT title FROM tl_calendar_events WHERE id = 99'));
        $this->assertNotSame('gottesdienst-0192f3a4', $this->event()['alias'], 'Alias darf nicht doppelt vergeben werden');
    }

    public function testUnbekanntesBild409ImageMissing(): void
    {
        $this->assertError(
            $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['imageId' => '0192f3a4-0000-7000-8000-000000000001'])),
            409,
            'image_missing',
        );
    }

    public function testBildSetzenUndEntfernen(): void
    {
        $imageId = '0192f3a4-0000-7000-8000-000000000002';
        $this->call('PUT', '/media/'.$imageId, ['filename' => 'bild.png', 'mimeType' => 'image/png', 'contentBase64' => base64_encode(MediaTest::png())]);

        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['imageId' => $imageId]));
        $row = $this->event();
        $this->assertSame(1, (int) $row['addImage']);
        $this->assertArrayHasKey($row['singleSRC'], $this->media->files);

        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['imageId' => null]));
        $row = $this->event();
        $this->assertSame(0, (int) $row['addImage']);
        $this->assertNull($row['singleSRC']);
        $this->assertCount(1, $this->media->files, 'Datei bleibt');
    }
}
