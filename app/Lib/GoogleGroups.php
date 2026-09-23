<?php

namespace App\Lib;

use Google\Client;
use Google\Service\Directory;
use Google\Service\Directory\Member;
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\ClientInterface;
use RuntimeException;

/**
 * Maintain Google Groups (mailing lists) memberships in the burningman.org Google Workspace
 * via the Admin SDK Directory API. Requires a service account with domain-wide delegation
 * for the admin.directory.group.member scope.
 */

class GoogleGroups
{
    const string ALLCOM = 'ranger-allcom@burningman.org';
    const string ANNOUNCE = 'rangers-announce@burningman.org';

    const string SCOPE = 'https://www.googleapis.com/auth/admin.directory.group.member';

    // Result statuses
    const string ADDED = 'added';
    const string ALREADY_MEMBER = 'already a member';
    const string REMOVED = 'removed';
    const string NOT_FOUND = 'not a member';

    protected ?Directory $directory = null;
    protected ?ClientInterface $httpClient = null;

    /**
     * Use a specific HTTP client for the Google API requests (used by the tests to mock Google's responses).
     *
     * @param ClientInterface $httpClient
     * @return void
     */

    public function setHttpClient(ClientInterface $httpClient): void
    {
        $this->httpClient = $httpClient;
        $this->directory = null;
    }

    /**
     * Is the email address a Ranger Google Group the Clubhouse is allowed to manage?
     *
     * @param string|null $email
     * @return bool
     */

    public static function isManagedGroup(?string $email): bool
    {
        return !empty($email) && preg_match('/^rangers?-[^@\s]+@burningman\.org$/i', trim($email)) === 1;
    }

    /**
     * Add an email address to a group.
     *
     * @param string $group
     * @param string $email
     * @return string status
     * @throws GoogleServiceException
     */

    public function addMember(string $group, string $email): string
    {
        self::assertManagedGroup($group);

        $member = new Member();
        $member->setEmail($email);
        $member->setRole('MEMBER');

        try {
            $this->directory()->members->insert($group, $member);
        } catch (GoogleServiceException $e) {
            if ($e->getCode() == 409) {
                return self::ALREADY_MEMBER;
            }
            throw $e;
        }

        return self::ADDED;
    }

    /**
     * Remove an email address from a group.
     *
     * @param string $group
     * @param string $email
     * @return string status
     * @throws GoogleServiceException
     */

    public function removeMember(string $group, string $email): string
    {
        self::assertManagedGroup($group);

        try {
            $this->directory()->members->delete($group, $email);
        } catch (GoogleServiceException $e) {
            // Google answers 404 both when the person isn't in the group ("Resource Not Found: memberKey")
            // and when the group itself doesn't exist ("Resource Not Found: groupKey"). Only the first is fine.
            if ($e->getCode() == 404 && str_contains($e->getMessage(), 'memberKey')) {
                return self::NOT_FOUND;
            }
            throw $e;
        }

        return self::REMOVED;
    }

    protected static function assertManagedGroup(string $group): void
    {
        if (!self::isManagedGroup($group)) {
            throw new RuntimeException("{$group} is not a managed Ranger Google Group");
        }
    }

    /**
     * Build the Directory service on first use.
     *
     * @return Directory
     */

    protected function directory(): Directory
    {
        if ($this->directory) {
            return $this->directory;
        }

        $settings = setting(['GoogleGroupsServiceAccountJson', 'GoogleGroupsAdminEmail'], true);
        $credentials = json_decode($settings['GoogleGroupsServiceAccountJson'], true);
        if (!is_array($credentials)) {
            throw new RuntimeException('GoogleGroupsServiceAccountJson is not valid JSON');
        }

        $client = new Client();
        $client->setApplicationName('Ranger Clubhouse');
        $client->setAuthConfig($credentials);
        $client->setScopes([self::SCOPE]);
        $client->setSubject($settings['GoogleGroupsAdminEmail']);
        if ($this->httpClient) {
            $client->setHttpClient($this->httpClient);
        }

        return $this->directory = new Directory($client);
    }
}
