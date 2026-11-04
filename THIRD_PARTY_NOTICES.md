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
