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
        $this->assertStringContainsString('<a href="https://musterkirche.jetro.tools/anmeldung/0192f3a4-5b6c-7d8e-9f01-23456789abcd"', $row['teaser']);
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
        $this->assertSame(mktime(23, 59, 59, 12, 26, 2026), (int) $row['endTime'], 'wie der Kern: Ende des letzten Tages');
    }

    public function testEintaegigGanztaegigEndetUm235959(): void
    {
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['allDay' => true, 'start' => '2026-12-24', 'end' => '2026-12-24']));

        $row = $this->event();
        $this->assertNull($row['endDate']);
        $this->assertSame(mktime(0, 0, 0, 12, 24, 2026), (int) $row['startTime']);
        $this->assertSame(mktime(23, 59, 59, 12, 24, 2026), (int) $row['endTime']);
    }

    public function testTitelDerKodiertZuLangWird422(): void
    {
        $response = $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['title' => str_repeat('a', 254).'#']));

        $this->assertError($response, 422, 'validation');
        $this->assertSame('title', $this->json($response)['field']);
        $this->assertFalse($this->event());
    }

    public function testOrtDerKodiertZuLangWird422(): void
    {
        $location = ['name' => str_repeat('O', 300), 'street' => null, 'postalCode' => null, 'town' => null, 'country' => null];
        $response = $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['location' => $location]));

        $this->assertError($response, 422, 'validation');
        $this->assertSame('location.name', $this->json($response)['field']);
    }

    public function testLangerTitelErgibtAliasHoechstens255(): void
    {
        $this->assertSame(201, $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['title' => str_repeat('Wort ', 51)]))->getStatusCode());

        $this->assertLessThanOrEqual(255, \strlen($this->event()['alias']));
    }

    public function testInsertTagsInTextOrtUndLinkWerdenKodiert(): void
    {
        $location = ['name' => '{{file::x.php}}', 'street' => '{{env::host}}', 'postalCode' => null, 'town' => null, 'country' => null];
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture([
            'description' => 'Text {{file::x.php}} {{env::host}}',
            'location' => $location,
            'signupUrl' => 'https://example.org/?a={{env::host}}',
        ]));

        $row = $this->event();
        foreach (['teaser', 'location', 'address'] as $field) {
            $this->assertStringNotContainsString('{{', (string) $row[$field], $field);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function boeseLinks(): iterable
    {
        yield 'javascript gross' => ['JAVASCRIPT:alert(1)'];
        yield 'Leerraum davor' => [' https://example.org/'];
        yield 'Steuerzeichen davor' => ["\x01https://example.org/"];
        yield 'data' => ['data:text/html,<script>alert(1)</script>'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('boeseLinks')]
    public function testUnsichereLinks422(string $url): void
    {
        $response = $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['videoUrl' => $url]));

        $this->assertError($response, 422, 'validation');
        $this->assertSame('videoUrl', $this->json($response)['field']);
    }

    public function testAnfrageUeber4MbWird413(): void
    {
        $body = json_encode($this->fixture(['description' => str_repeat('x', 4 * 1024 * 1024)]), JSON_THROW_ON_ERROR);

        $this->assertError($this->call('PUT', '/events/'.self::EVENT_ID, $body), 413, 'file_too_large');
    }

    public function testAusnahmeWird500OhneInterna(): void
    {
        $this->db->executeStatement('DROP TABLE tl_calendar_events');

        $response = $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());

        $this->assertError($response, 500, 'internal');
        $this->assertStringNotContainsString('tl_calendar_events', (string) $response->getContent());
        $this->assertStringNotContainsString('SQL', (string) $response->getContent());
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
        $this->assertStringContainsString('&#60;script&#62;', $row['title'], 'wie Input::encodeInput(encodeAll)');
        $this->assertStringNotContainsString('<b>', $row['teaser']);
    }

    public function testSimpleTokensUndKlammernWieImBackend(): void
    {
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['title' => '##member_email## (a) = b \\', 'description' => '##member_email##']));

        $row = $this->event();
        $this->assertSame('&#35;&#35;member_email&#35;&#35; &#40;a&#41; &#61; b &#92;', $row['title']);
        $this->assertStringNotContainsString('##', $row['teaser']);
    }

    public function testZuLangeBeschreibung422(): void
    {
        $response = $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['description' => str_repeat('a', 70000)]));

        $this->assertError($response, 422, 'validation');
        $this->assertSame('description', $this->json($response)['field']);
    }

    public function testPostAufTermin404(): void
    {
        $this->assertError($this->call('POST', '/events/'.self::EVENT_ID, $this->fixture()), 404, 'not_found');
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
