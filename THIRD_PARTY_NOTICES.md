# Third-party information

The MIT license covers this library's code. It does not relicense standards,
the ISO schema or third-party technical information.

`resources/pain.001.001.09.xsd` is the unmodified schema downloaded from the
ISO 20022 Registration Authority: https://www.iso20022.org/message/14346/download.
Its original generator header is preserved. The ISO repository's IPR policy is
at https://www.iso20022.org/intellectual-property-rights; contributing organisations
retain their rights and grant a non-exclusive, royalty-free license to use the
published information. This resource is not represented as MIT-licensed code.

`resources/iban-formats.json` consists of country codes, lengths and national BBAN
format facts extracted from the Swift IBAN Registry TXT download at
https://www.swift.com/swift-resource/11971/download. `tests/fixtures/iban-examples.json`
contains the corresponding public example identifiers, without personal contact data.
Swift is the ISO 13616 registration authority. See
https://www.swift.com/standards/data-standards/iban-international-bank-account-number.
The snapshot is recorded by source hash and retrieval date; no claim about the
latest registry release or current bank participation is made.

The geographical IBAN prefix list is based on EPC409-09 v8.0, dated 2025-12-24,
pages 4–5. EPC rulebooks and guidelines remain European Payments Council material;
they are referenced for the implementation, not relicensed or bundled wholesale.

The exact source hashes, resource hashes and retrieval date are recorded in
`resources/manifest.json`. Payment files and caller data remain the caller's data.

## Generator dependency and additional schemas

`digitick/sepa-xml` version 3.2.0, source commit
`1b876013bee9c49d9d9e61e6d17e195898a12037`, is used as a separate, unmodified
Composer dependency. Its code is LGPL-3.0-or-later, not MIT. Its original LICENSE,
source headers and Composer attribution are preserved under `vendor/digitick/sepa-xml`
when installed, including in our Docker image. Source and license:
https://github.com/php-sepa-xml/php-sepa-xml/tree/3.2.0.
Our adapter and typed/validation code do not replace or modify that dependency.

`resources/schemas/` includes unchanged ISO/legacy SEPA national-profile schemas
from that pinned release, except `pain.001.001.09`, which uses our original direct
ISO download. Schemas retain their headers and standards-owner provenance; they
are not represented as our MIT code. The national-profile variants include German
DFÜ/EPC constraints and are not described as unmodified generic ISO schemas.

Additional schemas were retrieved directly from the ISO Registration Authority:

- pain.001.001.11: https://www.iso20022.org/message/22096/download
- pain.001.001.13: https://www.iso20022.org/message/23503/download
- pain.008.001.12: https://www.iso20022.org/message/23506/download
- pain.002.001.10: https://www.iso20022.org/message/14396/download
- pain.002.001.15: https://www.iso20022.org/message/23504/download

Their original content is preserved and their SHA-256 hashes are recorded in the
resource manifest. The ISO repository IPR policy linked above applies. Build tools
and base images retain their respective licenses.
