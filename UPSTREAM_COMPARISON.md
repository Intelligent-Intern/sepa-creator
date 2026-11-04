# Comparison with digitick/sepa-xml 3.2.0

Checked 2026-11-04 against release 3.2.0, source commit
`1b876013bee9c49d9d9e61e6d17e195898a12037`. We use that established generator as
an exact Composer dependency, adding our own typed API and validation layer.
This is a feature/validation comparison, not a claim about project maturity.

| Area | Upstream 3.2.0 | This package |
| --- | --- | --- |
| CT/DD, multiple groups, CORE/B2B, mandate amendments | Available | Typed API plus native object adapter |
| Registered/bundled generation formats | 23 | 26, each exercised against its pinned schema |
| Additional bundled versions | `.001.001.11`, `.001.001.13`, `.008.001.12` absent from registry/schema bundle | All three included, with direct ISO schema provenance |
| IBAN checksum/BBAN and BIC syntax | Not validated by its core validation | Mandatory checks |
| DD mandate required fields | Not comprehensively checked | Required ID/signature plus date consistency |
| Creditor identifier | Supplied as text | Generic checksum/shape check; business code excluded correctly |
| Amount input | Integer cents; weak callers can coerce floats before construction | Decimal strings on typed/JSON API; mixed input rejects floats before coercion |
| Schema validation | Opt-in | Mandatory and offline for exports |
| Control sums/counts and IDs | Generated from mutable objects | Rechecked against XML; message-wide duplicate IDs rejected |
| Character conversion | Global sanitizer can silently transform input | Typed API preserves input or rejects; temporary sanitizer setting restored |
| Newer UETR | Optional | Explicit caller UUIDv4; unsupported format fails |
| Native object compatibility | Public API | `createNative()` plus our final validation |
| payment-status pain.002 reading | No reader in this generation library | `.10` and `.15`, IDs/status/reasons plus hashes |
| Private atomic export with digest metadata | Caller responsibility | `PaymentFile::writeTo()`, mode 0600, no overwrite |

Upstream's README mentions `pain.001.001.11`, but release 3.2.0's format registry
and schema bundle omit it. The general builder can be used with a manually supplied
schema; our addition is explicit registration, bundled schema and exercised output.
The newer schemas are additions to our wrapper, not modifications inside `vendor/`.

The typed DD implementation also preserves original debtor IBAN amendments,
orders original mandate/account nodes as required by XSD, handles the old national
SMNDA location, emits a supplied final-collection date, and preserves both origin
and counterparty structured addresses. The advanced native adapter retains
upstream behavior before our final validation; it cannot undo already-coerced
amounts or already-sanitized text. See the README's scope and bank caveats.

Primary sources:

- [Validation scope](https://github.com/php-sepa-xml/php-sepa-xml/blob/3.2.0/doc/guides/output-and-validation.md).
- [Input and validation gotchas](https://github.com/php-sepa-xml/php-sepa-xml/blob/3.2.0/doc/gotchas.md).
- [Actual format registry](https://github.com/php-sepa-xml/php-sepa-xml/blob/3.2.0/src/Util/MessageFormat.php).
- [DD builder](https://github.com/php-sepa-xml/php-sepa-xml/blob/3.2.0/src/DomBuilder/CustomerDirectDebitTransferDomBuilder.php).
- [Open format extension PR #252](https://github.com/php-sepa-xml/php-sepa-xml/pull/252).
- [Open amount-validation PR #259](https://github.com/php-sepa-xml/php-sepa-xml/pull/259).

Bank connections, approvals, actual payment execution and accounting reconciliation
are application services, not expected omissions in a payment XML generator.
