<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\ApiTestTrait;
use App\Tests\Support\HttpMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FilesTest extends WebTestCase
{
    use ApiTestTrait {
        setUp as private apiSetUp;
    }

    protected function setUp(): void
    {
        $this->apiSetUp();
        HttpMock::reset();
    }

    /** @return array<string, mixed> */
    private function upload(string $authorization, string $name, string $content, ?string $folder = null, array $headers = []): array
    {
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, $content);
        $server = ['HTTP_AUTHORIZATION' => $authorization, 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $header => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $header))] = $value;
        }
        $this->client->request('POST', '/api/files', null === $folder ? [] : ['folder' => $folder], ['file' => new UploadedFile($path, $name, test: true)], $server);

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    private function jwt(User $user): string
    {
        return 'Bearer '.$this->jwtFor($user);
    }

    public function testFoldersAndFilesArePrivate(): void
    {
        $alice = $this->createUser('alice@example.org');
        $bob = $this->createUser('bob@example.org');
        $admin = $this->createUser('admin@example.org', ['ROLE_ADMIN']);

        $folder = $this->api('POST', '/api/folders', ['name' => 'Projets'], $this->jwt($alice));
        $this->assertStatus(201);
        $sub = $this->api('POST', '/api/folders', ['name' => 'Devis', 'parent' => '/api/folders/'.$folder['id']], $this->jwt($alice));
        $this->assertStatus(201);
        self::assertSame(['Projets', 'Devis'], array_column($sub['path'], 'name'));

        $file = $this->upload($this->jwt($alice), 'devis.txt', 'Devis n°42', $sub['id']);
        $this->assertStatus(201);
        self::assertSame('devis.txt', $file['name']);
        self::assertSame(\strlen('Devis n°42'), $file['size']);
        self::assertSame('text/plain', $file['mimeType']);
        self::assertSame(hash('sha256', 'Devis n°42'), $file['sha256']);

        $this->client->request('GET', '/api/files/'.$file['id'].'/content', server: ['HTTP_AUTHORIZATION' => $this->jwt($alice)]);
        $this->assertStatus(200);
        self::assertSame('Devis n°42', $this->client->getInternalResponse()->getContent());
        self::assertStringContainsString('inline', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $listed = $this->api('GET', '/api/files?folder='.$sub['id'], authorization: $this->jwt($alice));
        self::assertSame(['devis.txt'], array_column($listed, 'name'));
        self::assertSame(['Projets'], array_column($this->api('GET', '/api/folders?exists[parent]=false', authorization: $this->jwt($alice)), 'name'));

        // Nobody else sees it, administrators included.
        foreach ([$bob, $admin] as $other) {
            self::assertSame([], $this->api('GET', '/api/files', authorization: $this->jwt($other)));
            self::assertSame([], $this->api('GET', '/api/folders', authorization: $this->jwt($other)));
            $this->api('GET', '/api/files/'.$file['id'], authorization: $this->jwt($other));
            $this->assertStatus(403);
            $this->client->request('GET', '/api/files/'.$file['id'].'/content', server: ['HTTP_AUTHORIZATION' => $this->jwt($other)]);
            $this->assertStatus(404);
        }
        // Nor writes into it.
        $this->api('POST', '/api/folders', ['name' => 'Intrus', 'parent' => '/api/folders/'.$folder['id']], $this->jwt($bob));
        $this->assertStatus(422);
        $this->upload($this->jwt($bob), 'x.txt', 'x', $folder['id']);
        $this->assertStatus(422);
        $this->api('PATCH', '/api/files/'.$file['id'], ['name' => 'pwned.txt'], $this->jwt($bob));
        $this->assertStatus(403);
    }

    public function testRenameMoveAndDeleteWithContent(): void
    {
        $alice = $this->createUser('alice@example.org');
        $a = $this->api('POST', '/api/folders', ['name' => 'A'], $this->jwt($alice));
        $b = $this->api('POST', '/api/folders', ['name' => 'B', 'parent' => '/api/folders/'.$a['id']], $this->jwt($alice));
        $file = $this->upload($this->jwt($alice), 'note.txt', 'hello', $b['id']);

        $moved = $this->api('PATCH', '/api/files/'.$file['id'], ['name' => 'note-finale.txt', 'folder' => null], $this->jwt($alice));
        $this->assertStatus(200);
        self::assertSame('note-finale.txt', $moved['name']);
        self::assertNull($moved['folder']);

        // A folder cannot go into its own subfolder.
        $this->api('PATCH', '/api/folders/'.$a['id'], ['parent' => '/api/folders/'.$b['id']], $this->jwt($alice));
        $this->assertStatus(422);

        $other = $this->upload($this->jwt($alice), 'deep.txt', 'deep', $b['id']);
        $path = static::getContainer()->getParameter('kernel.project_dir').'/var/test-data/files/'.substr($other['id'], 0, 2).'/'.$other['id'];
        self::assertFileExists($path);
        $this->api('DELETE', '/api/folders/'.$a['id'], authorization: $this->jwt($alice));
        $this->assertStatus(204);
        self::assertFileDoesNotExist($path);
        self::assertSame(['note-finale.txt'], array_column($this->api('GET', '/api/files', authorization: $this->jwt($alice)), 'name'));
    }

    public function testQuotaAndMaximumSize(): void
    {
        $alice = $this->createUser('alice@example.org');
        // .env.test: 600 bytes per file, 1000 per user.
        $this->upload($this->jwt($alice), 'big.bin', str_repeat('x', 601));
        $this->assertStatus(413);
        $this->upload($this->jwt($alice), 'a.bin', str_repeat('x', 500));
        $this->assertStatus(201);
        $this->upload($this->jwt($alice), 'b.bin', str_repeat('x', 501));
        $this->assertStatus(413);

        $usage = $this->api('GET', '/api/files/usage', authorization: $this->jwt($alice));
        self::assertSame(['used' => 500, 'quota' => 1000, 'maxFileSize' => 600, 'files' => 1], $usage);
    }

    public function testApplicationsStoreFilesForAUser(): void
    {
        $this->createUser('alice@example.org');
        [, $token] = $this->createApplication();

        $file = $this->upload('Bearer '.$token, 'facture.pdf', '%PDF-1.4 demo', headers: ['X-Impersonate-User' => 'alice@example.org']);
        $this->assertStatus(201);
        self::assertSame('facture.pdf', $file['name']);

        // Without impersonation, an application reaches nothing.
        $this->upload('Bearer '.$token, 'x.txt', 'x');
        $this->assertStatus(403);
    }

    public function testShareLinksWithPasswordLimitsAndNotification(): void
    {
        $alice = $this->createUser('alice@example.org')->setFirstName('Alice')->setLastName('Durand');
        $this->em()->flush();
        $file = $this->upload($this->jwt($alice), 'tarifs.csv', "a;b\n1;2\n");

        $sent = [];
        HttpMock::on('http://mailer.test/api/emails', function (string $method, string $url, array $options) use (&$sent) {
            $sent[] = $options;

            return new MockResponse(json_encode(['id' => 'e1', 'status' => 'queued']), ['http_code' => 202, 'response_headers' => ['content-type' => 'application/json']]);
        });

        $share = $this->api('POST', '/api/shares', [
            'file' => '/api/files/'.$file['id'],
            'password' => 'secret-pass',
            'maxDownloads' => 1,
            'recipients' => ['Client@Example.com'],
            'message' => 'Nos tarifs.',
        ], $this->jwt($alice));
        $this->assertStatus(201);
        self::assertTrue($share['passwordProtected']);
        self::assertArrayNotHasKey('password', $share);

        // Notified through Rocket Mailer, as Alice (worker: synchronous in tests).
        self::assertCount(1, $sent);
        self::assertContains('X-Impersonate-User: alice@example.org', $sent[0]['headers']);
        self::assertContains('Authorization: Bearer rma_test_token_for_rocket_cloud_notifications', $sent[0]['headers']);
        $email = json_decode($sent[0]['body'], true);
        self::assertSame(['client@example.com'], $email['to']);
        self::assertSame('Alice Durand vous a partagé « tarifs.csv »', $email['subject']);
        self::assertStringContainsString('http://localhost:3200/s/'.$share['token'], $email['htmlBody']);
        self::assertSame('sent', $this->api('GET', '/api/shares/'.$share['id'], authorization: $this->jwt($alice))['notificationStatus']);

        // Public side.
        $public = $this->api('GET', '/api/public/shares/'.$share['token']);
        $this->assertStatus(200);
        self::assertSame('tarifs.csv', $public['name']);
        self::assertSame('Alice Durand', $public['sharedBy']);
        self::assertTrue($public['passwordProtected']);
        self::assertArrayNotHasKey('files', $public);

        $this->client->request('POST', '/api/public/shares/'.$share['token'].'/download', ['password' => 'wrong']);
        $this->assertStatus(403);
        $this->client->request('POST', '/api/public/shares/'.$share['token'].'/download', ['password' => 'secret-pass']);
        $this->assertStatus(200);
        self::assertSame("a;b\n1;2\n", $this->client->getInternalResponse()->getContent());
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        // One download allowed.
        $this->client->request('POST', '/api/public/shares/'.$share['token'].'/download', ['password' => 'secret-pass']);
        $this->assertStatus(410);

        $this->api('GET', '/api/public/shares/unknown-token');
        $this->assertStatus(404);
    }

    public function testSharedFoldersAndRevocation(): void
    {
        $alice = $this->createUser('alice@example.org');
        $bob = $this->createUser('bob@example.org');
        $folder = $this->api('POST', '/api/folders', ['name' => 'Photos'], $this->jwt($alice));
        $one = $this->upload($this->jwt($alice), 'un.txt', '1', $folder['id']);
        $this->upload($this->jwt($alice), 'deux.txt', '2', $folder['id']);

        // Someone else's folder cannot be shared.
        $this->api('POST', '/api/shares', ['folder' => '/api/folders/'.$folder['id']], $this->jwt($bob));
        $this->assertStatus(422);
        $this->api('POST', '/api/shares', ['file' => '/api/files/'.$one['id'], 'folder' => '/api/folders/'.$folder['id']], $this->jwt($alice));
        $this->assertStatus(422);

        $share = $this->api('POST', '/api/shares', ['folder' => '/api/folders/'.$folder['id'], 'expiresAt' => (new \DateTimeImmutable('+1 day'))->format(\DATE_ATOM)], $this->jwt($alice));
        $this->assertStatus(201);
        self::assertNull($share['notificationStatus']);
        $public = $this->api('GET', '/api/public/shares/'.$share['token']);
        self::assertSame('folder', $public['type']);
        self::assertEqualsCanonicalizing(['un.txt', 'deux.txt'], array_column($public['files'], 'name'));

        $this->client->request('POST', '/api/public/shares/'.$share['token'].'/download', ['file' => $one['id']]);
        $this->assertStatus(200);
        self::assertSame('1', $this->client->getInternalResponse()->getContent());

        self::assertSame([], $this->api('GET', '/api/shares', authorization: $this->jwt($bob)));
        $this->api('DELETE', '/api/shares/'.$share['id'], authorization: $this->jwt($bob));
        $this->assertStatus(403);
        $this->api('DELETE', '/api/shares/'.$share['id'], authorization: $this->jwt($alice));
        $this->assertStatus(204);
        $this->api('GET', '/api/public/shares/'.$share['token']);
        $this->assertStatus(404);
    }

    public function testMailerIsHealthChecked(): void
    {
        HttpMock::json('http://mailer.test/api/me', ['user' => null, 'application' => ['name' => 'Rocket Cloud'], 'roles' => []]);
        $admin = $this->createUser('admin@example.org', ['ROLE_ADMIN']);
        $health = $this->api('POST', '/api/health/check', authorization: $this->jwt($admin));
        $mailer = array_values(array_filter($health['services'], static fn (array $s) => 'mailer' === $s['id']))[0];
        self::assertSame('operational', $mailer['status']);
        self::assertStringContainsString('Rocket Cloud', $mailer['detail']);

        $dashboard = $this->api('GET', '/api/dashboard', authorization: $this->jwt($admin));
        self::assertContains('storage', array_column($dashboard['kpis'], 'id'));
    }
}
