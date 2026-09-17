# Development

## Requirements

- PHP 8.4 or later within the supported range.
- Composer 2.

```console
composer install
composer check
```

Use strict types, immutable values where practical, bounded reads, and explicit exceptions. Do not copy implementation code from PocketMine, Nukkit, or other servers. Preserve legally required copyright and license attribution in `NOTICE` or `THIRD_PARTY_NOTICES.md` before committing external material.

Changes to a wire format require tests containing a readable rationale and independently obtained fixtures. Update `CHANGELOG.md` for user-visible changes.

Read [the codec contract](codecs.md) before adding primitives. New decoders must distinguish caller errors, input truncation, capacity exhaustion, and malformed wire data. They must reject non-canonical variable integers and validate bounds before slicing or allocating.
