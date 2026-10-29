<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Api;

use Doctrine\DBAL\Connection;
use JetroTools\ContaoBundle\Storage\ConnectionRepository;
use JetroTools\ContaoBundle\Storage\MediaRepository;

/**
 * Schreibt Termine nur in Kern-Felder von tl_calendar_events (V7d) und nur eigene Objekte (V7b).
 */
final class EventWriter
{
    /** Tabellen anderer Erweiterungen, deren Einträge ein Löschen verhindern (V5) */
    private const BOOKING_TABLES = ['tl_calendar_events_member'];

    private readonly \Closure $now;

    public function __construct(
        private readonly Connection $db,
        private readonly ConnectionRepository $connections,
        private readonly MediaRepository $media,
        private readonly SluggerInterface $slugger,
        private readonly LabelProviderInterface $labels,
        \Closure|null $now = null,
    ) {
        $this->now = $now ?? static fn (): int => time();
    }

    /**
     * @param array<string, mixed> $connection
     *
     * @return bool true = angelegt
     */
    public function put(array $connection, string $id, EventData $data): bool
    {
        $row = $this->own($connection, $id, false);
        $calendar = $this->connections->calendarFor($connection, $data->calendarKey);

        if (null === $calendar) {
            throw ApiException::validation('calendar', 'Kein Standard-Kalender für diese Verbindung gewählt.');
        }

        $image = null;

        if (null !== $data->imageId) {
            $image = $this->media->uuidFor((int) $connection['id'], $data->imageId);

            if (null === $image) {
                throw new ApiException(409, 'image_missing', 'Das Bild ist nicht vorhanden.');
            }
        }

        $fields = [
            'pid' => $calendar,
            'tstamp' => ($this->now)(),
            'title' => $this->fit(TextEncoder::encode($data->title), 'title'),
            'location' => $this->fit(TextEncoder::encode((string) ($data->location['name'] ?? '')), 'location.name'),
            'address' => $this->fit(TextEncoder::encode($this->address($data->location)), 'location'),
            'teaser' => $this->teaser($data, $calendar),
            'addImage' => null === $image ? 0 : 1,
            'singleSRC' => $image,
            'jetroCancelled' => $data->cancelled ? 1 : 0,
            ...$this->times($data),
        ];

        if (false !== $row) {
            $this->db->update('tl_calendar_events', $fields, ['id' => $row['id']]);

            return false;
        }

        $this->db->insert('tl_calendar_events', $fields + [
            'alias' => $this->alias($data->title, $id, $calendar),
            'published' => 1,
            'jetroConnection' => (int) $connection['id'],
            'jetroId' => $id,
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $connection
     */
    public function cancel(array $connection, string $id, bool $cancelled): void
    {
        $row = $this->own($connection, $id, true);

        $this->db->update('tl_calendar_events', ['jetroCancelled' => $cancelled ? 1 : 0, 'tstamp' => ($this->now)()], ['id' => $row['id']]);
    }

    /**
     * Löscht nur künftige Termine; mit Buchungen nur unveröffentlicht; vergangene bleiben (V5).
     *
     * @param array<string, mixed> $connection
     */
    public function delete(array $connection, string $id): void
    {
        $row = $this->own($connection, $id, true);

        if ((int) $row['startTime'] <= ($this->now)()) {
            return;
        }

        if ($this->hasBookings((int) $row['id'])) {
            $this->db->update('tl_calendar_events', ['published' => 0, 'tstamp' => ($this->now)()], ['id' => $row['id']]);

            return;
        }

        $this->db->delete('tl_content', ['ptable' => 'tl_calendar_events', 'pid' => $row['id']]);
        $this->db->delete('tl_calendar_events', ['id' => $row['id']]);
    }

    /**
     * @param array<string, mixed> $connection
     *
     * @return array<string, mixed>|false
     */
    private function own(array $connection, string $id, bool $mustExist): array|false
    {
        $row = $this->db->fetchAssociative('SELECT id, jetroConnection, startTime FROM tl_calendar_events WHERE jetroId = ?', [$id]);

        if (false === $row) {
            if ($mustExist) {
                throw ApiException::notFound();
            }

            return false;
        }

        if ((int) $row['jetroConnection'] !== (int) $connection['id']) {
            throw ApiException::foreign();
        }

        return $row;
    }

    /**
     * @return array<string, int|null>
     */
    private function times(EventData $data): array
    {
        $tz = new \DateTimeZone(date_default_timezone_get());
        $start = $data->start->setTimezone($tz);
        $end = $data->end->setTimezone($tz);

        if ($data->allDay) {
            // Ganztägig: Datum gilt in der Zeitzone der Website, Ende = letzter Tag
            $startDate = (new \DateTimeImmutable($data->start->format('Y-m-d'), $tz))->getTimestamp();
            $endDate = (new \DateTimeImmutable($data->end->format('Y-m-d'), $tz))->getTimestamp();

            // Ende wie im Kern um 23:59:59, sonst fällt der Termin um 00:00 aus „Nächste …“-Listen
            return [
                'addTime' => 0,
                'startDate' => $startDate,
                'startTime' => $startDate,
                'endDate' => $endDate === $startDate ? null : $endDate,
                'endTime' => (new \DateTimeImmutable($data->end->format('Y-m-d'), $tz))->modify('+1 day')->getTimestamp() - 1,
            ];
        }

        $startDate = $start->setTime(0, 0)->getTimestamp();
        $endDate = $end->setTime(0, 0)->getTimestamp();

        return [
            'addTime' => 1,
            'startDate' => $startDate,
            'startTime' => $start->getTimestamp(),
            'endDate' => $endDate === $startDate ? null : $endDate,
            'endTime' => $end->getTimestamp(),
        ];
    }

    /**
     * @param array<string, string|null>|null $location
     */
    private function address(array|null $location): string
    {
        if (null === $location) {
            return '';
        }

        $town = trim(($location['postalCode'] ?? '').' '.($location['town'] ?? ''));
        $parts = array_filter([$location['street'] ?? null, '' === $town ? null : $town]);

        if (null !== $location['country'] && 'DE' !== strtoupper($location['country'])) {
            $parts[] = strtoupper($location['country']);
        }

        return implode(', ', $parts);
    }

    private function teaser(EventData $data, int $calendar): string|null
    {
        $html = [];

        if (null !== $data->description && '' !== trim($data->description)) {
            $html[] = TextEncoder::paragraphs($data->description);
        }

        foreach (['livestream' => $data->videoUrl, 'signup' => $data->signupUrl] as $key => $url) {
            if (null !== $url) {
                $html[] = \sprintf(
                    '<p class="jetro-%s"><a href="%s" target="_blank" rel="noopener">%s</a></p>',
                    $key,
                    TextEncoder::encode($url),
                    TextEncoder::encode($this->labels->label($key, $calendar)),
                );
            }
        }

        $teaser = implode("\n", $html);

        // Spalte teaser ist TEXT (65 535 Byte)
        if (\strlen($teaser) > 65535) {
            throw ApiException::validation('description', 'Die Beschreibung ist zu lang.');
        }

        return [] === $html ? null : $teaser;
    }

    /**
     * Längen erst nach dem Kodieren prüfen: aus & wird &amp;, die Spalten fassen 255 Zeichen.
     */
    private function fit(string $encoded, string $field): string
    {
        if (mb_strlen($encoded) > 255) {
            throw ApiException::validation($field, 'Der Text ist zu lang.');
        }

        return $encoded;
    }

    private function alias(string $title, string $id, int $calendar): string
    {
        // Platz für „-“ und die volle ID (37 Zeichen) in varchar(255) lassen
        $slug = rtrim(mb_substr($this->slugger->slug($title, $calendar), 0, 255 - 37), '-');
        $alias = ('' === $slug ? 'termin' : $slug).'-'.strtolower(substr($id, 0, 8));

        // Ein von der Redaktion vergebener gleicher Alias bleibt; dann hängt die volle ID an
        if (false !== $this->db->fetchOne('SELECT id FROM tl_calendar_events WHERE alias = ?', [$alias])) {
            $alias = ('' === $slug ? 'termin' : $slug).'-'.strtolower($id);
        }

        return $alias;
    }

    private function hasBookings(int $eventId): bool
    {
        $schema = $this->db->createSchemaManager();

        foreach (self::BOOKING_TABLES as $table) {
            if ($schema->tablesExist([$table]) && (int) $this->db->fetchOne("SELECT COUNT(*) FROM $table WHERE pid = ?", [$eventId]) > 0) {
                return true;
            }
        }

        return false;
    }
}
