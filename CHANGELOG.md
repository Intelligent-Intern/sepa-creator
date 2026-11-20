# Changelog

## 0.1.0 — 2026-11-20

First versioned release of the existing SEPA library and CLI.

- Replaced the Debian container runtime with maintained Wolfi packages: PHP 8.5.13
  or newer and libxml2 2.15.4 or newer, without unused development headers and
  Composer/build dependencies in the final image.
- Kept the PHP branch policy, startup configuration guard, non-root UID, offline
  schemas, exact decimal amounts and existing public API.
- Exercised all 26 payment-generation formats and both payment-status formats:
  527 checks under a read-only, network-isolated container with resource limits.
- Fully scanned the release runtime and its locked Composer dependency. Security
  reports and SBOMs identify their exact inputs; rebuild and rescan for later
  package updates.
