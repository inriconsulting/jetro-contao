<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Storage;

use Doctrine\DBAL\Connection;

/**
 * Letzte 50 Anfragen je Verbindung für die Backend-Seite „Jetro“, ohne Inhalte und Token (V7h).
 */
final class RequestLog
{
    public const KEEP = 50;

    /** Abgewiesene Anfragen ohne Verbindung dürfen echte Einträge nicht verdrängen */
    public const KEEP_REJECTED = 10;

    public function __construct(private readonly Connection $db)
    {
    }

    public function add(int $connectionId, string $method, string $path, int $status, string $code): void
    {
        $this->db->insert('tl_jetro_log', [
            'pid' => $connectionId,
            'tstamp' => time(),
            'method' => substr($method, 0, 10),
            'path' => substr($path, 0, 255),
            'status' => $status,
            'code' => $code,
        ]);

        $keep = 0 === $connectionId ? self::KEEP_REJECTED : self::KEEP;

        $this->db->executeStatement(
            'DELETE FROM tl_jetro_log WHERE pid = ? AND id NOT IN (SELECT id FROM (SELECT id FROM tl_jetro_log WHERE pid = ? ORDER BY id DESC LIMIT '.$keep.') keep)',
            [$connectionId, $connectionId],
        );
    }
}
