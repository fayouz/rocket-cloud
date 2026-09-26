<?php

namespace App\Tests\Unit;

use App\Share\MailerClient;
use PHPUnit\Framework\TestCase;
use Rocket\Core\Suite\ServiceTokenProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** How Rocket Cloud authenticates to Rocket Mailer: token of Rocket Auth in suite mode, static token otherwise. */
final class MailerClientTest extends TestCase
{
    private const STATIC = 'rma_test_token_for_rocket_cloud_notifications';

    /** @var list<array{method: string, url: string, authorization: string, impersonate: ?string}> */
    private array $requests = [];

    public function testStandaloneUsesTheStaticToken(): void
    {
        $mailer = $this->mailer([$this->created()], suite: false);

        self::assertTrue($mailer->isConfigured());
        self::assertSame('token', $mailer->mode());
        self::assertSame('email-1', $mailer->send('alice@example.org', ['bob@example.org'], 'Partage', '<p>…</p>'));
        self::assertSame('POST http://mailer.test/api/emails', $this->requests[0]['method'].' '.$this->requests[0]['url']);
        self::assertSame('Bearer '.self::STATIC, $this->requests[0]['authorization']);
        self::assertSame('alice@example.org', $this->requests[0]['impersonate']);
    }

    public function testSuiteModeUsesATokenOfRocketAuthForRocketMailer(): void
    {
        $tokens = $this->createMock(ServiceTokenProvider::class);
        $tokens->method('isAvailable')->willReturn(true);
        $tokens->expects(self::once())->method('tokenForClient')->with('rocket-mailer')->willReturn('suite-access-token');
        $mailer = $this->mailer([$this->created()], tokens: $tokens, staticToken: '');

        self::assertTrue($mailer->isConfigured());
        self::assertSame('suite', $mailer->mode());
        $mailer->send('alice@example.org', ['bob@example.org'], 'Partage', '<p>…</p>');
        self::assertSame('Bearer suite-access-token', $this->requests[0]['authorization']);
        self::assertSame('alice@example.org', $this->requests[0]['impersonate']);
    }

    public function testARefusedSuiteTokenFallsBackToTheStaticToken(): void
    {
        $tokens = $this->createMock(ServiceTokenProvider::class);
        $tokens->method('isAvailable')->willReturn(true);
        $tokens->method('tokenForClient')->willReturn('suite-access-token');
        $tokens->expects(self::once())->method('forget')->with('rocket-mailer');
        $unauthorized = new MockResponse('{"message":"not linked"}', ['http_code' => 401]);
        $mailer = $this->mailer([$unauthorized, $this->created()], tokens: $tokens);

        self::assertSame('email-1', $mailer->send('alice@example.org', ['bob@example.org'], 'Partage', '<p>…</p>'));
        self::assertSame(['Bearer suite-access-token', 'Bearer '.self::STATIC], array_column($this->requests, 'authorization'));
    }

    public function testWithoutSuiteTokenNorStaticTokenNothingIsSent(): void
    {
        $mailer = $this->mailer([], suite: false, staticToken: '');
        self::assertFalse($mailer->isConfigured());
    }

    public function testRefusalsAreReported(): void
    {
        $mailer = $this->mailer([new MockResponse('{"detail":"Adresse invalide"}', ['http_code' => 422])], suite: false);
        $this->expectExceptionMessage('(HTTP 422) : Adresse invalide');
        $mailer->send('alice@example.org', ['nope'], 'Partage', '<p>…</p>');
    }

    /** @param list<MockResponse> $responses */
    private function mailer(array $responses, bool $suite = true, ?ServiceTokenProvider $tokens = null, string $staticToken = self::STATIC): MailerClient
    {
        if (null === $tokens) {
            $tokens = $this->createStub(ServiceTokenProvider::class);
            $tokens->method('isAvailable')->willReturn($suite);
        }
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $headers = [];
            foreach ($options['headers'] as $header) {
                [$name, $value] = explode(': ', $header, 2);
                $headers[strtolower($name)] = $value;
            }
            $this->requests[] = ['method' => $method, 'url' => $url, 'authorization' => $headers['authorization'] ?? '', 'impersonate' => $headers['x-impersonate-user'] ?? null];

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        });

        return new MailerClient($http, $tokens, 'http://mailer.test/', $staticToken);
    }

    private function created(): MockResponse
    {
        return new MockResponse('{"id":"email-1"}', ['http_code' => 201, 'response_headers' => ['content-type' => 'application/json']]);
    }
}
