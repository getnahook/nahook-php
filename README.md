# Nahook PHP SDK

Official PHP SDK for the [Nahook](https://nahook.com) webhook platform.

## Requirements

- PHP 8.1+
- Guzzle HTTP client 7.x

## Installation

```bash
composer require nahook/nahook-php
```

## Quick Start

### Ingestion Client

Use `NahookClient` to send webhooks via your API key.

```php
use Nahook\NahookClient;

// Simple
$client = new NahookClient('nhk_us_your_api_key');

// With options
$client = new NahookClient('nhk_us_your_api_key', [
    'timeout' => 30000,                       // optional, default 30s (in ms)
    'retries' => 3,                           // optional, default 0
]);
```

#### Configuration

The SDK automatically routes requests to the correct regional API based on your API key prefix (`nhk_us_...` -> US, `nhk_eu_...` -> EU, `nhk_ap_...` -> Asia Pacific). No configuration needed.

To override the base URL (for testing or local development):

```php
$client = new NahookClient('nhk_us_your_api_key', [
    'baseUrl' => 'http://localhost:3001',
]);
```

For unit tests, mock the SDK client at the dependency injection boundary. For integration tests, override the base URL to point at a local server.

#### Send to a specific endpoint

```php
$result = $client->send('ep_abc123', [
    'payload' => [
        'event' => 'order.completed',
        'data' => ['orderId' => 'ord_456', 'amount' => 99.99],
    ],
    'idempotencyKey' => 'my-unique-key',  // optional, auto-generated UUID if omitted
]);

// $result = ['deliveryId' => 'del_...', 'idempotencyKey' => '...', 'status' => 'accepted']
```

#### Trigger by event type (fan-out)

```php
$result = $client->trigger('order.paid', [
    'payload' => ['orderId' => 'ord_456', 'amount' => 99.99],
    'metadata' => ['source' => 'checkout'],  // optional
]);

// $result = ['eventTypeId' => 'evt_...', 'deliveryIds' => ['del_...'], 'status' => 'accepted']
```

#### Batch send

```php
$result = $client->sendBatch([
    ['endpointId' => 'ep_abc', 'payload' => ['event' => 'user.created']],
    ['endpointId' => 'ep_def', 'payload' => ['event' => 'user.updated']],
]);

// $result = ['items' => [['index' => 0, 'deliveryId' => '...', 'status' => 'accepted'], ...]]
```

#### Batch trigger

```php
$result = $client->triggerBatch([
    ['eventType' => 'order.paid', 'payload' => ['orderId' => '123']],
    ['eventType' => 'order.shipped', 'payload' => ['orderId' => '456']],
]);

// $result = ['items' => [['index' => 0, 'eventTypeId' => '...', 'deliveryIds' => [...], 'status' => 'accepted'], ...]]
```

### Management API

Use `NahookManagement` to manage endpoints, event types, applications, subscriptions, and portal sessions.

```php
use Nahook\NahookManagement;

// Simple
$mgmt = new NahookManagement('nhm_your_management_token');

// With options
$mgmt = new NahookManagement('nhm_your_management_token', [
    'timeout' => 30000,                       // optional
]);
```

#### Endpoints

```php
// List all endpoints
$result = $mgmt->endpoints->list('ws_workspace_id');
// $result = ['data' => [['id' => 'ep_...', 'url' => '...', ...], ...]]

// Create an endpoint
$endpoint = $mgmt->endpoints->create('ws_workspace_id', [
    'url' => 'https://example.com/webhook',
    'description' => 'My webhook endpoint',
    'type' => 'webhook',  // 'webhook' or 'slack'
    'metadata' => ['env' => 'production'],
]);

// Get an endpoint
$endpoint = $mgmt->endpoints->get('ws_workspace_id', 'ep_abc123');

// Update an endpoint
$endpoint = $mgmt->endpoints->update('ws_workspace_id', 'ep_abc123', [
    'description' => 'Updated description',
    'isActive' => false,
]);

// Delete an endpoint
$mgmt->endpoints->delete('ws_workspace_id', 'ep_abc123');
```

#### Event Types

```php
// List event types
$result = $mgmt->eventTypes->list('ws_workspace_id');

// Create an event type
$eventType = $mgmt->eventTypes->create('ws_workspace_id', [
    'name' => 'order.paid',
    'description' => 'Fired when an order is paid',
]);

// Get an event type
$eventType = $mgmt->eventTypes->get('ws_workspace_id', 'evt_abc123');

// Update an event type
$eventType = $mgmt->eventTypes->update('ws_workspace_id', 'evt_abc123', [
    'description' => 'Updated description',
]);

// Delete an event type
$mgmt->eventTypes->delete('ws_workspace_id', 'evt_abc123');
```

#### Applications

```php
// List applications (with optional pagination)
$result = $mgmt->applications->list('ws_workspace_id', ['limit' => 10, 'offset' => 0]);

// Create an application
$app = $mgmt->applications->create('ws_workspace_id', [
    'name' => 'Acme Corp',
    'externalId' => 'ext-123',
    'metadata' => ['tier' => 'enterprise'],
]);

// Get an application
$app = $mgmt->applications->get('ws_workspace_id', 'app_abc123');

// Update an application
$app = $mgmt->applications->update('ws_workspace_id', 'app_abc123', [
    'name' => 'Acme Corp Updated',
]);

// Delete an application
$mgmt->applications->delete('ws_workspace_id', 'app_abc123');

// List endpoints for an application
$result = $mgmt->applications->listEndpoints('ws_workspace_id', 'app_abc123');

// Create an endpoint for an application
$endpoint = $mgmt->applications->createEndpoint('ws_workspace_id', 'app_abc123', [
    'url' => 'https://example.com/webhook',
]);
```

#### Subscriptions

```php
// List subscriptions for an endpoint
$result = $mgmt->subscriptions->list('ws_workspace_id', 'ep_abc123');

// Create a subscription
$sub = $mgmt->subscriptions->create('ws_workspace_id', 'ep_abc123', [
    'eventTypeId' => 'evt_abc123',
]);

// Delete a subscription
$mgmt->subscriptions->delete('ws_workspace_id', 'ep_abc123', 'evt_abc123');
```

#### Environments

```php
// List environments
$result = $mgmt->environments->list('ws_workspace_id');

// Create an environment
$env = $mgmt->environments->create('ws_workspace_id', [
    'name' => 'Staging',
    'slug' => 'staging',
]);

// Get an environment
$env = $mgmt->environments->get('ws_workspace_id', 'env_abc123');

// Update an environment
$env = $mgmt->environments->update('ws_workspace_id', 'env_abc123', [
    'name' => 'Pre-production',
]);

// Delete an environment
$mgmt->environments->delete('ws_workspace_id', 'env_abc123');
```

#### Event Type Visibility

```php
// List event type visibility for an environment
$result = $mgmt->environments->listEventTypeVisibility('ws_workspace_id', 'env_abc123');

// Set an event type as published in an environment
$vis = $mgmt->environments->setEventTypeVisibility('ws_workspace_id', 'env_abc123', 'evt_abc123', [
    'published' => true,
]);
// $vis = ['eventTypeId' => 'evt_...', 'eventTypeName' => 'order.paid', 'published' => true]
```

#### Portal Sessions

```php
// Create a portal session
$session = $mgmt->portalSessions->create('ws_workspace_id', 'app_abc123', [
    'metadata' => ['userId' => 'user-1'],
]);

// $session = ['url' => 'https://portal.nahook.com/s/...', 'code' => '...', 'expiresAt' => '...']
```

## Error Handling

All errors extend `Nahook\Errors\NahookError` (which extends `\RuntimeException`).

```php
use Nahook\NahookClient;
use Nahook\Errors\NahookAPIError;
use Nahook\Errors\NahookNetworkError;
use Nahook\Errors\NahookTimeoutError;

$client = new NahookClient('nhk_us_your_api_key');

try {
    $result = $client->send('ep_abc', ['payload' => ['test' => true]]);
} catch (NahookAPIError $e) {
    echo "API Error: {$e->getMessage()}\n";
    echo "Status: {$e->status}\n";
    echo "Code: {$e->errorCode}\n";

    if ($e->isRateLimited()) {
        echo "Rate limited. Retry after: {$e->retryAfter}s\n";
    }
    if ($e->isAuthError()) {
        echo "Authentication failed\n";
    }
    if ($e->isNotFound()) {
        echo "Resource not found\n";
    }
    if ($e->isValidationError()) {
        echo "Validation error\n";
    }
} catch (NahookTimeoutError $e) {
    echo "Request timed out after {$e->timeoutMs}ms\n";
} catch (NahookNetworkError $e) {
    echo "Network error: {$e->getMessage()}\n";
    echo "Cause: {$e->cause->getMessage()}\n";
}
```

## Retry Configuration

The client supports automatic retries with exponential backoff and full jitter.

```php
$client = new NahookClient('nhk_us_your_api_key', [
    'retries' => 3,  // retry up to 3 times on retryable errors
]);
```

Retryable errors:
- HTTP 5xx (server errors)
- HTTP 429 (rate limited)
- Network connection errors
- Timeout errors

Non-retryable errors:
- HTTP 400, 401, 403, 404, 409, 413

The retry delay formula is: `min(10000, 500 * 2^attempt) * random(0, 1)` milliseconds, with `Retry-After` header respected when present.

## License

MIT
