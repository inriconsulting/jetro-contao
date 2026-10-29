<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Storage;

use Doctrine\DBAL\Connection;
use JetroTools\ContaoBundle\Api\ApiException;

final class MediaRepository
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** Grenze gegen Bilder, die beim Verkleinern den Speicher sprengen */
    public const MAX_PIXELS = 6000;

    private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(
        private readonly Connection $db,
        private readonly MediaStorageInterface $storage,
    ) {
    }

    /**
     * @return bool true = neu angelegt
     */
    public function put(int $connectionId, string $id, string $content): bool
    {
        $row = $this->db->fetchAssociative('SELECT * FROM tl_jetro_media WHERE jetroId = ?', [$id]);

        if (false !== $row) {
            if ((int) $row['pid'] !== $connectionId) {
                throw ApiException::foreign();
            }

            if (null !== $row['uuid'] && $this->storage->exists($row['uuid'])) {
                return false;
            }

            // Datei in Contao gelöscht: neu ablegen
            $this->db->delete('tl_jetro_media', ['id' => $row['id']]);
        }

        if (\strlen($content) > self::MAX_BYTES) {
            throw new ApiException(413, 'file_too_large', 'Die Datei ist größer als 2 MB.');
        }

        // Typ am Inhalt prüfen, nie an Dateiname oder Angabe (V7g)
        $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);

        if (!isset(self::TYPES[$type])) {
            throw new ApiException(415, 'file_type_rejected', 'Nur PNG, JPEG und WebP sind erlaubt.');
        }

        $size = getimagesizefromstring($content);

        if (false === $size || $size[0] > self::MAX_PIXELS || $size[1] > self::MAX_PIXELS) {
            throw new ApiException(413, 'file_too_large', 'Das Bild ist größer als 6000 × 6000 Pixel.');
        }

        $uuid = $this->storage->store($id, self::TYPES[$type], $content);
        $this->db->insert('tl_jetro_media', ['pid' => $connectionId, 'tstamp' => time(), 'jetroId' => $id, 'uuid' => $uuid]);

        return true;
    }

    /**
     * UUID der Datei oder null, wenn Jetro sie (neu) hochladen muss.
     */
    public function uuidFor(int $connectionId, string $id): string|null
    {
        $row = $this->db->fetchAssociative('SELECT * FROM tl_jetro_media WHERE jetroId = ? AND pid = ?', [$id, $connectionId]);

        if (false === $row || null === $row['uuid'] || !$this->storage->exists($row['uuid'])) {
            return null;
        }

        return $row['uuid'];
    }
}
