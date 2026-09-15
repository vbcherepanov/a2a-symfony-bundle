# Changelog

## Unreleased

Initial Symfony integration for A2A protocol 1.0.0, using A2A PHP SDK 1.0.0.

- JSON-RPC, REST and SSE routes with optional native gRPC serving.
- Named outbound clients with per-client TLS/mTLS settings.
- File and Doctrine DBAL task storage with principal and tenant isolation.
- Messenger scheduling, background workers and webhook retries.
- Bearer and Symfony access-token authentication, JSON logs and Prometheus metrics.
- Container integration tests, PostgreSQL 18 checks and clean package installation.

The SDK documents an upstream TCK CORE-SEND-003 defect. This release does not
claim full official TCK certification.
