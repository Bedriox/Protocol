# Contributing

This project is in private foundation development. Open an issue before substantial work. Read the [codebase map](docs/codebase-map.md), [packet contract](docs/packet-contract.md), and [change-safety guide](docs/change-safety.md) before editing.

## Workflow

1. Identify the exact wire behavior and the owning Protocol namespace. Server policy, transport, mutable gameplay state, and registry admission belong elsewhere.
2. Record the current relevant test result and list the public APIs, golden vectors, packet IDs, and Bedriox journeys that must remain unchanged.
3. Establish wire facts from a primary specification, a pinned compatible reference, or privacy-safe retail observation, and preserve any legally required attribution when external material is admitted.
4. Implement the smallest typed and bounded change. Never accept opaque trailing data or weaken a shared decoder to accommodate one packet.
5. Add exact independent vectors, every meaningful truncation case, range/count/size failures, trailing-data rejection, and a regression test for the motivating defect.
6. Run `composer check`, `composer validate --strict`, and `composer audit --locked`. For consumer-visible work, also run Bedriox's affected tests and workspace verifier.
7. Update the relevant guide and `CHANGELOG.md`, inspect the final diff, and include compatibility and rollback effects in the review description.

Keep changes focused. Unrelated cleanup, packet renaming, version changes, and registry changes require separate justification and review. A successful local encode/decode round trip is not compatibility evidence.

Contributions are accepted under the same GPL-3.0-only license as the repository (inbound = outbound). By submitting a contribution, you represent that you have the right to do so. Do not submit decompiled, leaked, proprietary, GPL-incompatible, or copied server code. No contributor license agreement is currently required.

Be respectful and follow the [Code of Conduct](CODE_OF_CONDUCT.md).
