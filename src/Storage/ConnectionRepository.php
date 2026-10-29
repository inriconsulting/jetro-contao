<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Storage;

use Doctrine\DBAL\Connection;

final class ConnectionRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Vergleicht zeitkonstant gegen jede aktive Verbindung (V7a).
     *
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): array|null
    {
        $hash = hash('sha256', $token);
        $match = null;

        foreach ($this->db->fetchAllAssociative("SELECT * FROM tl_jetro_connection WHERE tokenHash != ''") as $row) {
            if (hash_equals((string) $row['tokenHash'], $hash)) {
                $match = $row;
            }
        }

        return $match;
    }

    /**
     * @param array<string, mixed> $connection
     */
    public function calendarFor(array $connection, string $key): int|null
    {
        $mapped = $this->db->fetchOne(
            'SELECT m.calendar FROM tl_jetro_calendar m JOIN tl_calendar c ON c.id = m.calendar WHERE m.pid = ? AND m.jetroKey = ? ORDER BY m.id LIMIT 1',
            [$connection['id'], $key],
        );

        if (false !== $mapped) {
            return (int) $mapped;
        }

        $default = (int) $connection['defaultCalendar'];

        if ($default > 0 && false !== $this->db->fetchOne('SELECT id FROM tl_calendar WHERE id = ?', [$default])) {
            return $default;
        }

        return null;
    }

    public static function newToken(): string
    {
        return 'jt_'.bin2hex(random_bytes(32));
    }
}
