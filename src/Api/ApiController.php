<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Api;

use JetroTools\ContaoBundle\Storage\ConnectionRepository;
use JetroTools\ContaoBundle\Storage\MediaRepository;
use JetroTools\ContaoBundle\Storage\RequestLog;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vertrag Jetro → Paket (api 1). Ohne Contao-Scope: keine Session, kein Cookie, kein CSRF (V7h).
 */
#[AsController]
final class ApiController
{
    public const API = 1;

    private const MAX_REQUEST_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly ConnectionRepository $connections,
        private readonly EventWriter $events,
        private readonly MediaRepository $media,
        private readonly PayloadValidator $validator,
        private readonly RequestLog $log,
        private readonly LoggerInterface $logger,
        private readonly string $version,
    ) {
    }

    #[Route('/api/jetro/{path}', name: 'jetro_contao_api', requirements: ['path' => '.*'], defaults: ['path' => ''], priority: 10)]
    public function __invoke(Request $request, string $path): Response
    {
        if (!preg_match('#^v1(/(?<rest>.*))?$#', $path, $m)) {
            return $this->error(ApiException::notFound());
        }

        return $this->handle($request, $m['rest'] ?? '');
    }

    public function handle(Request $request, string $path): Response
    {
        $connection = null;

        try {
            $connection = $this->authenticate($request);
            [$status, $body] = $this->dispatch($request, $connection, $path);
            $response = null === $body ? new Response('', $status) : new JsonResponse($body, $status);
            $code = '';
        } catch (ApiException $e) {
            $response = $this->error($e);
            $code = $e->errorCode;
        } catch (\Throwable $e) {
            $this->logger->error('Jetro-API: '.$e::class.' in '.$e->getFile().':'.$e->getLine());
            $response = $this->error(new ApiException(500, 'internal', 'Interner Fehler.'));
            $code = 'internal';
        }

        try {
            $this->log->add((int) ($connection['id'] ?? 0), $request->getMethod(), $request->getPathInfo(), $response->getStatusCode(), $code);
        } catch (\Throwable) {
            // Protokoll darf die Antwort nie verhindern
        }

        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function authenticate(Request $request): array
    {
        // Beide Köpfe lesen: Apache/FPM streicht Authorization oft (Vertrag)
        $token = (string) $request->headers->get('X-Jetro-Token', '');

        if ('' === $token && preg_match('/^Bearer\s+(\S+)$/i', (string) $request->headers->get('Authorization', ''), $m)) {
            $token = $m[1];
        }

        $connection = '' === $token ? null : $this->connections->findByToken($token);

        if (null === $connection) {
            throw new ApiException(401, 'auth_rejected', 'Token fehlt oder ist ungültig.');
        }

        return $connection;
    }

    /**
     * @param array<string, mixed> $connection
     *
     * @return array{int, array<string, mixed>|null}
     */
    private function dispatch(Request $request, array $connection, string $path): array
    {
        $method = $request->getMethod();

        if ('' === $path && 'GET' === $method) {
            return [200, ['name' => 'jetro-contao', 'version' => $this->version, 'api' => self::API]];
        }

        if (!preg_match('#^(?<type>events|media)/(?<id>'.PayloadValidator::UUID.')$#', $path, $m)) {
            throw ApiException::notFound();
        }

        $id = strtolower($m['id']);

        return match ([$m['type'], $method]) {
            ['events', 'PUT'] => [$this->events->put($connection, $id, $this->validator->event($this->body($request))) ? 201 : 200, ['id' => $id]],
            ['events', 'PATCH'] => (function () use ($connection, $id, $request) {
                $this->events->cancel($connection, $id, $this->validator->cancelled($this->body($request)));

                return [200, ['id' => $id]];
            })(),
            ['events', 'DELETE'] => (function () use ($connection, $id) {
                $this->events->delete($connection, $id);

                return [204, null];
            })(),
            ['media', 'PUT'] => [$this->media->put((int) $connection['id'], $id, $this->mediaContent($this->body($request))) ? 201 : 200, ['id' => $id]],
            default => throw ApiException::notFound(),
        };
    }

    /**
     * @return array<mixed>
     */
    private function body(Request $request): array
    {
        $content = (string) $request->getContent();

        if (\strlen($content) > self::MAX_REQUEST_BYTES) {
            throw new ApiException(413, 'file_too_large', 'Die Anfrage ist größer als 4 MB.');
        }

        try {
            $data = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(400, 'invalid_json', 'Der Inhalt ist kein gültiges JSON.');
        }

        if (!\is_array($data) || array_is_list($data) && [] !== $data) {
            throw new ApiException(400, 'invalid_json', 'Erwartet wird ein JSON-Objekt.');
        }

        return $data;
    }

    /**
     * @param array<mixed> $data
     */
    private function mediaContent(array $data): string
    {
        $base64 = $data['contentBase64'] ?? null;
        $content = \is_string($base64) ? base64_decode($base64, true) : false;

        if (false === $content || '' === $content) {
            throw ApiException::validation('contentBase64');
        }

        return $content;
    }

    private function error(ApiException $e): JsonResponse
    {
        $body = ['code' => $e->errorCode, 'message' => $e->getMessage()];

        if (null !== $e->field) {
            $body['field'] = $e->field;
        }

        return new JsonResponse($body, $e->status);
    }
}
