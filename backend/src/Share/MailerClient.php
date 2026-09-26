<?php

namespace App\Share;

use Rocket\Core\Suite\ServiceTokenProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Rocket Mailer, the email middleware: Rocket Cloud sends its notifications through it as an external application,
 * on behalf of the user (X-Impersonate-User), so the email leaves from that user.
 *
 * Authentication: in suite mode, an access token of Rocket Auth for Rocket Mailer (client credentials, audience
 * ROCKET_MAILER_AUDIENCE, see Rocket\Core\Suite\ServiceTokenProvider): Rocket Mailer's administrator links an
 * application to the client "rocket-cloud". Otherwise, the static token of that application (ROCKET_MAILER_TOKEN,
 * rma_…), also used when Rocket Mailer does not accept the suite token yet.
 */
class MailerClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ServiceTokenProvider $suiteTokens,
        #[Autowire(env: 'ROCKET_MAILER_URL')] private readonly string $url,
        #[Autowire(env: 'ROCKET_MAILER_TOKEN')] #[\SensitiveParameter] private readonly string $token,
        #[Autowire(env: 'ROCKET_MAILER_AUDIENCE')] private readonly string $audience = 'rocket-mailer',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->url && ('' !== $this->token || $this->suiteTokens->isAvailable());
    }

    public function url(): string
    {
        return rtrim($this->url, '/');
    }

    /** How Rocket Cloud authenticates to Rocket Mailer: "suite" (token of Rocket Auth) or "token" (static token). */
    public function mode(): string
    {
        return $this->suiteTokens->isAvailable() ? 'suite' : 'token';
    }

    /**
     * Queues an email in Rocket Mailer; returns its identifier.
     *
     * @param list<string> $to
     */
    public function send(string $asUser, array $to, string $subject, string $htmlBody): string
    {
        $response = $this->request('POST', '/api/emails', [
            'headers' => ['X-Impersonate-User' => $asUser, 'Accept' => 'application/json'],
            'json' => ['to' => $to, 'subject' => $subject, 'htmlBody' => $htmlBody],
            'timeout' => 15,
        ]);
        $status = $response->getStatusCode();
        $data = $response->toArray(false);
        if ($status >= 300) {
            throw new \RuntimeException(\sprintf('Rocket Mailer a refusé l’email (HTTP %d) : %s', $status, $data['detail'] ?? $data['message'] ?? 'erreur inconnue'));
        }

        return (string) ($data['id'] ?? '');
    }

    /** Health check: the credentials are valid (GET /api/me as the application). */
    public function ping(): string
    {
        $response = $this->request('GET', '/api/me', ['headers' => ['Accept' => 'application/json'], 'timeout' => 5]);
        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(\sprintf('Rocket Mailer répond HTTP %d (%s)', $response->getStatusCode(), 'suite' === $this->mode() ? 'application liée au client Rocket Auth de Rocket Cloud ?' : 'jeton d’application invalide ?'));
        }
        $application = $response->toArray()['application']['name'] ?? '?';

        return \sprintf('%s, application « %s »%s', $this->url(), $application, 'suite' === $this->mode() ? ' (jeton Rocket Auth)' : '');
    }

    /** @param array<string, mixed> $options */
    private function request(string $method, string $path, array $options): ResponseInterface
    {
        if (!$this->suiteTokens->isAvailable()) {
            return $this->httpClient->request($method, $this->url().$path, ['auth_bearer' => $this->token] + $options);
        }

        $response = $this->httpClient->request($method, $this->url().$path, ['auth_bearer' => $this->suiteTokens->tokenForClient($this->audience)] + $options);
        if (401 !== $response->getStatusCode()) {
            return $response;
        }
        // Refused: a token cached too long, or a Rocket Mailer that does not accept the tokens of the suite yet.
        $this->suiteTokens->forget($this->audience);
        $token = '' !== $this->token ? $this->token : $this->suiteTokens->tokenForClient($this->audience);

        return $this->httpClient->request($method, $this->url().$path, ['auth_bearer' => $token] + $options);
    }
}
