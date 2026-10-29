<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Api;

/**
 * Fehler nach Vertrag V6; Meldung ist ein fester Satz, nie Eingabe oder Stacktrace.
 */
final class ApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly string|null $field = null,
    ) {
        parent::__construct($message);
    }

    public static function validation(string $field, string $message = 'Ungültiger Wert.'): self
    {
        return new self(422, 'validation', $message, $field);
    }

    public static function notFound(): self
    {
        return new self(404, 'not_found', 'Nicht gefunden.');
    }

    public static function foreign(): self
    {
        return new self(409, 'foreign', 'Das Objekt gehört einer anderen Verbindung.');
    }
}
