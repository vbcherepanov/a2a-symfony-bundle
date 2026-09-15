# Releasing the Symfony bundle

The maintainer runs Git and publication commands. CI verifies the package and
creates downloadable artifacts.

1. Run `make verify`, `make test-postgres`, `make test-symfony` and `make package`.
2. Push the reviewed changes and require **Bundle checks** to pass on GitHub.
3. Move the changelog entry from Unreleased to the chosen version and date.
4. Commit that change, push it and wait for CI before creating the version tag.
5. Push the tag and wait for its CI. Download `composer-package` from that exact run.
6. Verify SHA256SUMS and create the GitHub release with the ZIP and checksum.
7. Register the repository on Packagist and enable GitHub Hook synchronization.
8. Verify installation by package name and version in a clean Docker container.
9. Set the repository description, topics and Packagist homepage. Protect `main`
   with pull requests and the required **Bundle checks** status.

PHP package releases contain source; no compiled native binaries are published.
Composer resolves the released SDK and other runtime dependencies.

Mention the SDK's documented CORE-SEND-003 TCK limitation in release notes.
Do not claim full certification by the unchanged official TCK.
