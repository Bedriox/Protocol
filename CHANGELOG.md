# Changelog

- Centralize the current passenger seat offset and rotation metadata used by complete vehicle projections.
- Add typed current-protocol Shulker presentation and End Crystal beam-target/show-bottom metadata, including bounded integer block positions.
- Add the fixed-schema bidirectional protocol-2193 boss-event packet with typed actions, colors and overlays, bounded titles, finite progress, and current client query admission.
- Add the bounded protocol-2193 change-dimension packet, project the active dimension in StartGame, and close the client acknowledgement and loading-screen conversation through existing typed packets.
- Project current boat buoyancy metadata so controlling clients retain water
  physics while predicting mounted movement.

- Decode and encode the current mounted `PlayerAuthInput` vehicle rotation and
  predicted vehicle actor ID instead of rejecting ordinary rideable movement.
- Name the current riding actor flag so vehicle links can project complete passenger metadata.
- Name the current powered, ignited, wall-climbing, angry, charged, and fire-immune actor metadata flags and the explosion sound used by hostile-mob projection.
- Expose the current actor metadata flag used for sheared entities.
- Add typed current-protocol fishing-hook owner metadata for authoritative cast and reel projection.
- Accept the current optional-recipe zero network-ID sentinel used by anvils without weakening other recipe-ID validation.
- Add a typed protocol-2193 experience-orb actor factory with its required positive experience-value metadata.
- Add bounded smithing transform and trim recipe sections, enchanting options, map requests and updates, and protocol-2193 furnace display preferences for processing-station gameplay.
- Migrate block network IDs to canonical signed 32-bit hashes, advertise hash mode in StartGame, and project the same bit pattern through each packet's required signed or unsigned VarInt encoding.
- Add an explicit `BlockNetworkId` value object and negative-hash coverage for chunks, block updates, inventory, creative inventory, particles, transactions, and stack-request craft results.
- Add typed current weather level events for rain and thunder transitions.
- Add bounded protocol-2193 actor-effect synchronization, named-particle spawning, and typed level-event particles with color, block, item, scalar, direction, and size payload factories.
- Add typed potion and container mixing registries plus brewing-stand and furnace progress synchronization.
- Name the current actor on-fire metadata flag for authoritative entity combustion updates.
- Add the bounded protocol-2193 non-player actor conversation with generic actor spawning, dynamic properties, vehicle/passenger links, generic attribute updates and modifiers, and the complete current actor-event domain.
- Add bounded block-actor data, block state events, dynamic-container cleanup, the complete named container-slot domain, and protocol-2193 item-stack response slots for persistent storage conversations.
- Add bounded crafting recipe snapshots for shaped, shapeless, user-data-aware, chemistry, and multi recipes, plus the complete current crafting stack-request action family and named crafting containers.
- Add the current armor and offhand equipment synchronization surface, bounded nutrition attributes, item-use actor events, and exact item-release semantics.
- Qualify the complete protocol-2193 creative content and selection shapes with damage, item user data, block-state runtime IDs, stable unsigned creative IDs, standalone requests, and embedded player-input requests.
- Add bounded hard and soft command enums, command aliases, multiple typed overload projections, and packet 114 soft-enum updates for live client suggestions.
- Add validated pre-packed block and biome palette storage so chunk serializers can write canonical Bedrock word arrays directly without changing existing expanded-storage vectors.
- Identify failed item-stack request actions by bounded action type, index, byte offset, and fixed failure category without retaining packet bytes.
- Correct the item-stack request action marker for current creative, mine-block, and craft-results actions; the marker is the action enum ordinal, not a duplicate wire type.
- Decode and encode bounded deprecated craft-results actions in creative item-stack requests without treating client-reported results as inventory authority.
- Corrected creative-content group references to use the current zero-based wire index.

All notable changes will be documented here. The format follows Keep a Changelog and releases will use Semantic Versioning.

## [0.1.0-alpha.1] - 2026-09-17

### Fixed

- Include the required unsigned client tick in protocol-2193 `SetActorMotionPacket` encoding and decoding.
- Correct the protocol-2193 command-origin layout for requests and outputs to use a bounded string origin, UUID, request ID, and unconditional signed little-endian 64-bit player actor ID.
- Restore the protocol-2193 MovePlayer teleport-metadata presence marker and reject marker/mode mismatches before decoding conditional fields.
- Permit the current optional angular-velocity field for both player and vehicle movement corrections.
- Project the authoritative saved pitch and yaw into StartGame instead of resetting the reconnecting player's view to zero rotation.
- Correct the protocol-2193 hotbar, inventory, cursor, and created-output container-name IDs so authoritative retail inventory requests route to their intended containers.
- Advertise the authoritative new inventory system in fixed-flat StartGame so retail clients use item-stack requests for inventory moves and splits.
- Decode and encode current `PlayerAuthInput` stop-destroy actions without nonexistent position and face fields, preserving alignment of following optional input data.
- Documented source ownership, packet-boundary contracts, and mandatory regression-preservation checks for protocol changes.

### Changed

- Represent player and command permission levels in ability data with closed enums, and provide exact member and operator survival ability projections.
- Default fixed-flat StartGame to 40 ticks of bounded movement rewind history while retaining server-authoritative block breaking.
- Retain bounded client-reported `DeviceOS` privately while making peer-facing build platform a closed wire value defaulting to unknown (`-1`).
- Embed a complete initialized player actor-data snapshot directly in `AddPlayerPacket`, with later actor-data packets reserved for changes.
- Correct the current baseline-player collision and gravity actor-flag indexes to 48 and 49 so survival clients apply ordinary grounded physics.
- Admitted protocol-2193 vertical-fly-speed requests and corrected survival ability values without changing their capability mask or speeds.
- Keep commit messages free of personal-email sign-off trailers.
- Mark baseline players as breathing, collidable, and gravity-affected using semantic current-protocol actor flags.

### Added

- Added the typed current ability domain and safe ability-layer mask construction and query helpers.
- Added typed fixed-flat StartGame player and level game modes while preserving the survival default payload.
- Added the bounded protocol-2193 named level-sound event packet with verified hit, break, and place sound names.
- Added typed, bounded protocol-2193 creative-content groups and entries with the current one-byte category layout.
- Added closed game-type and block-interaction level-event semantics plus update-game-type and request-permissions packet codecs.
- Added typed drop, destroy, consume, create, mine-block, and creative-craft item-stack request actions without changing the established take/place/swap API.
- Added the bounded dropped-item actor spawn, delta-movement, collection, and removal packet conversation.
- Added every remaining protocol-2193 text display variant plus complete title, subtitle, action-bar, timing, clear/reset, JSON-title, and toast-notification packet support.
- Added bounded protocol-2193 system and translated text packet shapes with typed union and text-type discriminators.
- Added the bounded current `SetActorMotionPacket` used for authoritative entity velocity and combat knockback projection.
- Added the complete current command packet conversation with typed packet IDs, command origins, permissions, output modes, standard argument types, bounded command declarations, requests, and responses.
- Added a typed, bounded protocol-2193 `MovementPredictionSyncPacket` for the current client movement-property notification, including its complete actor-flag bitset, finite property values, unsigned runtime actor ID, and flying marker.
- Added bounded protocol-2193 actor lifecycle, death-information, and bidirectional respawn codecs with current optional actor-event fire-position framing.
- Added typed, bounded protocol-2193 take, place, and swap stack requests in both packet 147 and embedded packet 144, plus authoritative success response containers and slots for packet 148.
- Added typed non-empty `InventoryContentPacket` snapshots, protocol-2193 `InventorySlotPacket` corrections, optional full-container-name framing, and retained typed inventory actions for packed `PlayerAuthInput` item use.
- Added one shared bounded actor-metadata collection codec plus current vector metadata for player collision boxes.
- Added the bounded bidirectional protocol-2193 `PlayerSkinPacket` so retail appearance synchronization no longer falls through the packet registry.
- Added typed protocol-2193 absolute peer-actor movement, current movement header flags, and baseline-preserving sneaking/sprinting metadata composition.
- Added the typed protocol-2193 `RemoveActorPacket` for bounded multiplayer departure and peer-view cleanup.
- Added typed, bounded protocol-2193 container-open, exact-empty server-settings-request, and current movement-rewind codecs.
- Added bounded protocol-2193 codecs for bidirectional emote notifications and clientbound level-event and block-update packets, with typed flags and exact literal vectors.
- Added a typed, bounded protocol-2193 packet-30 inventory-transaction codec covering every official transaction variant, exact source/item framing, and adversarial validation without applying game state.
- Add an immutable, bounded protocol-2193 Bedrock server advertisement with semantic game modes, typed Nintendo-limited encoding, exact literal vectors, and no RakNet transport dependency.
- Added configurable signed 64-bit world seeds and bounded signed 32-bit world-spawn coordinates to the fixed-flat StartGame factory while preserving its existing defaults.
- Added a bidirectional, bounded protocol-2193 `EmoteListPacket` codec for actor runtime IDs and canonical emote-piece UUID lists.
- Added bounded StartGame data-driven block properties and required experiment declarations, retained biome tags, and typed PlayerAuthInput block-action and packed item-use projections for protocol 2193.
- Added a bounded generic full-column chunk model and serializer with v9 signed-Y sections, one or more block layers, every current Bedrock palette width, complete biome columns, safe border framing, and bounded block-entity network NBT; the fixed-flat factories now use the same path.
- Added semantic v9 terrain reconstruction covering every fixed-flat cell, palette entry, result class, and protocol-2193 current/render height map.
- Added bounded common item-stack action parsing, request-ID projection, inventory-option notifications, and explicit protocol-2193 error responses without container mutation.
- Added bounded Minecraft 1.26.50 / protocol-2193 compatibility alongside 1.26.45 / protocol 2169, including negotiated PlayerAuthInput framing and version-specific StartGame/resource-pack declarations.
- Added bounded SubChunkRequest and SubChunk response codecs, biome-only LevelChunk shells, v9 fixed-flat section payloads, and protocol-aware current/render height maps for on-demand terrain delivery.

- Initial PHP 8.4 package, bounded unsigned VarInt codec, tests, documentation, and CI baseline.
- Added immutable capacity-bounded byte-buffer reader and writer APIs.
- Added canonical unsigned/signed VarInt and VarLong codecs, including 32/64-bit ZigZag and an unsigned 64-bit limb value.
- Added little-endian fixed-width integers, floats, doubles, bounded UTF-8 strings, deterministic codec exceptions, and boundary/adversarial tests.
- Added an explicit 64-bit PHP platform requirement plus usage and troubleshooting documentation.
- Added bounded immutable packet codecs for the protocol 975 / Minecraft 1.26.20 pre-spawn login slice, including network settings, structured authentication envelopes, encryption handshakes, disconnects, and resource-pack negotiation.
- Added clean-room protocol-975 generic packet headers, VarInt-length batches, `0xfe` envelopes, explicit login-phase compression modes, bounded decompression, and adversarial framing tests.
- Added bounded JOSE parsing, strict base64url and DER conversion, OpenSSL-backed ES384/P-384 signing, verification, ECDH and session-key derivation, injectable ephemeral-key generation, and protocol-975 handshake JWT construction without authentication policy.
- Added a repository-owned, secret-free OpenSSL test configuration and made skipped, incomplete, or risky cryptographic tests fail the PHPUnit quality gate.
- Added stateful protocol-975 AES-256-CTR encryption framing with independent directional state, bounded authenticated batches, unsigned packet counters, fail-closed integrity handling, and independent known-answer vectors.
- Added bounded legacy three-token identity-chain and explicit one-token self-signed verification, plus signature-first client-data JWT validation with immutable safe identity and appearance values.
- Added threshold-aware per-batch negotiated-zlib dispatch and a misuse-resistant protocol-975 encrypted-envelope codec that keeps the `0xfe` marker outside the cipher stream.
- Defined threshold zero as compressing every non-empty negotiated-zlib batch, closed protocol-975 encoding to registered packet types, rejected empty client-data JWTs, and added independent non-empty resource-pack wire vectors and count-overflow coverage.
- Restricted the stateful encryption API to complete `0xfe` envelopes so callers cannot accidentally encrypt the clear marker.
- Added bounded protocol-975 codecs for basic movement, strict chat, chunk-radius negotiation, local-player initialization, chunk publication, non-cache level-chunk envelopes, classic player-list add/remove, and the empty-gameplay-state AddPlayer variant.
- Added clientbound protocol-975 StartGame, actor identifiers, biome definitions, item-component registry, and canonical fixed-flat LevelChunk factories for Bedriox/Data inputs.
- Added bounded protocol-975 PlayerAuthInput movement decoding, conditional-payload rejection, client-tick/input projections, and a shared player eye-height conversion.
- Added an isolated Minecraft 1.26.45 / protocol-2169 login, encryption, fixed-flat bootstrap, player-list/skin, movement, and chat codec slice with adversarial and known-vector coverage.
- Added the protocol-2169 one-byte `ClientCacheStatusPacket` used during retail login.
- Added bounded protocol-2169 empty `JigsawStructureDataPacket` and `VoxelShapesPacket` bootstrap registries required before `StartGamePacket`.
- Added the bounded protocol-2169 `ServerboundLoadingScreenPacket` transition codec used during initial world entry.
- Added an empty protocol-2169 `CreativeContentPacket` for minimal startup profiles without inventory content.
- Added bounded protocol-2169 `InteractPacket` decoding for modern actions 3 through 6, including the ordinary inventory-open notification.
- Added typed, bounded protocol-2169 actor metadata and a baseline self-player snapshot covering air, identity, dimensions, health, flags, scale, and lead state.
- Added typed current-release `PlayerAuthInputFlag` values, jump and collision projections, predicted-velocity semantics, and unsigned comparison for authoritative client-tick admission.
- Added bounded routine-client codecs for player actions, container closure, network-stack latency, and ability requests, plus typed action/value domains and safe animation/latency helpers.

### Changed

- Corrected packet 31 MobEquipment to use the shared protocol-2193 network item descriptor, including the canonical zero-count empty item, and retain bounded typed non-empty equipment notifications.
- Corrected the PlayerAuthInput item-use descriptor to the protocol-2193 single optional signed stack-network-ID field and aligned its inventory-action bound to 100.
- Encode every LevelChunk subchunk with a block layer, using a singleton air palette for empty sections, and enforce the official 64-section and 65-cache-entry bounds.
- Made Minecraft 1.26.50 / protocol 2193 the sole wire target, qualified Minecraft 1.26.51 as a same-protocol retail client, and removed the protocol-2169 PlayerAuthInput and SubChunk branches.
- Encode one biome palette followed by copy-last sentinels and derive the request limit from fixed-world section bounds.
- Corrected `PlayerAuthInputPacket` conditional-payload indexes and nested presence decoding, and accept the valid absent input-data representation as an empty flag set.
- Accept the bounded Interact action-byte domain shared by current schema ordinals and retail legacy-valued notifications while retaining exact structural validation.
- Name MovePlayer mode 1 `RESPAWN`, matching the current packet schema; teleport mode 2 remains distinct.
- Replaced parallel versioned packet and encryption APIs with the sole unversioned `Packet` namespace, `BedrockPacketCodec`, `BedrockEncryptor`, `BedrockDecryptor`, and `BedrockEncryptedEnvelopeCodec`; compatibility is centralized in `ProtocolVersion`.
- Aligned protocol-2169 startup state with independently verified wire behavior: ItemV4 empty descriptors now include the required empty stack-network ID, StartGame uses signed block-position coordinates, the byte-sized member permission field, and current vanilla defaults, and player spawn uses the player-spawn discriminator.
- Aligned protocol-2169 admission data with the retail schema: retain and emit the signed client `ProfileHash`, build the available-actor network NBT from admitted server actor types, and encode full-column biome storage with the conservative V2 palette shape.
- Corrected the protocol-2169 `StartGamePacket` level-settings layout by serializing the editor-export flag before the day-cycle lock time.
- Added the complete bounded survival game-rule snapshot to `StartGamePacket` and `GameRulesChangedPacket`, and named every protocol-2169 item-component NBT root with its item identifier.
- Separated the protocol-2169 client chunk-radius wire domain from the server's bounded authorization policy so retail-scale requests can be decoded and safely clamped by the consumer.
- Hardened JOSE extension and P-384 scalar validation, required matching key-pair coordinates, fixed the legacy online root at certificate index 1, rejected null time claims, aligned client appearance limits with the 1 MiB login envelope, and separated online XUID requirements from explicit self-signed identity behavior.
- Enabled named rootless construction for explicit self-signed legacy verification while requiring an injected pinned root before any online certificate input is processed.
- Retained bounded signed appearance metadata and animation expression types in `VerifiedClientData` for future player-list serialization without preserving raw JWTs.
- Accept the exact JSON `null` geometry used by retail Bedrock for skins without custom geometry while retaining object-only validation for non-null geometry documents.

### Removed

- Removed the obsolete protocol-975 packet, encryption, test, and dedicated guide surface; Bedriox now replaces its one supported Bedrock target on upgrade.
