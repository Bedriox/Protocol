# Testing

Run all local checks with:

```console
composer check
```

`composer test` runs PHPUnit and `composer analyse` runs PHPStan at maximum level. Protocol work must add minimum/maximum boundary, truncation at every meaningful width, numerical overflow, capacity overflow, canonical encoding, offset, immutable-state, round-trip, and golden-wire tests. Security-sensitive parsers should also receive fuzz/property coverage as the harness is introduced.

CI validates Composer metadata, installs locked constraints, audits dependencies, runs tests, and performs static analysis on Linux and Windows. A successful single connection is not a compatibility test.

Batch coverage includes literal packet-header and length-prefix vectors, independently generated RFC 1950 and raw-DEFLATE bytes, negotiated-zlib threshold boundaries for both `0x00` and `0xff` (including threshold zero), meaningful truncation points, trailing data, rejected Snappy/unknown algorithms, size/ratio bombs, count limits, and a deterministic 200-packet property sequence.

Terrain coverage independently parses complete LevelChunk columns, requires layer zero in every section, resolves singleton air sections, reconstructs all 4,096 occupied-section X-Z-Y cells, resolves every palette index, verifies biome copy-last behavior (including biome zero), and parses both protocol-2193 height maps. Generic-column tests independently reconstruct all cells for every supported `1/2/3/4/5/6/8/16`-bit palette width, including padded word layouts; cover negative chunk coordinates and signed subchunk Y; verify multiple layers, complete biome framing, the zero retail border count, concatenated bounded block-entity roots, and every public count/range boundary. Inventory-safety coverage exercises packet 307 enum bounds, exact packet 148 error vectors, all six supported common item-stack action layouts, truncation, mismatched type markers, unsupported actions, and count overflow.

Packet coverage includes independent non-empty ResourcePacksInfo and ResourcePackStack byte vectors with non-palindromic UUIDs and distinguishable flags, plus exact maximum-count rejection for all resource-pack and experiment lists. StartGame coverage verifies data-driven block-property NBT placement, required experiments, duplicates, and byte/count limits. Biome coverage verifies retained tags, while PlayerAuthInput coverage exercises typed block actions, packed item-use transactions, combined optionals, truncation, and adversarial counts. Emote-list coverage uses a manually composed two-UUID vector, tests every truncation boundary, accepts the exact 4,096-entry limit, and rejects the next count before allocation. The registry is tested as closed in both directions, and Login rejects an empty client-data JWT on caller and wire paths.

Encryption coverage includes independent continuous-CTR known answers, packet-counter rollover, integrity failure, replay/reordering, limits, close behavior, and an outer-envelope vector proving that `0xfe` remains clear while only the compressed batch and trailer are encrypted.
