<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

final class MediaTest extends ApiTestCase
{
    private const MEDIA_ID = '0192f3a4-0000-7000-8000-00000000000a';

    public static function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
    }

    public function testV4LegtBildAnUndMeldetVorhandenes(): void
    {
        $body = ['filename' => 'bild.png', 'mimeType' => 'image/png', 'contentBase64' => base64_encode(self::png())];

        $response = $this->call('PUT', '/media/'.self::MEDIA_ID, $body);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['id' => self::MEDIA_ID], $this->json($response));
        $this->assertSame(['files/jetro/'.self::MEDIA_ID.'.png'], array_values($this->media->files));

        $this->assertSame(200, $this->call('PUT', '/media/'.self::MEDIA_ID, $body)->getStatusCode());
        $this->assertCount(1, $this->media->files, 'keine neue Datei');
    }

    public function testFalscherInhalt415(): void
    {
        $body = ['filename' => 'bild.png', 'mimeType' => 'image/png', 'contentBase64' => base64_encode('<?php echo 1;')];

        $this->assertError($this->call('PUT', '/media/'.self::MEDIA_ID, $body), 415, 'file_type_rejected');
        $this->assertSame([], $this->media->files);
    }

    public function testZuGross413(): void
    {
        $body = ['filename' => 'bild.png', 'mimeType' => 'image/png', 'contentBase64' => base64_encode(self::png().str_repeat("\0", 2 * 1024 * 1024))];

        $this->assertError($this->call('PUT', '/media/'.self::MEDIA_ID, $body), 413, 'file_too_large');
    }

    public function testZuGrosseAbmessungen413(): void
    {
        $image = imagecreatetruecolor(6001, 2);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $body = ['filename' => 'breit.png', 'mimeType' => 'image/png', 'contentBase64' => base64_encode($png)];

        $this->assertError($this->call('PUT', '/media/'.self::MEDIA_ID, $body), 413, 'file_too_large');
        $this->assertSame([], $this->media->files);
    }

    public function testKeinBase64422(): void
    {
        $this->assertError($this->call('PUT', '/media/'.self::MEDIA_ID, ['filename' => 'x.png', 'mimeType' => 'image/png', 'contentBase64' => '%%%']), 422, 'validation');
    }

    public function testBilderGehoerenKeinerVerbindung(): void
    {
        $body = ['filename' => 'bild.png', 'mimeType' => 'image/png', 'contentBase64' => base64_encode(self::png())];
        $this->assertSame(201, $this->call('PUT', '/media/'.self::MEDIA_ID, $body, ['HTTP_X_JETRO_TOKEN' => self::OTHER_TOKEN])->getStatusCode());

        // Verbindung B (neu angelegt) nutzt das Bild von A per PUT /media und per imageId
        $this->assertSame(200, $this->call('PUT', '/media/'.self::MEDIA_ID, $body)->getStatusCode());
        $this->assertCount(1, $this->media->files, 'keine neue Datei');
        $this->assertSame(201, $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['imageId' => self::MEDIA_ID]))->getStatusCode());
        $this->assertSame(1, (int) $this->event()['addImage']);
    }

    public function testInContaoGeloeschteDateiWirdNeuAbgelegt(): void
    {
        $body = ['filename' => 'bild.png', 'mimeType' => 'image/png', 'contentBase64' => base64_encode(self::png())];
        $this->call('PUT', '/media/'.self::MEDIA_ID, $body);
        $this->media->files = [];

        $this->assertError($this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['imageId' => self::MEDIA_ID])), 409, 'image_missing');
        $this->assertSame(201, $this->call('PUT', '/media/'.self::MEDIA_ID, $body)->getStatusCode());
        $this->assertCount(1, $this->media->files);
        $this->assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_jetro_media'));
    }

    public function testPngKopfMitMuell415(): void
    {
        $body = ['filename' => 'kaputt.png', 'mimeType' => 'image/png', 'contentBase64' => base64_encode("\x89PNG\r\n\x1a\n".str_repeat('x', 100))];

        $this->assertError($this->call('PUT', '/media/'.self::MEDIA_ID, $body), 415, 'file_type_rejected');
    }
}
