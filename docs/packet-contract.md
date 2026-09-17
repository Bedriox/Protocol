# Packet contract

The packet boundary performs this finite transformation:

```text
bounded bytes -> canonical packet header -> registered typed decoder
              -> immutable packet value + exact end-of-payload

immutable packet value -> registered typed encoder -> bounded bytes
```

Every decoder validates lengths, counts, enum domains, nesting, finite numbers, allocation limits, and exact payload termination before returning. Unknown packet IDs, unsupported variants, malformed fields, and trailing bytes fail deterministically. The Bedriox consumer separately decides whether a valid packet is legal in the current phase, is a harmless typed notification, becomes an immutable command, or closes that session.

A packet addition or change requires:

- an explicit registry entry and unversioned semantic name;
- independently constructed exact wire vectors rather than production-only round trips;
- minimum, maximum, truncation, overflow, invalid enum/count, and trailing-data coverage;
- declared decode and encode limits before allocation;
- consumer tests for every affected phase and journey;
- compatibility documentation updates when the wire authority changes.

Do not register unknown bodies as opaque no-ops. A not-yet-implemented gameplay action may be decoded only when its complete bounded structure is known; ignoring or rejecting it is server policy.
