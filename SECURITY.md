# PHP runtime security policy

Reviewed 2026-11-20. SEPA Creator supports these patched PHP branches:

| Branch | Minimum patch |
| --- | --- |
| 8.2 | 8.2.36 |
| 8.3 | 8.3.37 |
| 8.4 | 8.4.28 |
| 8.5 | 8.5.13 |

Composer excludes earlier versions. A Composer bootstrap also checks the actual
interpreter, covering copied vendor directories or overridden Composer platform
settings. Standalone CLI use applies the same check. Unreviewed branches fail.
This guard does not patch PHP or protect request parsing before application code.

The Docker runtime uses the maintained Wolfi PHP 8.5 CLI packages (at least
8.5.13-r1) on a digest-pinned Wolfi base. Package updates are applied when building. Its build runs
`tools/check-runtime.php`, failing for unsupported PHP or enabled session upload
progress. `make check-runtime` checks the current interpreter/startup settings.
`make test` requires that check; use `make test-container` on an older host.

## Advisories addressed

- **2026-11-19 PHP security releases:** the minimum patches above include the
  newly published Date, session, SAPI and XML fixes, including CVE-2026-129496,
  CVE-2026-126714 and CVE-2026-129501 where applicable to the PHP branch.
  Both Composer constraints and the actual-interpreter bootstrap reject the
  previous patch levels; prerelease and malformed version strings also fail.
  [Official PHP changelog](https://www.php.net/ChangeLog-8.php).

- **CVE-2026-109618 / CVE-2026-109619 (PHP2Shell):** use the patched versions above.
  [PHP advisory](https://www.php.net/archive/2026.php#2026-10-21-5),
  [OOM advisory](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w),
  [stale-state advisory](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp).
- **CVE-2026-115560:** the follow-up session upload-progress issue is handled by
  setting `session.upload_progress.enabled=0` before request parsing. It is included
  in `docker/php-security.ini`; PHP 8.4.27 alone is insufficient for this follow-up.
  [PHP follow-up advisory](https://www.php.net/archive/2026.php#2026-10-23-1).

For an application embedding this library, apply the INI setting to every actual
FPM/Apache/other web runtime and restart/recreate its workers. Setting it using
`ini_set()` in application code is too late. Verify the effective web SAPI, not
only the CLI configuration. Do not treat Composer's `config.platform.php` or
`--ignore-platform-reqs` as security fixes. Vendor backports below these version
floors are conservatively rejected; use an accepted upstream version.

Windows deployments additionally need the updated `-1` distribution packages
for the Brotli dependency fix (CVE-2026-111497); PHP_VERSION is unchanged by that
package rebuild. The provided Docker runtime is Linux.
[PHP Windows build notice](https://www.php.net/archive/2026.php#2026-10-26-1).

Keep applying subsequent security updates. These checks address the named
advisories and do not certify absence of other vulnerabilities. Library updates
do not upgrade the developer host or a consuming application's PHP server.

## Container dependency hardening (2026-11-20)

The container uses Wolfi's maintained PHP 8.5 packages and libxml2 (at least
2.15.4), with only DOM/XMLReader and their runtime dependencies. Composer,
unzip, its TLS/phar extensions, compilers, development headers and the Debian
package set are confined to build stages or absent from the final image.
All installed runtime packages remain in the native APK database and CycloneDX
SBOM; no CVEs are ignored or removed from scan reports.

The previous Debian runtime retained an older libxml2 with CVE-2026-6653 and
unnecessary development packages even after its attempted automatic purge.
Replacing the actual runtime dependencies fixes that exposure rather than
reclassifying it. Input remains bounded UTF-8; DTD/entity declarations are rejected
before libxml. Full container scans and source dependency scans are separate
checks and must identify the exact built image and current source manifests.

The container build checks its PHP branch, required extensions, effective startup
configuration and libxml version. `make test-container` exercises all generation
and status-reader formats without network access, under UID 10001 with a read-only
root filesystem and resource limits. Rebuild, rerun tests and rescan whenever the
base or package revisions change; a clean scan is a dated result, not a permanent
guarantee.

Upstream packaging: [Wolfi PHP](https://github.com/wolfi-dev/os/blob/main/php-8.5.yaml),
[Wolfi libxml2](https://github.com/wolfi-dev/os/blob/main/libxml2.yaml).
