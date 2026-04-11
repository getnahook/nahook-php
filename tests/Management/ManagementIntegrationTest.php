<?php

declare(strict_types=1);

namespace Nahook\Tests\Management;

use Nahook\NahookManagement;
use Nahook\Errors\NahookAPIError;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Management API hitting a real Nahook API instance.
 *
 * Required env vars:
 *   NAHOOK_TEST_API_URL, NAHOOK_TEST_MGMT_TOKEN, NAHOOK_TEST_WORKSPACE_ID
 *
 * @group integration
 */
final class ManagementIntegrationTest extends TestCase
{
    private NahookManagement $mgmt;
    private string $workspaceId;

    protected function setUp(): void
    {
        $apiUrl = getenv('NAHOOK_TEST_API_URL') ?: '';
        $mgmtToken = getenv('NAHOOK_TEST_MGMT_TOKEN') ?: '';
        $this->workspaceId = getenv('NAHOOK_TEST_WORKSPACE_ID') ?: '';

        if ($apiUrl === '' || $mgmtToken === '' || $this->workspaceId === '') {
            $this->markTestSkipped(
                'Management integration test env vars not set '
                . '(NAHOOK_TEST_API_URL, NAHOOK_TEST_MGMT_TOKEN, NAHOOK_TEST_WORKSPACE_ID)'
            );
        }

        $this->mgmt = new NahookManagement($mgmtToken, ['baseUrl' => $apiUrl]);
    }

    // ---------------------------------------------------------------
    // Event Types CRUD
    // ---------------------------------------------------------------

    public function testEventTypesCrud(): void
    {
        $ts = time();
        $name = "test.mgmt.php.{$ts}";

        // Create
        $created = $this->mgmt->eventTypes->create($this->workspaceId, [
            'name' => $name,
            'description' => "PHP integration test event type {$ts}",
        ]);
        $this->assertArrayHasKey('id', $created);
        $eventTypeId = $created['id'];
        $this->assertStringStartsWith('evt_', $eventTypeId);
        $this->assertSame($name, $created['name']);

        // List
        $listed = $this->mgmt->eventTypes->list($this->workspaceId);
        $this->assertArrayHasKey('data', $listed);
        $this->assertIsArray($listed['data']);
        $ids = array_column($listed['data'], 'id');
        $this->assertContains($eventTypeId, $ids);

        // Get
        $fetched = $this->mgmt->eventTypes->get($this->workspaceId, $eventTypeId);
        $this->assertSame($eventTypeId, $fetched['id']);
        $this->assertSame($name, $fetched['name']);

        // Update
        $updatedDesc = "Updated PHP integration test {$ts}";
        $updated = $this->mgmt->eventTypes->update($this->workspaceId, $eventTypeId, [
            'description' => $updatedDesc,
        ]);
        $this->assertSame($eventTypeId, $updated['id']);
        $this->assertSame($updatedDesc, $updated['description']);

        // Delete
        $this->mgmt->eventTypes->delete($this->workspaceId, $eventTypeId);

        // Confirm deletion via 404
        try {
            $this->mgmt->eventTypes->get($this->workspaceId, $eventTypeId);
            $this->fail('Expected NahookAPIError (404) after deletion');
        } catch (NahookAPIError $e) {
            $this->assertSame(404, $e->status);
        }
    }

    // ---------------------------------------------------------------
    // Endpoints CRUD
    // ---------------------------------------------------------------

    public function testEndpointsCrud(): void
    {
        $ts = time();

        // Create
        $created = $this->mgmt->endpoints->create($this->workspaceId, [
            'url' => "https://example.com/php-test-{$ts}",
            'description' => "PHP integration test endpoint {$ts}",
        ]);
        $this->assertArrayHasKey('id', $created);
        $endpointId = $created['id'];
        $this->assertStringStartsWith('ep_', $endpointId);

        // List
        $listed = $this->mgmt->endpoints->list($this->workspaceId);
        $this->assertArrayHasKey('data', $listed);
        $this->assertIsArray($listed['data']);
        $ids = array_column($listed['data'], 'id');
        $this->assertContains($endpointId, $ids);

        // Get
        $fetched = $this->mgmt->endpoints->get($this->workspaceId, $endpointId);
        $this->assertSame($endpointId, $fetched['id']);
        $this->assertStringContainsString("php-test-{$ts}", $fetched['url']);

        // Update
        $updatedDesc = "Updated PHP endpoint {$ts}";
        $updated = $this->mgmt->endpoints->update($this->workspaceId, $endpointId, [
            'description' => $updatedDesc,
        ]);
        $this->assertSame($endpointId, $updated['id']);
        $this->assertSame($updatedDesc, $updated['description']);

        // Delete
        $this->mgmt->endpoints->delete($this->workspaceId, $endpointId);

        // Confirm deletion via 404
        try {
            $this->mgmt->endpoints->get($this->workspaceId, $endpointId);
            $this->fail('Expected NahookAPIError (404) after deletion');
        } catch (NahookAPIError $e) {
            $this->assertSame(404, $e->status);
        }
    }

    // ---------------------------------------------------------------
    // Applications CRUD
    // ---------------------------------------------------------------

    public function testApplicationsCrud(): void
    {
        $ts = time();

        // Create
        $created = $this->mgmt->applications->create($this->workspaceId, [
            'name' => "PHP Test App {$ts}",
            'externalId' => "php-ext-{$ts}",
        ]);
        $this->assertArrayHasKey('id', $created);
        $appId = $created['id'];
        $this->assertStringStartsWith('app_', $appId);
        $this->assertSame("PHP Test App {$ts}", $created['name']);

        // List
        $listed = $this->mgmt->applications->list($this->workspaceId);
        $this->assertArrayHasKey('data', $listed);
        $this->assertIsArray($listed['data']);
        $ids = array_column($listed['data'], 'id');
        $this->assertContains($appId, $ids);

        // Get
        $fetched = $this->mgmt->applications->get($this->workspaceId, $appId);
        $this->assertSame($appId, $fetched['id']);
        $this->assertSame("PHP Test App {$ts}", $fetched['name']);

        // Update
        $updatedName = "Updated PHP App {$ts}";
        $updated = $this->mgmt->applications->update($this->workspaceId, $appId, [
            'name' => $updatedName,
        ]);
        $this->assertSame($appId, $updated['id']);
        $this->assertSame($updatedName, $updated['name']);

        // Delete
        $this->mgmt->applications->delete($this->workspaceId, $appId);

        // Confirm deletion via 404
        try {
            $this->mgmt->applications->get($this->workspaceId, $appId);
            $this->fail('Expected NahookAPIError (404) after deletion');
        } catch (NahookAPIError $e) {
            $this->assertSame(404, $e->status);
        }
    }

    // ---------------------------------------------------------------
    // Subscriptions Lifecycle
    // ---------------------------------------------------------------

    public function testSubscriptionsLifecycle(): void
    {
        $ts = time();

        // Create an endpoint and event type to subscribe
        $endpoint = $this->mgmt->endpoints->create($this->workspaceId, [
            'url' => "https://example.com/php-sub-test-{$ts}",
            'description' => "PHP subscription test endpoint {$ts}",
        ]);
        $endpointId = $endpoint['id'];
        $this->assertStringStartsWith('ep_', $endpointId);

        $eventType = $this->mgmt->eventTypes->create($this->workspaceId, [
            'name' => "test.sub.php.{$ts}",
            'description' => "PHP subscription test event type {$ts}",
        ]);
        $eventTypeId = $eventType['id'];
        $this->assertStringStartsWith('evt_', $eventTypeId);

        // Subscribe endpoint to event type (plural array, returns {subscribed: N})
        $subscription = $this->mgmt->subscriptions->create(
            $this->workspaceId,
            $endpointId,
            [$eventTypeId],
        );
        $this->assertArrayHasKey('subscribed', $subscription);
        $this->assertSame(1, $subscription['subscribed']);

        // List subscriptions for this endpoint
        $listed = $this->mgmt->subscriptions->list($this->workspaceId, $endpointId);
        $this->assertArrayHasKey('data', $listed);
        $this->assertGreaterThanOrEqual(1, count($listed['data']));

        // Find our subscription in the list
        $subEventTypeIds = array_column($listed['data'], 'eventTypeId');
        $this->assertContains($eventTypeId, $subEventTypeIds);

        // Unsubscribe (DELETE uses event type public_id, returns 204)
        $this->mgmt->subscriptions->delete($this->workspaceId, $endpointId, $eventTypeId);

        // Verify unsubscribed - list should no longer contain it
        $afterDelete = $this->mgmt->subscriptions->list($this->workspaceId, $endpointId);
        $remainingIds = array_column($afterDelete['data'], 'eventTypeId');
        $this->assertNotContains($eventTypeId, $remainingIds);

        // Cleanup
        $this->mgmt->endpoints->delete($this->workspaceId, $endpointId);
        $this->mgmt->eventTypes->delete($this->workspaceId, $eventTypeId);
    }

    // ---------------------------------------------------------------
    // Environments CRUD
    // ---------------------------------------------------------------

    public function testEnvironmentsCrud(): void
    {
        $ts = time();

        // Create
        $created = $this->mgmt->environments->create($this->workspaceId, [
            'name' => "PHP Test Env {$ts}",
            'slug' => "php-test-env-{$ts}",
        ]);
        $this->assertArrayHasKey('id', $created);
        $envId = $created['id'];
        $this->assertSame("PHP Test Env {$ts}", $created['name']);
        $this->assertSame("php-test-env-{$ts}", $created['slug']);
        $this->assertArrayHasKey('isDefault', $created);
        $this->assertArrayHasKey('createdAt', $created);
        $this->assertArrayHasKey('updatedAt', $created);

        // List (should contain at least the default env + our new one)
        $listed = $this->mgmt->environments->list($this->workspaceId);
        $this->assertArrayHasKey('data', $listed);
        $this->assertIsArray($listed['data']);
        $this->assertGreaterThanOrEqual(2, count($listed['data']));
        $ids = array_column($listed['data'], 'id');
        $this->assertContains($envId, $ids);

        // Get
        $fetched = $this->mgmt->environments->get($this->workspaceId, $envId);
        $this->assertSame($envId, $fetched['id']);
        $this->assertSame("PHP Test Env {$ts}", $fetched['name']);

        // Update
        $updatedName = "Updated PHP Env {$ts}";
        $updated = $this->mgmt->environments->update($this->workspaceId, $envId, [
            'name' => $updatedName,
        ]);
        $this->assertSame($envId, $updated['id']);
        $this->assertSame($updatedName, $updated['name']);

        // Delete
        $this->mgmt->environments->delete($this->workspaceId, $envId);

        // Confirm deletion via 404
        try {
            $this->mgmt->environments->get($this->workspaceId, $envId);
            $this->fail('Expected NahookAPIError (404) after deletion');
        } catch (NahookAPIError $e) {
            $this->assertSame(404, $e->status);
        }
    }

    // ---------------------------------------------------------------
    // Event Type Visibility
    // ---------------------------------------------------------------

    public function testEventTypeVisibility(): void
    {
        $ts = time();

        // Create an environment
        $env = $this->mgmt->environments->create($this->workspaceId, [
            'name' => "PHP Vis Env {$ts}",
            'slug' => "php-vis-env-{$ts}",
        ]);
        $envId = $env['id'];

        // Create an event type
        $eventType = $this->mgmt->eventTypes->create($this->workspaceId, [
            'name' => "test.vis.php.{$ts}",
            'description' => "PHP visibility test event type {$ts}",
        ]);
        $eventTypeId = $eventType['id'];

        // List visibility
        $visibility = $this->mgmt->environments->listEventTypeVisibility($this->workspaceId, $envId);
        $this->assertArrayHasKey('data', $visibility);
        $this->assertIsArray($visibility['data']);

        // Set published = true
        $result = $this->mgmt->environments->setEventTypeVisibility(
            $this->workspaceId,
            $envId,
            $eventTypeId,
            true,
        );
        $this->assertSame($eventTypeId, $result['eventTypeId']);
        $this->assertTrue($result['published']);

        // Verify in list
        $afterPublish = $this->mgmt->environments->listEventTypeVisibility($this->workspaceId, $envId);
        $published = array_filter($afterPublish['data'], fn($v) => $v['eventTypeId'] === $eventTypeId);
        $this->assertCount(1, $published);
        $this->assertTrue(array_values($published)[0]['published']);

        // Cleanup
        $this->mgmt->environments->delete($this->workspaceId, $envId);
        $this->mgmt->eventTypes->delete($this->workspaceId, $eventTypeId);
    }

    // ---------------------------------------------------------------
    // Error: Invalid Token
    // ---------------------------------------------------------------

    public function testInvalidTokenReturns401(): void
    {
        $apiUrl = getenv('NAHOOK_TEST_API_URL') ?: '';

        $badMgmt = new NahookManagement('nhm_bogus_token_000000', [
            'baseUrl' => $apiUrl,
        ]);

        try {
            $badMgmt->endpoints->list($this->workspaceId);
            $this->fail('Expected NahookAPIError (401) for invalid token');
        } catch (NahookAPIError $e) {
            $this->assertSame(401, $e->status);
            $this->assertTrue($e->isAuthError());
            $this->assertFalse($e->isRetryable());
        }
    }
}
