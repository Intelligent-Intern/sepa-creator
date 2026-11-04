# SEPA Creator

MIT-licensed PHP library and CLI for SEPA credit-transfer XML files in
`pain.001.001.09`, using the standard ISO 20022 namespace. No framework,
King extension, remote API or Java process is required.

Package: `intelligent-intern/sepa-creator` · PHP 8.2+ on 64-bit, DOM/libxml/JSON.

## Scope

- One debtor account and requested execution date per file, 1–10,000 transfers.
- EUR amounts from `0.01` to `999999999.99` per transfer, supplied as **decimal
  strings**. Integer-cent arithmetic; floats and numeric JSON amounts are rejected.
- Message/payment identifiers and unique end-to-end IDs; reproducible output when
  input order, identifiers and creation time remain unchanged.
- IBAN country length, BBAN structure and mod-97 checksum checks, plus BIC syntax.
  SEPA geographical IBAN prefixes are pinned to EPC409-09 v8.0, including Serbia
  and Gibraltar. This does not check an account's existence or a bank's membership.
- Unstructured remittance up to 140 characters, or an ISO 11649 RF creditor
  reference with checksum; optional structured postal addresses.
- Generated XML is validated against the unmodified official ISO schema on every
  call. Schema/IBAN resources are bundled, hashed and usable offline.
- XML plus count, control sum, schema hash and file SHA-256 for export records.
- Private, atomic file publication without overwriting existing files or symlinks.

This creates **ordinary SCT transfer files**. Direct debits (`pain.008`), instant
payments, bank submission, signatures, Verification of Payee and payment approval
are outside this package. An export is not a submitted or completed payment.
The application must enforce tenant access, approvals, outstanding amounts and
protection against exporting/paying the same invoice twice. Fixed identifiers and
deterministic bytes support reconciliation; they do not guarantee bank deduplication.

The bank must support this format. There is no blanket claim that every Sparkasse
or banking product accepts it; verify the intended import channel. Bank-specific
limits, address requirements (including non-EEA cases), execution cutoffs and bank
holidays remain the caller's responsibility. This is not a complete EPC scheme
conformance certificate. The initial version does not support older pain versions.

## Installation

For local development:

```sh
composer install
composer test
```

Until the package is registered on Packagist and tagged, consumers can use the
Git repository directly:

```sh
composer config repositories.ii-sepa vcs https://github.com/Intelligent-Intern/sepa-creator
composer require intelligent-intern/sepa-creator:dev-main
```

Packagist registration and release tags are separate publishing steps. The Git
repository already contains the Composer name, PSR-4 autoloading and MIT metadata.

## PHP API

```php
<?php
require 'vendor/autoload.php';

use IntelligentIntern\SepaCreator\{SepaCreator, PaymentBatch, Party, CreditTransfer};

$batch = new PaymentBatch(
    messageId: 'DEMO-20261104-001',
    paymentInfoId: 'DEMO-PAYMENTS-001',
    debtor: new Party('Example GmbH', 'DE89370400440532013000', 'COBADEFFXXX'),
    requestedExecutionDate: '2026-11-05',
    transfers: [new CreditTransfer(
        endToEndId: 'DEMO-INVOICE-001',
        creditor: new Party('Example Supplier', 'FR1420041010050500013M02606'),
        amount: '149.90',
        remittance: 'Demo invoice 001',
    )],
    createdAt: new DateTimeImmutable('2026-11-04T09:00:00+01:00'),
);

$file = (new SepaCreator())->create($batch);
$file->writeTo('/private/exports/new-payment-file.xml');
echo json_encode($file, JSON_THROW_ON_ERROR); // Metadata, no account details or XML.
// $file->xml contains the complete XML. Archive the exact bytes and metadata privately.
```

The examples use public IBAN registry examples and fictional payee names, not
test-bank accounts. Choose appropriate values and dates in the calling application.
The output directory must exist. `writeTo()` creates mode `0600` files and uses a
same-filesystem hard link for atomic publication; unsupported filesystems fail
instead of falling back to a partial write or overwrite.

The default character policy is the EPC basic Latin set. It rejects unsupported
characters instead of silently changing beneficiary names. If the bank agrees
to the character repertoire, explicitly opt in with
`new SepaCreator(CharacterSet::Utf8)`. `CharacterSet` is in this package namespace.
Names remain limited to 70 characters. XML-sensitive characters are safely escaped.
Identifiers always use the restricted basic set and follow the slash restrictions.

`StructuredAddress(town: 'Bonn', country: 'DE', street: 'Musterstrasse',
buildingNumber: '12', postCode: '53111')` can be passed as a party's `address`.
Town and country are required if an address is supplied. Country validates the
alpha-2 shape, not an authoritative address/country registry. BIC validation checks
syntax only. Choose either `remittance` or `creditorReference` on each transfer.

## CLI

```sh
bin/sepa-creator examples/batch.json > /private/exports/demo.xml
bin/sepa-creator examples/batch.json --output /private/exports/new-demo.xml
bin/sepa-creator examples/batch.json --json
cat examples/batch.json | bin/sepa-creator -
```

Default stdout is only XML. `--output` writes a new private file and prints metadata;
`--json` returns metadata and XML. Invalid input exits 1, runtime/output failure 3,
success 0. Errors go to stderr. The CLI accepts local files/stdin up to 10 MiB;
unknown JSON fields, non-string amounts and ambiguous dates are rejected.
Use `--output` for private atomic publication; ordinary shell redirection follows
the caller's filesystem permissions and overwrite behavior.

## Docker and tests

```sh
make test
make lint
make image
make test-container
docker run --rm --network none --read-only --cap-drop ALL \
  -v "$PWD/examples:/input:ro" sepa-creator:local /input/batch.json
```

The image uses UID 10001 and contains no credentials. The container test target
adds memory, PID, temporary-filesystem and wall-time limits. Tests cover the real
ISO schema, all 89 bundled public IBAN examples, exact sums, Unicode/XML escaping,
invalid monetary inputs/identifiers, private files and CLI behavior. A bank import
has not been exercised. No a consuming application UI or payment service is changed by this library.

## Standards, provenance and license

The library code is MIT. Bundled schema information retains its ISO repository
IPR policy, and the extracted technical IBAN facts retain Swift provenance; see
[third-party notices](THIRD_PARTY_NOTICES.md) and `resources/manifest.json`.
No validation-time downloads occur. Update resources explicitly and rerun tests.

References checked on 2026-11-04:

- [EPC 2025 SCT customer-to-PSP implementation guidelines](https://www.europeanpaymentscouncil.eu/document-library/implementation-guidelines/sepa-credit-transfer-customer-psp-implementation-1).
- [Official ISO pain.001.001.09 schema](https://www.iso20022.org/message/14346/download).
- [EPC geographical scope, v8.0](https://www.europeanpaymentscouncil.eu/sites/default/files/kb/file/2025-12/EPC409-09%20EPC%20List%20of%20SEPA%20Scheme%20Countries%20v8.0.pdf).
- [Swift IBAN registry](https://www.swift.com/standards/data-standards/iban-international-bank-account-number).
- [EPC character repertoire](https://www.europeanpaymentscouncil.eu/document-library/guidance-documents/sepa-requirements-extended-character-set-unicode-subset-best).
- [EPC identifier slash rules](https://www.europeanpaymentscouncil.eu/document-library/guidance-documents/clarification-paper-use-slashes-references-identifications-and).
