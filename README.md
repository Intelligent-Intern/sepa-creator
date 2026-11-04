# SEPA Creator

PHP library and CLI for SEPA credit transfers, direct debits and bank payment-status
reports. Exact decimal amounts, deterministic typed generation, mandatory offline
schema and application checks, and private atomic exports.

Package: `intelligent-intern/sepa-creator` · PHP 8.2+ on 64-bit with DOM/libxml/JSON.
Our code is MIT; the pinned generator dependency `digitick/sepa-xml:3.2.0` is
LGPL-3.0-or-later. See [third-party notices](THIRD_PARTY_NOTICES.md).

## Supported formats

| Operation | Bundled, exercised formats |
| --- | --- |
| Generate/validate credit transfers | `pain.001.001.03` through `.13`, `pain.001.002.03`, `pain.001.003.03` |
| Generate/validate direct debits | `pain.008.001.02` through `.12`, `pain.008.002.02`, `pain.008.003.02` |
| Read payment-status reports | `pain.002.001.10`, `pain.002.001.15` |

These are 26 generation formats and two status-reader formats. Every generation
format is exercised with multiple payment groups; every DD format with both CORE
and B2B collections, RF references and mandate amendments. A format being valid
against its bundled schema does not establish bank/channel acceptance. Old
national variants have different constraints from generic ISO formats.

The default transfer format remains `pain.001.001.09`; DD defaults to
`pain.008.001.08`. Selecting a newer format is explicit, not an automatic upgrade.
The complete runtime list is available through `Formats` or `--formats`.

## Installation

```sh
composer install
composer test
```

Before Packagist registration/release tags, consumers can use the Git repository:

```sh
composer config repositories.ii-sepa vcs https://github.com/Intelligent-Intern/sepa-creator
composer require intelligent-intern/sepa-creator:dev-main
```

The repository lockfile pins development/container dependencies. A consumer's
Composer project manages its own lockfile. No framework, King, Java or external
service is required at runtime. Dependencies are installed at build/install time.

## Credit transfers: existing API

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
echo json_encode($file, JSON_THROW_ON_ERROR); // Metadata, no account details/XML.
```

Existing `PaymentBatch` calls still work. Their ordinary `.09` writer is retained;
new formats, options and multiple groups use the pinned generator adapter, followed
by the same mandatory output validation. To select another format:

```php
$file = (new SepaCreator())->create($batch, 'pain.001.001.13');
```

Use caller-controlled IDs, timestamps and transaction order. The same typed input
produces the same XML bytes; a UETR is included only if explicitly supplied.
The public IBAN examples and fictional names shown here are not test-bank accounts.

## Direct debits and multiple groups

```php
use IntelligentIntern\SepaCreator\{
    PaymentMessage, PaymentGroup, DirectDebit, Mandate, TransactionOptions
};

$group = new PaymentGroup(
    id: 'SUBSCRIPTIONS-1',
    origin: new Party('Example GmbH', 'DE89370400440532013000', 'COBADEFFXXX'),
    dueDate: '2026-11-05',
    transactions: [new DirectDebit(
        endToEndId: 'SUBSCRIPTION-001',
        debtor: new Party('Example Customer', 'FR1420041010050500013M02606'),
        amount: '49.00',
        mandate: new Mandate('MANDATE-001', '2026-01-01'),
        remittance: 'Subscription November 2026',
        options: new TransactionOptions(instructionId: 'INSTRUCTION-001'),
    )],
    creditorIdentifier: 'DE98ZZZ09999999999',
    scheme: 'CORE',
    sequenceType: 'RCUR',
);
$message = new PaymentMessage(
    messageId: 'DD-20261104-001',
    initiatingParty: 'Example GmbH',
    createdAt: new DateTimeImmutable('2026-11-04T09:00:00+01:00'),
    groups: [$group], // More groups may use different accounts/dates/schemes.
    format: 'pain.008.001.08',
);
$file = (new SepaCreator())->create($message);
```

`PaymentGroup` contains either `CreditTransfer` objects without a creditor ID, or
`DirectDebit` objects with one. A message cannot mix CT and DD. Group and end-to-end
IDs are unique across the message. At most 10,000 transactions are allowed in total.

DD supports CORE/B2B and FRST/RCUR/OOFF/FNAL. COR1 is retained for legacy-format
compatibility; availability must be agreed with the bank. `Mandate` also accepts
`originalMandateId`, `originalDebtorIban`, `amendedDebtorAccount` and
`finalCollectionDate`. The original IBAN is preserved. Modern SMNDA is represented
on the original account; the old national `.002/.003` profiles require SMNDA on
the original debtor agent and FRST. Unsupported combinations fail validation.
This checks data consistency, not the legal validity/existence of a mandate.

`TransactionOptions` supports instruction ID, purpose/category-purpose codes and
an explicit lowercase UUIDv4 UETR. Older formats that cannot represent a UETR are
rejected rather than silently dropping it. Codes are checked for shape and schema
compatibility, not against a live bank's external code lists.

## Validation, amounts and addresses

```php
use IntelligentIntern\SepaCreator\{DocumentValidator, CharacterSet};
$report = (new DocumentValidator())->validate($xml);
$report->assertValid();
echo json_encode($report, JSON_THROW_ON_ERROR); // status, format, findings, metadata
```

Reports distinguish `valid`, `invalid` and `unsupported`. Resource/runtime failures
throw; they are never reported as a successful validation. Findings carry a code,
message and XML path (schema findings carry line numbers).

- EUR `0.01`–`999999999.99` per transaction, supplied as decimal **strings**.
  The typed API rejects floats even from weakly typed PHP callers. JSON numeric
  amounts fail. Counts and control sums are recomputed using integer cents.
- IBAN country length, BBAN structure and mod-97 checksum; BIC syntax. Geographical
  IBAN prefixes are pinned to EPC409-09 v8.0, including Serbia/Gibraltar. Account
  existence, bank participation and beneficiary-name matching are not checked.
- Identifiers, mandatory SEPA party/account information, unique payment IDs,
  EUR/service/charge conventions, dates and total transaction limit.
- Mandatory DD mandate ID/signature, creditor-ID checksum (excluding its business
  code), collection sequence/instrument and amendment consistency. Country-specific
  creditor-ID registration/length rules are outside the generic checksum check.
- Unstructured remittance up to 140 characters **or** a checksum-validated
  ISO 11649 RF creditor reference; no silent replacement of either value.
- ISO/legacy-profile XSD validation on every export, against bundled SHA-256-pinned
  resources. No validation-time downloads or caller-selected schema locations.
- Bounded UTF-8 XML parsing, maximum 25 MiB, no DTDs/entity expansion. A bank's
  `xsi:schemaLocation` cannot select a remote validation schema.

The default character policy rejects text outside the SEPA basic set. Use
`new SepaCreator(CharacterSet::Utf8)` only with a bank-agreed repertoire; use the
same policy when validating that output. Names are limited to 70 characters.
Unsupported characters are not silently transliterated. The typed adapter scopes
and restores the dependency's global sanitizer on success and failure.

`StructuredAddress(town: 'Bonn', country: 'DE', street: 'Musterstrasse',
buildingNumber: '12', postCode: '53111')` can be passed to `Party(address: ...)`.
Both origin and counterparty addresses are preserved in generic ISO formats.
Town/country are required if supplied; country validates alpha-2 shape only.
Legacy national variants accept different address structures: typed structured
addresses fail explicitly there; use their native address-line API when appropriate.
Bank/profile-specific mandatory address rules remain the caller's responsibility.

## Advanced upstream API and bank switches

`SepaCreator::createNative(TransferFileInterface $source, string $format)` accepts
Digitick transfer-file objects for its advanced initiating-party identification,
issuer, ultimate-debtor, address-line and other generator options. See the
[pinned upstream API](https://github.com/php-sepa-xml/php-sepa-xml/tree/3.2.0/doc).
The output still passes our mandatory schema/business checks and returns the same
`PaymentFile` object. Upstream omissions/unsupported combinations may therefore be
rejected. Decimal input safety, deterministic creation time and preservation before
construction are guarantees of **our typed API**: an adapter cannot recover a
float already truncated or a name already sanitized inside an upstream object.

`new SepaCreator(profile: new BankProfile(omitGroupHeaderControlSum: true))` exposes
an explicit header-sum switch for bank profiles. `omitAgentIfBicMissing` is also
available. Neither flag bypasses XSD validation; omitting a mandatory agent fails.
No bank is described as certified/compatible without a real channel import test.

## Bank-status reports

```php
use IntelligentIntern\SepaCreator\PaymentStatusReader;
$status = (new PaymentStatusReader())->read($bankXml);
echo json_encode($status, JSON_THROW_ON_ERROR);
```

The reader validates pain.002 `.10` or `.15` against its pinned schema, then returns
original message/format, payment-group and transaction IDs, UETR, group/transaction
statuses, rejection reason codes, additional information and account-servicer
references. Original XML and schema digests support storing evidence. Missing
transaction IDs remain `null`; group status is not fabricated as a transaction
status. Acceptance is not interpreted as payment completion. Matching reports to
an authenticated bank source/tenant and applying state changes belong to the app.

## Export, CLI and Docker

`PaymentFile` exposes XML, actual format, group/transaction count, control sum,
schema hash and SHA-256. `writeTo()` creates mode `0600` files atomically via a
same-filesystem hard link, refusing existing files/symlinks. The directory must
exist. Archive the exact bytes and metadata privately.

```sh
bin/sepa-creator examples/batch.json --output /private/exports/new-demo.xml
bin/sepa-creator examples/multi-group.json --json
bin/sepa-creator examples/direct-debit.json > /private/exports/demo-dd.xml
bin/sepa-creator --formats
bin/sepa-creator --validate /private/exports/demo-dd.xml
bin/sepa-creator --status tests/fixtures/status.xml
cat examples/direct-debit.json | bin/sepa-creator -
make test
make lint
make image
make test-container
```

Generation defaults to XML-only stdout. `--output` publishes a new private file and
prints metadata; `--json` returns metadata plus XML. Validation/status modes output
JSON. Exit: 0 success/valid, 1 invalid/unsupported input, 3 runtime/output failure.
The CLI accepts local files/stdin up to 10 MiB, rejects unknown JSON fields, and
requires explicit timezones. Shell redirection uses the shell's permissions and
overwrite behavior; use `--output` for protected publication.

The Docker image includes its locked dependency and license, runs as UID 10001,
and requires no network during use. `make test-container` runs with network off,
read-only filesystem, dropped capabilities, memory/PID/time limits and temporary
storage. Example:

```sh
docker run --rm --network none --read-only --cap-drop ALL \
  -v "$PWD/examples:/input:ro" sepa-creator:local /input/direct-debit.json
```

Tests cover all 26 generator formats and both status schemas, 89 public IBAN
examples, two payment groups per format, CORE/B2B, mandate changes, structured
addresses, UETR/purpose handling, rejection cases, 10,000 maximum-sized amounts,
UTF-8/XML escaping, global-state restoration, private exports, CLI and Composer
consumption. They do not replace an import test with the intended bank.

## Boundaries and provenance

This creates payment files and reads status information. Bank submission, bank
connections, instant-payment product support, signatures, Verification of Payee,
app approval workflows and duplicate-payment prevention remain application work.
Due-date cutoffs, holidays, current EPC address/mandate requirements and each bank's
accepted formats/limits must be checked by the integrating application. Generation
and ISO validation are not a complete EPC conformance certificate or a payment.

See [the upstream comparison](UPSTREAM_COMPARISON.md),
[third-party notices](THIRD_PARTY_NOTICES.md) and `resources/manifest.json` for
versioned sources/hashes. Resource updates are explicit; rerun the format matrix.
Packagist registration and release tags remain separate publication steps.
