<?php

namespace Tests\Feature;

use App\Lib\GoogleGroups;
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * Exercise GoogleGroups against mocked Google HTTP responses. Runs the real code path: settings,
 * service account credentials, the OAuth token exchange, and the Directory API requests.
 */

class GoogleGroupsTest extends TestCase
{
    use RefreshDatabase;

    const string GROUP = 'ranger-greendot@burningman.org';
    const string MEMBER = 'hubcap@example.com';
    const string ADMIN = 'clubhouse-admin@burningman.org';
    const string MEMBERS_PATH = '/admin/directory/v1/groups/ranger-greendot@burningman.org/members';

    private array $history = [];

    public function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);

        $this->setting('GoogleGroupsServiceAccountJson', json_encode([
            'type' => 'service_account',
            'project_id' => 'clubhouse-test',
            'private_key_id' => 'test-key-id',
            'private_key' => $privateKey,
            'client_email' => 'clubhouse@clubhouse-test.iam.gserviceaccount.com',
            'client_id' => '1234567890',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        $this->setting('GoogleGroupsAdminEmail', self::ADMIN);
    }

    /**
     * Build a GoogleGroups whose HTTP requests are answered by the given API response,
     * after a successful OAuth token exchange.
     */

    private function groupsWithResponse(Response $apiResponse): GoogleGroups
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ])),
            $apiResponse,
        ]);
        // Record requests right before the mock handler, so they include what Google's auth middleware added.
        $stack = HandlerStack::create(Middleware::history($this->history)($mock));

        $groups = new GoogleGroups();
        $groups->setHttpClient(new Client(['handler' => $stack]));
        return $groups;
    }

    private function googleError(int $code, string $message): Response
    {
        return new Response($code, ['Content-Type' => 'application/json'], json_encode([
            'error' => [
                'code' => $code,
                'message' => $message,
                'errors' => [['message' => $message, 'domain' => 'global', 'reason' => $code == 409 ? 'duplicate' : 'notFound']],
            ]
        ]));
    }

    private function request(int $index): RequestInterface
    {
        $this->assertArrayHasKey($index, $this->history, "Expected HTTP request #{$index}");
        return $this->history[$index]['request'];
    }

    /**
     * The first request exchanges a signed JWT (impersonating the admin, scoped to group membership) for a token.
     */

    private function assertTokenExchange(): void
    {
        $token = $this->request(0);
        $this->assertEquals('POST', $token->getMethod());
        $this->assertEquals('oauth2.googleapis.com', $token->getUri()->getHost());

        parse_str((string)$token->getBody(), $form);
        $this->assertEquals('urn:ietf:params:oauth:grant-type:jwt-bearer', $form['grant_type']);

        $claims = json_decode(base64_decode(strtr(explode('.', $form['assertion'])[1], '-_', '+/')), true);
        $this->assertEquals(self::ADMIN, $claims['sub'], 'Must impersonate the Workspace admin');
        $this->assertEquals(GoogleGroups::SCOPE, $claims['scope']);
        $this->assertEquals('clubhouse@clubhouse-test.iam.gserviceaccount.com', $claims['iss']);
    }

    private function assertApiRequest(string $method, string $path): RequestInterface
    {
        $api = $this->request(1);
        $this->assertEquals($method, $api->getMethod());
        $this->assertEquals('admin.googleapis.com', $api->getUri()->getHost());
        $this->assertEquals($path, urldecode($api->getUri()->getPath()));
        $this->assertEquals('Bearer test-access-token', $api->getHeaderLine('Authorization'));
        return $api;
    }

    public function testAddMember(): void
    {
        $groups = $this->groupsWithResponse(new Response(200, ['Content-Type' => 'application/json'],
            json_encode(['kind' => 'admin#directory#member', 'email' => self::MEMBER, 'role' => 'MEMBER'])));

        $this->assertEquals(GoogleGroups::ADDED, $groups->addMember(self::GROUP, self::MEMBER));

        $this->assertTokenExchange();
        $api = $this->assertApiRequest('POST', self::MEMBERS_PATH);
        $body = json_decode((string)$api->getBody(), true);
        $this->assertEquals(self::MEMBER, $body['email']);
        $this->assertEquals('MEMBER', $body['role']);
    }

    public function testAddExistingMemberIsSuccess(): void
    {
        $groups = $this->groupsWithResponse($this->googleError(409, 'Member already exists.'));

        $this->assertEquals(GoogleGroups::ALREADY_MEMBER, $groups->addMember(self::GROUP, self::MEMBER));
        $this->assertApiRequest('POST', self::MEMBERS_PATH);
    }

    public function testRemoveMember(): void
    {
        $groups = $this->groupsWithResponse(new Response(204));

        $this->assertEquals(GoogleGroups::REMOVED, $groups->removeMember(self::GROUP, self::MEMBER));

        $this->assertTokenExchange();
        $this->assertApiRequest('DELETE', self::MEMBERS_PATH . '/' . self::MEMBER);
    }

    public function testRemoveNonMemberIsSuccess(): void
    {
        $groups = $this->groupsWithResponse($this->googleError(404, 'Resource Not Found: memberKey'));

        $this->assertEquals(GoogleGroups::NOT_FOUND, $groups->removeMember(self::GROUP, self::MEMBER));
        $this->assertApiRequest('DELETE', self::MEMBERS_PATH . '/' . self::MEMBER);
    }

    /**
     * A 404 because the group doesn't exist (e.g., a typo in the team's email) is a failure, not "not a member".
     */

    public function testRemoveFromMissingGroupIsFailure(): void
    {
        $groups = $this->groupsWithResponse($this->googleError(404, 'Resource Not Found: groupKey'));

        $this->expectException(GoogleServiceException::class);
        $groups->removeMember(self::GROUP, self::MEMBER);
    }

    public function testAddToMissingGroupIsFailure(): void
    {
        $groups = $this->groupsWithResponse($this->googleError(404, 'Resource Not Found: groupKey'));

        $this->expectException(GoogleServiceException::class);
        $groups->addMember(self::GROUP, self::MEMBER);
    }

    /**
     * Other errors (e.g., domain-wide delegation not granted) are thrown so the job reports a failure.
     */

    public function testOtherErrorsAreThrown(): void
    {
        $groups = $this->groupsWithResponse($this->googleError(403, 'Not Authorized to access this resource/api'));

        $this->expectException(GoogleServiceException::class);
        $groups->addMember(self::GROUP, self::MEMBER);
    }

    public function testRefusesUnmanagedGroup(): void
    {
        $groups = $this->groupsWithResponse(new Response(200));

        try {
            $groups->addMember('rangers@burningman.org', self::MEMBER);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('not a managed Ranger Google Group', $e->getMessage());
        }

        $this->assertEmpty($this->history, 'No request should be made for an unmanaged group');
    }

    public function testInvalidCredentialsJson(): void
    {
        $this->setting('GoogleGroupsServiceAccountJson', 'not json');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GoogleGroupsServiceAccountJson is not valid JSON');
        (new GoogleGroups())->addMember(self::GROUP, self::MEMBER);
    }

    public function testMissingSettingsThrow(): void
    {
        $this->setting('GoogleGroupsAdminEmail', '');

        $this->expectException(RuntimeException::class);
        (new GoogleGroups())->addMember(self::GROUP, self::MEMBER);
    }
}
