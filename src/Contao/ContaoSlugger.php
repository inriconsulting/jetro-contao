<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Contao;

use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Slug\Slug;
use JetroTools\ContaoBundle\Api\SluggerInterface;

final class ContaoSlugger implements SluggerInterface
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Slug $slug,
    ) {
    }

    public function slug(string $text, int $calendarId): string
    {
        $this->framework->initialize();

        // Einstellungen der Weiterleitungsseite (Zeichensatz der Website) wie im Backend
        $jumpTo = (int) $this->framework->getAdapter(CalendarModel::class)->findById($calendarId)?->jumpTo;

        return $this->slug->generate($text, $jumpTo > 0 ? $jumpTo : []);
    }
}
