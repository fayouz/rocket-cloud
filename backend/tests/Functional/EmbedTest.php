<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The embedded file picker: an embed session browses and reads the files of its user, and nothing else
 * (embed tokens: see rocket-core).
 */
final class EmbedTest extends WebTestCase
{
    use ApiTestTrait;

    private function embedToken(string $appToken, string $user = 'alice@example.org'): string
    {
        $response = $this->api('POST', '/api/embed/token', authorization: 'Bearer '.$appToken, headers: ['X-Impersonate-User' => $user]);
        $this->assertStatus(201);

        return $response['token'];
    }

    public function testEmbedSessionBrowsesAndReadsFiles(): void
    {
        $alice = $this->createUser('alice@example.org');
        $folder = $this->api('POST', '/api/folders', ['name' => 'Modèles'], 'Bearer '.$this->jwtFor($alice));
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, 'Bonjour {{nom}}');
        $this->client->request('POST', '/api/files', ['folder' => $folder['id']], ['file' => new UploadedFile($path, 'lettre.txt', test: true)], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->jwtFor($alice), 'HTTP_ACCEPT' => 'application/json']);
        $file = json_decode((string) $this->client->getResponse()->getContent(), true);
        [, $appToken] = $this->createApplication();
        $embed = 'Embed '.$this->embedToken($appToken);

        self::assertSame(['Modèles'], array_column($this->api('GET', '/api/folders?exists[parent]=false', authorization: $embed), 'name'));
        $this->assertStatus(200);
        $this->api('GET', '/api/folders/'.$folder['id'], authorization: $embed);
        $this->assertStatus(200);
        self::assertSame(['lettre.txt'], array_column($this->api('GET', '/api/files?folder='.$folder['id'].'&mimeType=text/', authorization: $embed), 'name'));
        $this->api('GET', '/api/files/'.$file['id'], authorization: $embed);
        $this->assertStatus(200);
        $this->api('GET', '/api/files/usage', authorization: $embed);
        $this->assertStatus(200);
        $this->client->request('GET', '/api/files/'.$file['id'].'/content', server: ['HTTP_AUTHORIZATION' => $embed]);
        $this->assertStatus(200);
        self::assertSame('Bonjour {{nom}}', $this->client->getInternalResponse()->getContent());
    }

    public function testEmbedSessionChangesNothing(): void
    {
        $alice = $this->createUser('alice@example.org');
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, 'x');
        $this->client->request('POST', '/api/files', [], ['file' => new UploadedFile($path, 'x.txt', test: true)], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->jwtFor($alice), 'HTTP_ACCEPT' => 'application/json']);
        $file = json_decode((string) $this->client->getResponse()->getContent(), true);
        [, $appToken] = $this->createApplication();
        $embed = 'Embed '.$this->embedToken($appToken);

        $this->api('POST', '/api/folders', ['name' => 'x'], $embed);
        $this->assertStatus(403);
        $this->api('PATCH', '/api/files/'.$file['id'], ['name' => 'y.txt'], $embed);
        $this->assertStatus(403);
        $this->api('DELETE', '/api/files/'.$file['id'], authorization: $embed);
        $this->assertStatus(403);
        $this->client->request('PUT', '/api/files/'.$file['id'].'/content', server: ['HTTP_AUTHORIZATION' => $embed], content: 'y');
        $this->assertStatus(403);
        $this->api('POST', '/api/shares', ['file' => '/api/files/'.$file['id']], $embed);
        $this->assertStatus(403);
        $this->api('GET', '/api/shares', authorization: $embed);
        $this->assertStatus(403);
    }
}
