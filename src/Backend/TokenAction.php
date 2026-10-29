<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Backend;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Doctrine\DBAL\Connection;
use JetroTools\ContaoBundle\Storage\ConnectionRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Token erzeugen und widerrufen. Zustandsänderung nur per POST mit Anfrage-Token;
 * das neue Token wird genau einmal angezeigt und nur als SHA-256 gespeichert (V7a).
 */
final class TokenAction
{
    public function __construct(
        private readonly Connection $db,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $router,
        private readonly TranslatorInterface $translator,
        #[Autowire(service: 'contao.csrf.token_manager')]
        private readonly ContaoCsrfTokenManager $csrf,
    ) {
    }

    public function generate(): string
    {
        return $this->page('token');
    }

    public function revoke(): string
    {
        return $this->page('revoke');
    }

    private function page(string $action): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $id = (int) $request?->query->get('id');
        $row = $this->db->fetchAssociative('SELECT id, title, tokenHash FROM tl_jetro_connection WHERE id = ?', [$id]);
        $back = $this->router->generate('contao_backend', ['do' => 'jetro']);

        if (false === $row) {
            return $this->frame($back, '<p class="tl_error">'.$this->t('not_found').'</p>');
        }

        if ('POST' === $request->getMethod() && 'jetro_'.$action === $request->request->get('FORM_SUBMIT')) {
            if ('token' === $action) {
                $token = ConnectionRepository::newToken();
                $this->db->update('tl_jetro_connection', ['tokenHash' => hash('sha256', $token), 'tokenCreated' => time(), 'tstamp' => time()], ['id' => $id]);

                return $this->frame($back, \sprintf(
                    '<p class="tl_confirm">%s</p><div class="widget"><h3><label for="jetro_token">%s</label></h3><input id="jetro_token" class="tl_text" type="text" readonly value="%s" onfocus="this.select()"><p class="tl_help tl_tip">%s</p></div>',
                    $this->e($this->t('token_created')),
                    $this->e($this->t('token_label')),
                    $this->e($token),
                    $this->e($this->t('token_once')),
                ));
            }

            $this->db->update('tl_jetro_connection', ['tokenHash' => '', 'tokenCreated' => 0, 'tstamp' => time()], ['id' => $id]);

            return $this->frame($back, '<p class="tl_confirm">'.$this->e($this->t('token_revoked')).'</p>');
        }

        $question = $this->t('token' === $action ? ('' === $row['tokenHash'] ? 'confirm_create' : 'confirm_replace') : 'confirm_revoke');

        return $this->frame($back, \sprintf(
            '<form method="post"><div class="tl_formbody_edit"><input type="hidden" name="FORM_SUBMIT" value="jetro_%s"><input type="hidden" name="REQUEST_TOKEN" value="%s"><p>%s</p></div><div class="tl_formbody_submit"><div class="tl_submit_container"><button type="submit" class="tl_submit">%s</button></div></div></form>',
            $action,
            $this->e($this->csrf->getDefaultTokenValue()),
            $this->e(\sprintf($question, $row['title'])),
            $this->e($this->t('token' === $action ? 'button_create' : 'button_revoke')),
        ));
    }

    private function frame(string $back, string $body): string
    {
        return \sprintf(
            '<div id="tl_buttons"><a href="%s" class="header_back">%s</a></div><div class="tl_listing_container">%s</div>',
            $this->e($back),
            $this->e($this->translator->trans('MSC.backBT', [], 'contao_default')),
            $body,
        );
    }

    private function t(string $key): string
    {
        return $this->translator->trans('tl_jetro_connection.'.$key, [], 'contao_tl_jetro_connection');
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
