<?php

namespace App\Share;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Rocket Mailer, the email middleware: Rocket Cloud sends its notifications through it as an external application
 * (token rma_…), on behalf of the user (X-Impersonate-User), so the email leaves from that user.
 */
class MailerClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'ROCKET_MAILER_URL')] private readonly string $url,
        #[Autowire(env: 'ROCKET_MAILER_TOKEN')] #[\SensitiveParameter] private readonly string $token,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->url && '' !== $this->token;
    }

    public function url(): string
    {
        return rtrim($this->url, '/');
    }

    /**
     * Queues an email in Rocket Mailer; returns its identifier.
     *
     * @param list<string> $to
     */
    public function send(string $asUser, array $to, string $subject, string $htmlBody): string
    {
        $response = $this->httpClient->request('POST', $this->url().'/api/emails', [
            'auth_bearer' => $this->token,
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

    /** Health check: the token is valid (GET /api/me as the application). */
    public function ping(): string
    {
        $response = $this->httpClient->request('GET', $this->url().'/api/me', ['auth_bearer' => $this->token, 'headers' => ['Accept' => 'application/json'], 'timeout' => 5]);
        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(\sprintf('Rocket Mailer répond HTTP %d (jeton d’application invalide ?)', $response->getStatusCode()));
        }
        $application = $response->toArray()['application']['name'] ?? '?';

        return \sprintf('%s, application « %s »', $this->url(), $application);
    }
}
