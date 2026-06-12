# Changelog

All notable changes to this SDK are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/) and
this project follows [Semantic Versioning](https://semver.org/).

## [0.2.0] - 2026-06-12

### Features

- Per-application endpoint cap (maxEndpoints) and Developer Portal event-catalog toggle (showEventTypes) on the applications resource
- Add Deliveries resource to the management client

## [0.1.1] - 2026-05-25

### Features

- Add environments resource to the management client
- Expose optional environmentId on endpoint creation
- Embed workspace region in API keys for SDK auto-routing

## [0.1.0] - 2026-04-10

### Features

- Initial release: ingestion client (send, trigger, batches) and management
  client (endpoints, event types, applications, subscriptions, portal
  sessions) with webhook signature verification
