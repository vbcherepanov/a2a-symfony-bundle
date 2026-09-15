# Changelog

## 1.0.1 — 2026-09-15

- Allow Symfony Flex to register the bundle before A2A is configured. Without
  configuration, the bundle adds no services or routes and cache clearing succeeds.
- Keep required server settings and authentication validation when configuration is supplied.
- Check installation in a clean Symfony 8.0 skeleton with Flex and Composer scripts
  enabled, including development and production cache clearing.

## 1.0.0 — 2026-09-15

Initial Symfony integration for A2A protocol 1.0.0, using A2A PHP SDK 1.0.0.

- JSON-RPC, REST and SSE routes with optional native gRPC serving.
- Named outbound clients with per-client TLS/mTLS settings.
- File and Doctrine DBAL task storage with principal and tenant isolation.
- Messenger scheduling, background workers and webhook retries.
- Bearer and Symfony access-token authentication, JSON logs and Prometheus metrics.
- Container integration tests, PostgreSQL 18 checks and clean package installation.

The SDK documents an upstream TCK CORE-SEND-003 defect. This release does not
claim full official TCK certification.
