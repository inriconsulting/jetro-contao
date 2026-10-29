<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Template;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Zeigt „Abgesagt“ in Terminliste und Leseansicht (V7e); der Termin bleibt veröffentlicht.
 */
#[AsHook('parseTemplate')]
final class CancelledEventListener
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function __invoke(Template $template): void
    {
        if (!str_starts_with($template->getName(), 'event') || empty($template->jetroCancelled)) {
            return;
        }

        $data = self::apply($template->getData(), $this->translator->trans('MSC.jetro_cancelled', [], 'contao_default'));
        $template->setData($data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function apply(array $data, string $label): array
    {
        if (empty($data['jetroCancelled'])) {
            return $data;
        }

        $prefix = $label.': ';

        foreach (['title', 'link'] as $key) {
            if (isset($data[$key]) && \is_string($data[$key]) && !str_starts_with($data[$key], $prefix)) {
                $data[$key] = $prefix.$data[$key];
            }
        }

        // Vorlagen hängen class direkt an „block“ an, das führende Leerzeichen muss bleiben
        $class = (string) ($data['class'] ?? '');

        if (!preg_match('/(^|\s)jetro-cancelled(\s|$)/', $class)) {
            $data['class'] = $class.' jetro-cancelled';
        }

        return $data;
    }
}
