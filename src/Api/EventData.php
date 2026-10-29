<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Api;

final class EventData
{
    /**
     * @param array{name: string|null, street: string|null, postalCode: string|null, town: string|null, country: string|null}|null $location
     */
    public function __construct(
        public readonly string $title,
        public readonly string|null $description,
        public readonly bool $allDay,
        public readonly \DateTimeImmutable $start,
        public readonly \DateTimeImmutable $end,
        public readonly string $calendarKey,
        public readonly array|null $location,
        public readonly string|null $videoUrl,
        public readonly string|null $signupUrl,
        public readonly string|null $imageId,
        public readonly bool $cancelled,
    ) {
    }
}
