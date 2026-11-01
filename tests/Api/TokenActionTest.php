<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Contao\CoreBundle\Exception\RedirectResponseException;
use JetroTools\ContaoBundle\Backend\TokenAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

final class TokenActionTest extends TestCase
{
    private Connection $db;
    private RequestStack $stack;
    private Session $session;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->db->executeStatement("CREATE TABLE tl_jetro_connection (id INTEGER PRIMARY KEY, tstamp INTEGER DEFAULT 0, title TEXT DEFAULT '', tokenHash TEXT DEFAULT '', tokenCreated INTEGER DEFAULT 0)");
        $this->db->insert('tl_jetro_connection', ['id' => 7, 'title' => 'Jetro', 'tokenHash' => hash('sha256', 'alt')]);
        $this->stack = new RequestStack();
        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testGetZeigtNurDieRueckfrageUndAendertNichts(): void
    {
        $html = $this->action('GET');

        $this->assertStringContainsString('<form method="post">', $html);
        $this->assertStringContainsString('name="REQUEST_TOKEN" value="rt"', $html);
        $this->assertSame(hash('sha256', 'alt'), $this->hash());
    }

    public function testPostErzeugtTokenLeitetWeiterUndZeigtEsEinmal(): void
    {
        $location = $this->post(['FORM_SUBMIT' => 'jetro_token']);
        $this->assertSame('/contao?do=jetro&key=token&id=7', $location);

        // Nach der Weiterleitung (Turbo in Contao 5.7 verlangt sie) erscheint das Token genau einmal
        $html = $this->action('GET');
        $this->assertMatchesRegularExpression('/value="(jt_[0-9a-f]{64})"/', $html);
        preg_match('/value="(jt_[0-9a-f]{64})"/', $html, $m);
        $this->assertSame(hash('sha256', $m[1]), $this->hash());
        $this->assertStringNotContainsString($m[1], json_encode($this->db->fetchAllAssociative('SELECT * FROM tl_jetro_connection'), JSON_THROW_ON_ERROR));

        $this->assertStringNotContainsString($m[1], $this->action('GET'));
        $this->assertNull($this->session->get('jetro_token_result'));
    }

    public function testTokenErscheintNichtBeiAndererVerbindungOderAktion(): void
    {
        $this->post(['FORM_SUBMIT' => 'jetro_token']);

        $this->assertStringNotContainsString('jt_', $this->action('GET', [], 'revoke'));
        $this->assertStringNotContainsString('jt_', $this->action('GET'));
    }

    public function testAltesErgebnisAusDerSitzungVerfaellt(): void
    {
        $this->session->set('jetro_token_result', ['id' => 7, 'action' => 'token', 'token' => 'jt_alt', 'time' => time() - 301]);

        $this->assertStringNotContainsString('jt_alt', $this->action('GET'));
    }

    public function testPostOhneFormularkennungAendertNichts(): void
    {
        $this->action('POST', ['FORM_SUBMIT' => 'anderes']);

        $this->assertSame(hash('sha256', 'alt'), $this->hash());
    }

    public function testWiderrufLeertDenHashUndBestaetigtNachDerWeiterleitung(): void
    {
        $this->post(['FORM_SUBMIT' => 'jetro_revoke'], 'revoke');

        $this->assertSame('', $this->hash());
        $this->assertStringContainsString('tl_confirm', $this->action('GET', [], 'revoke'));
        $this->assertStringNotContainsString('tl_confirm', $this->action('GET', [], 'revoke'));
    }

    /**
     * @param array<string, string> $post
     */
    private function post(array $post, string $action = 'generate'): string
    {
        try {
            $this->action('POST', $post, $action);
        } catch (RedirectResponseException $e) {
            return (string) $e->getResponse()->headers->get('Location');
        }

        $this->fail('POST muss weiterleiten');
    }

    /**
     * @param array<string, string> $post
     */
    private function action(string $method, array $post = [], string $action = 'generate'): string
    {
        while ($this->stack->pop()) {
        }

        $request = Request::create('/contao?do=jetro&key='.('revoke' === $action ? 'revoke' : 'token').'&id=7', $method, $post);
        $request->setSession($this->session);
        $this->stack->push($request);

        $csrf = $this->createMock(ContaoCsrfTokenManager::class);
        $csrf->method('getDefaultTokenValue')->willReturn('rt');
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/contao?do=jetro');

        $translator = new class() implements TranslatorInterface {
            use TranslatorTrait;
        };

        return (new TokenAction($this->db, $this->stack, $router, $translator, $csrf))->{$action}();
    }

    private function hash(): string
    {
        return (string) $this->db->fetchOne('SELECT tokenHash FROM tl_jetro_connection WHERE id = 7');
    }
}
