# Change safety

Protocol changes can break discovery-to-play compatibility without producing a PHP error. Preserve working behavior deliberately.

## Required change record

Before editing, record the objective, owning classes, affected packet IDs or public APIs, current protocol authority, baseline tests, protected vectors, and Bedriox journeys. The protected journey includes login, encryption, initialization, terrain, movement, chat, interaction, disconnect, and reconnect wherever the change can reach them.

During implementation, change only the owning layer. Add a regression that reproduces the original failure, then add adversarial and exact-vector coverage. A reference implementation may establish a documented wire fact; its code, fixtures, registries, and architecture are not copied.

Before handoff, run:

```shell
composer check
composer validate --strict
composer audit --locked
```

For a consumer-visible change, run affected Bedriox tests and the workspace verifier. Compare protected golden vectors, review the final diff for unrelated edits, update documentation and the changelog, and document rollback to the prior component pin. Never redefine an existing packet or protocol declaration merely to make one observation pass.
