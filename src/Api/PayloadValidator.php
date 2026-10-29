<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Api;

final class PayloadValidator
{
    public const UUID = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    /**
     * @param array<mixed> $data
     */
    public function event(array $data): EventData
    {
        $title = $data['title'] ?? null;

        if (!\is_string($title) || '' === trim($title) || mb_strlen($title) > 255) {
            throw ApiException::validation('title');
        }

        $description = $data['description'] ?? null;

        if (null !== $description && !\is_string($description)) {
            throw ApiException::validation('description');
        }

        if (!\is_bool($data['allDay'] ?? null)) {
            throw ApiException::validation('allDay');
        }

        $start = $this->date($data['start'] ?? null, $data['allDay'], 'start');
        $end = $this->date($data['end'] ?? null, $data['allDay'], 'end');

        if ($end < $start) {
            throw ApiException::validation('end');
        }

        $calendar = $data['calendar'] ?? null;

        if (!\is_array($calendar) || !\is_string($calendar['key'] ?? null) || '' === trim($calendar['key'])) {
            throw ApiException::validation('calendar');
        }

        $cancelled = $data['cancelled'] ?? false;

        if (!\is_bool($cancelled)) {
            throw ApiException::validation('cancelled');
        }

        $imageId = $data['imageId'] ?? null;

        if (null !== $imageId && (!\is_string($imageId) || !preg_match('/^'.self::UUID.'$/', $imageId))) {
            throw ApiException::validation('imageId');
        }

        return new EventData(
            title: trim($title),
            description: $description,
            allDay: $data['allDay'],
            start: $start,
            end: $end,
            calendarKey: trim($calendar['key']),
            location: $this->location($data['location'] ?? null),
            videoUrl: $this->url($data['videoUrl'] ?? null, 'videoUrl'),
            signupUrl: $this->url($data['signupUrl'] ?? null, 'signupUrl'),
            imageId: null === $imageId ? null : strtolower($imageId),
            cancelled: $cancelled,
        );
    }

    /**
     * @param array<mixed> $data
     */
    public function cancelled(array $data): bool
    {
        if (!\is_bool($data['cancelled'] ?? null)) {
            throw ApiException::validation('cancelled');
        }

        return $data['cancelled'];
    }

    private function date(mixed $value, bool $allDay, string $field): \DateTimeImmutable
    {
        if (!\is_string($value)) {
            throw ApiException::validation($field);
        }

        if ($allDay) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            if (false === $date || $date->format('Y-m-d') !== $value) {
                throw ApiException::validation($field);
            }

            return $date;
        }

        // ISO 8601 mit Versatz; ohne Versatz wäre die Zeit mehrdeutig (V2)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/', $value)) {
            throw ApiException::validation($field);
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw ApiException::validation($field);
        }
    }

    /**
     * @return array{name: string|null, street: string|null, postalCode: string|null, town: string|null, country: string|null}|null
     */
    private function location(mixed $value): array|null
    {
        if (null === $value) {
            return null;
        }

        if (!\is_array($value)) {
            throw ApiException::validation('location');
        }

        $result = [];

        foreach (['name', 'street', 'postalCode', 'town', 'country'] as $key) {
            $item = $value[$key] ?? null;

            if (null !== $item && !\is_string($item)) {
                throw ApiException::validation('location.'.$key);
            }

            $result[$key] = null === $item || '' === trim($item) ? null : trim($item);
        }

        if (null !== $result['country'] && !preg_match('/^[A-Za-z]{2}$/', $result['country'])) {
            throw ApiException::validation('location.country');
        }

        return $result;
    }

    private function url(mixed $value, string $field): string|null
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value) || false === filter_var($value, FILTER_VALIDATE_URL) || !\in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw ApiException::validation($field, 'Nur http- und https-Links sind erlaubt.');
        }

        return $value;
    }
}
