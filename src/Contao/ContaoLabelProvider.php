<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Contao;

use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use JetroTools\ContaoBundle\Api\LabelProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Linktexte in der Sprache der Website, zu der der Kalender gehört.
 */
final class ContaoLabelProvider implements LabelProviderInterface
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function label(string $key, int $calendarId): string
    {
        $this->framework->initialize();

        $jumpTo = (int) $this->framework->getAdapter(CalendarModel::class)->findById($calendarId)?->jumpTo;
        $page = $jumpTo > 0 ? $this->framework->getAdapter(PageModel::class)->findWithDetails($jumpTo) : null;
        $locale = $page?->rootLanguage ? str_replace('-', '_', $page->rootLanguage) : null;

        return $this->translator->trans('MSC.jetro_'.$key, [], 'contao_default', $locale);
    }
}
