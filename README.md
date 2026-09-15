# A2A Symfony Bundle

[![CI](https://github.com/vbcherepanov/a2a-symfony-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/vbcherepanov/a2a-symfony-bundle/actions/workflows/ci.yml)

Expose your Symfony application as an A2A agent and call other agents from your services.
The bundle integrates [A2A PHP SDK](https://github.com/vbcherepanov/a2a-php-sdk)
with Symfony's container, routing, security and Messenger. It implements **A2A protocol 1.0.0**.

The bundle provides JSON-RPC, REST and SSE routes, optional native gRPC through
OpenSwoole, scoped task storage (files or Doctrine DBAL), Messenger scheduling,
Bearer/Symfony access-token authentication, structured logs and persistent
Prometheus counters/histograms.

## Install and register

| Component | Requirement |
|---|---|
| PHP | 8.4+ with `ext-bcmath` |
| Symfony | 7.4 or 8.x |
| SDK | `vbcherepanov/a2a-php-sdk` 1.x, installed by Composer |
| Doctrine storage | A configured DBAL connection and its PHP database driver |
| Native gRPC | Optional; requirements below |
| Development | Docker and Docker Compose v2 |

Install the bundle inside your application's PHP container:

~~~sh
docker compose run --rm php composer require vbcherepanov/a2a-symfony-bundle
~~~

Register the bundle in `config/bundles.php`:

~~~php
return [
    // Keep your application's other bundles here.
    A2A\Bundle\A2ABundle::class => ['all' => true],
];
~~~

The setup below is explicit; it does not depend on a Symfony Flex recipe.

Import the routes in config/routes/a2a.yaml:

~~~yaml
a2a:
    resource: .
    type: a2a
~~~

Configure config/packages/a2a.yaml:

~~~yaml
a2a:
    executor: App\Agent\MyExecutor
    card_file: '%kernel.project_dir%/config/agent-card.json'
    public_url: '%env(A2A_PUBLIC_URL)%'
    auth:
        tokens:
            peer: '%env(A2A_PEER_TOKEN)%'
    storage:
        driver: file
        directory: '%kernel.project_dir%/var/a2a/tasks'
    push:
        allowed_hosts: ['%env(A2A_WEBHOOK_HOST)%']
    clients:
        peer:
            endpoint: '%env(A2A_PEER_ENDPOINT)%'
            binding: jsonrpc
            headers:
                Authorization: 'Bearer %env(A2A_OUTBOUND_TOKEN)%'
~~~

MyExecutor implements A2A\Server\Executor. Its execute method receives typed
SendMessageRequest, Task and CallContext and yields StreamResponse values containing
statusUpdate or artifactUpdate. End with a terminal state or an interrupted state.
See the [SDK executor example](https://github.com/vbcherepanov/a2a-php-sdk#executor).
Register the executor as a Symfony service and inject its application dependencies
through its constructor.

The AgentCard JSON supplies name, description, version, skills, capabilities and
input/output modes. The bundle builds supportedInterfaces from enabled transports.
Only declare capabilities the executor/application supports. An extended card is
enabled by extended_card_file and the extendedAgentCard capability.

Inject an outbound SDK client with a named argument:
A2A\Client\Client $peerClient. TLS, deadlines, headers, message limits and negotiated
extensions are configured independently per client.

## HTTPS and client certificates

For an internal CA or mTLS, give each HTTP client its own certificate files:

~~~yaml
a2a:
    clients:
        peer:
            endpoint: '%env(A2A_PEER_ENDPOINT)%'
            binding: jsonrpc
            tls:
                ca_file: '%env(A2A_PEER_CA_FILE)%'
                certificate_file: '%env(A2A_CLIENT_CERT_FILE)%'
                private_key_file: '%env(A2A_CLIENT_KEY_FILE)%'
~~~

Use an HTTPS endpoint. Omit `ca_file` to trust the system CA store. Omit both
client certificate files if the server does not require mTLS. Server certificates
and hostnames are always verified.

Plain HTTP requires `tls.enabled: false` and an `http://` endpoint. This is a change
from the earlier development version, which ignored the TLS options for HTTP
clients. A scheme mismatch or certificate options for the wrong transport now
raises a configuration error instead of being ignored.

## Optional gRPC

~~~yaml
a2a:
    transports:
        grpc:
            enabled: true
            host: '%env(A2A_GRPC_HOST)%'
            port: '%env(int:A2A_GRPC_PORT)%'
            public_url: '%env(A2A_GRPC_PUBLIC_URL)%'
            tls:
                certificate_file: '%env(A2A_GRPC_CERTIFICATE_FILE)%'
                private_key_file: '%env(A2A_GRPC_PRIVATE_KEY_FILE)%'
~~~

Run php bin/console a2a:grpc:serve under a process supervisor in a container with
OpenSwoole 26.2+ compiled with HTTP/2. gRPC is disabled by default and HTTP services
do not require OpenSwoole. Native outbound gRPC additionally needs ext-grpc and
grpc/grpc; configure clients.*.binding: grpc and endpoint: host:port.
gRPC client settings `root_certificates`, `certificate_chain` and `private_key`
contain PEM data. HTTP clients and gRPC servers use file paths. For server-side
mTLS, also set `client_ca_file` and `require_client_certificate: true`.

Streaming subscriptions use cooperative sleep in the gRPC runtime so streams on
the same HTTP/2 connection can progress independently. Executors must not block
indefinitely or store per-request identity in shared mutable service properties.

## Durable processing

File storage requires a persistent shared volume for HTTP and gRPC workers.
For Doctrine, select storage.driver: doctrine and configure storage.connection.
Run php bin/console a2a:storage:init explicitly to create the task table.
Schema creation never runs during an HTTP request.

Run php bin/console a2a:work for durable background tasks and webhook retries.
With messenger.enabled: true, route A2A\Bundle\Messenger\ProcessQueue to a real
asynchronous Messenger transport and run messenger:consume for it. The a2a:work
scanner remains useful for recovery and delayed webhook retries. Sync transports
are rejected for returnImmediately requests.

Task access is scoped by authenticated principal and tenant. To reuse Symfony
security, set auth.access_token_handler to your AccessTokenHandlerInterface service,
or auth.service to an A2A\Security\Authenticator implementation. The same identity
contract applies to HTTP and gRPC. With the built-in token map, the map key is the
principal identifier. A custom authenticator can also supply a tenant identifier.

### Doctrine storage

Configure an existing DBAL connection service, for example from DoctrineBundle:

~~~yaml
a2a:
    storage:
        driver: doctrine
        connection: doctrine.dbal.default_connection
        table: a2a_tasks
~~~

Run `a2a:storage:init` against the intended database before starting workers.
Use your normal database migration and backup process for later schema changes.

### Messenger routing

Use your application's queue DSN and install the Symfony transport package it requires:

~~~yaml
framework:
    messenger:
        transports:
            a2a_async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'A2A\Bundle\Messenger\ProcessQueue': a2a_async

a2a:
    messenger:
        enabled: true
~~~

Run `messenger:consume a2a_async` and `a2a:work` under your process supervisor.
Messages wake the processor; persisted tasks and leases allow recovery after a crash.
Webhook delivery is at least once, so receivers must handle duplicates.

## Configuration and operations

All supported settings and defaults are in [configuration.yaml](docs/configuration.yaml).
Custom metrics_service must implement A2A\Observability\Metrics. The default
a2a.metrics service persists counters and latency histograms in metrics_file;
render() returns Prometheus text. Expose it through your application's protected
monitoring route. Use a shared volume or a centralized custom adapter across replicas.
Change the metrics file when changing histogram boundaries.

Webhook hosts are explicitly allowlisted; HTTPS is required and private-network
destinations are blocked by default. allow_private_network and require_https
are explicit settings for trusted internal deployments. Store webhook credentials
and task storage on appropriately protected volumes.

## Verify

Clone this repository and run:

~~~sh
docker compose build
make install
make verify
make test-postgres
make test-symfony
make package
~~~

The repository has its own Dockerfile and test fixtures. Composer installs the
released SDK from Packagist; no adjacent SDK checkout is required. The Dockerfile
provides a development runtime with optional gRPC extensions for the tests.
`make test` includes real TLS/mTLS calls and streaming through the named clients.
The test certificates are generated inside the container and are not checked in.

Run `make test-postgres` for PostgreSQL 18. It creates a temporary database, checks
reconnection, access isolation, conflicting updates, rollback, duplicate IDs and
lease recovery, then removes the test containers. It publishes no ports and uses
no persistent database volume. Test settings are listed in `.env.example`.

`make verify` runs Composer validation, tests, PHPStan level 8, PSR-12 checks and
Symfony container compilation. `make test-symfony` repeats the tests and compilation
with Symfony 7.4 and 8.0 in temporary projects; the lockfile covers Symfony 8.1.

`make package` creates `dist/a2a-symfony-bundle.zip` and `dist/SHA256SUMS`. It installs
the archive in a fresh consumer project and checks discovery and JSON-RPC task
execution without native gRPC extensions or the `grpc/grpc` package. The archive
includes source, license, README, changelog and public documentation. Development
tools, dependencies, caches and local journals are excluded.

CI runs these checks on pull requests, pushes to `main` and version tags, and audits
dependencies for known vulnerabilities. A successful run attaches the package and
checksum. Releases and Packagist registration are manual steps; see
[the release checklist](docs/RELEASING.md).

The SDK's unchanged official TCK baseline has three known CORE-SEND-003 failures.
The isolated diagnostic correction passes. Bundle CI checks Symfony integration;
it does not rerun the SDK's TCK. See the [SDK TCK notes](https://github.com/vbcherepanov/a2a-php-sdk/blob/v1.0.0/docs/TCK_UPSTREAM.md).
This is not a claim of complete official TCK certification.

## Commands and routes

| Command | Purpose |
|---|---|
| `php bin/console a2a:storage:init` | Initialize the configured task storage |
| `php bin/console a2a:work` | Process background tasks and retry webhooks |
| `php bin/console a2a:grpc:serve` | Serve the enabled native gRPC endpoint |

The default discovery URL is `/.well-known/agent-card.json`. JSON-RPC uses
`/a2a/rpc`; REST operations use `/a2a`. Streaming HTTP responses use SSE.
Configure external URLs with the paths and TLS termination used by your deployment.
Ensure your proxy permits long-lived streaming responses and does not buffer SSE.

## Support and license

Report reproducible bugs through [GitHub Issues](https://github.com/vbcherepanov/a2a-symfony-bundle/issues).
Include PHP/Symfony versions, binding, storage driver and a minimal example with
credentials removed. See [CHANGELOG.md](CHANGELOG.md) for release changes.
Licensed under Apache-2.0; see [LICENSE](LICENSE).
