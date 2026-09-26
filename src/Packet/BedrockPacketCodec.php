<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\ProtocolVersion;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** State-independent packet-payload registry for the supported Bedrock release. */
final class BedrockPacketCodec
{
    public const int PROTOCOL_VERSION = ProtocolVersion::CURRENT;
    public const string MINECRAFT_VERSION = ProtocolVersion::GAME_VERSION;

    public static function encode(Packet $packet, int $protocolVersion = ProtocolVersion::CURRENT): string
    {
        self::requireSupportedProtocol($protocolVersion);
        self::packetId($packet);
        return $packet instanceof PlayerAuthInputPacket
            ? $packet->encodeForProtocol($protocolVersion)
            : ($packet instanceof SubChunkPacket ? $packet->encodeForProtocol($protocolVersion) : $packet->encode());
    }

    public static function decode(int $packetId, string $payload, int $protocolVersion = ProtocolVersion::CURRENT): Packet
    {
        self::requireSupportedProtocol($protocolVersion);
        return match ($packetId) {
            PacketIds::REQUEST_NETWORK_SETTINGS => RequestNetworkSettingsPacket::decode($payload),
            PacketIds::NETWORK_SETTINGS => NetworkSettingsPacket::decode($payload),
            PacketIds::LOGIN => LoginPacket::decode($payload),
            PacketIds::PLAY_STATUS => PlayStatusPacket::decode($payload),
            PacketIds::SERVER_TO_CLIENT_HANDSHAKE => ServerToClientHandshakePacket::decode($payload),
            PacketIds::CLIENT_TO_SERVER_HANDSHAKE => ClientToServerHandshakePacket::decode($payload),
            PacketIds::DISCONNECT => DisconnectPacket::decode($payload),
            PacketIds::RESOURCE_PACKS_INFO => ResourcePacksInfoPacket::decode($payload),
            PacketIds::RESOURCE_PACK_STACK => ResourcePackStackPacket::decode($payload),
            PacketIds::RESOURCE_PACK_CLIENT_RESPONSE => ResourcePackClientResponsePacket::decode($payload),
            PacketIds::TEXT => TextPacketCodec::decode($payload),
            PacketIds::SET_TITLE => SetTitlePacket::decode($payload),
            PacketIds::AVAILABLE_COMMANDS => AvailableCommandsPacket::decode($payload),
            PacketIds::COMMAND_REQUEST => CommandRequestPacket::decode($payload),
            PacketIds::COMMAND_OUTPUT => CommandOutputPacket::decode($payload),
            PacketIds::ANIMATE => AnimatePacket::decode($payload),
            PacketIds::ADD_PLAYER => AddPlayerPacket::decode($payload),
            PacketIds::ADD_ACTOR => AddActorPacket::decode($payload),
            PacketIds::REMOVE_ACTOR => RemoveActorPacket::decode($payload),
            PacketIds::ADD_ITEM_ACTOR => AddItemActorPacket::decode($payload),
            PacketIds::TAKE_ITEM_ACTOR => TakeItemActorPacket::decode($payload),
            PacketIds::MOVE_ACTOR_ABSOLUTE => MoveActorAbsolutePacket::decode($payload),
            PacketIds::MOVE_ACTOR_DELTA => MoveActorDeltaPacket::decode($payload),
            PacketIds::MOVE_PLAYER => MovePlayerPacket::decode($payload),
            PacketIds::UPDATE_BLOCK => UpdateBlockPacket::decode($payload),
            PacketIds::LEVEL_EVENT => LevelEventPacket::decode($payload),
            PacketIds::BLOCK_EVENT => BlockEventPacket::decode($payload),
            PacketIds::LEVEL_SOUND_EVENT => LevelSoundEventPacket::decode($payload),
            PacketIds::ACTOR_EVENT => ActorEventPacket::decode($payload),
            PacketIds::UPDATE_ATTRIBUTES => UpdateAttributesPacket::decode($payload),
            PacketIds::INVENTORY_TRANSACTION => InventoryTransactionPacket::decode($payload),
            PacketIds::MOB_EQUIPMENT => MobEquipmentPacket::decode($payload),
            PacketIds::MOB_ARMOR_EQUIPMENT => MobArmorEquipmentPacket::decode($payload),
            PacketIds::INTERACT => InteractPacket::decode($payload),
            PacketIds::PLAYER_ACTION => PlayerActionPacket::decode($payload),
            PacketIds::RESPAWN => RespawnPacket::decode($payload),
            PacketIds::SET_ACTOR_DATA => SetActorDataPacket::decode($payload),
            PacketIds::SET_ACTOR_MOTION => SetActorMotionPacket::decode($payload),
            PacketIds::SET_ACTOR_LINK => SetActorLinkPacket::decode($payload),
            PacketIds::LEVEL_CHUNK => LevelChunkPacket::decode($payload),
            PacketIds::PLAYER_LIST => PlayerListPacketCodec::decode($payload),
            PacketIds::PLAYER_SKIN => PlayerSkinPacket::decode($payload),
            PacketIds::CONTAINER_OPEN => ContainerOpenPacket::decode($payload),
            PacketIds::CONTAINER_CLOSE => ContainerClosePacket::decode($payload),
            PacketIds::INVENTORY_CONTENT => InventoryContentPacket::decode($payload),
            PacketIds::INVENTORY_SLOT => InventorySlotPacket::decode($payload),
            PacketIds::CRAFTING_DATA => CraftingDataPacket::decode($payload),
            PacketIds::BLOCK_ACTOR_DATA => BlockActorDataPacket::decode($payload),
            PacketIds::REQUEST_CHUNK_RADIUS => RequestChunkRadiusPacket::decode($payload),
            PacketIds::CHUNK_RADIUS_UPDATED => ChunkRadiusUpdatedPacket::decode($payload),
            PacketIds::SET_PLAYER_GAME_TYPE => SetPlayerGameTypePacket::decode($payload),
            PacketIds::SERVER_SETTINGS_REQUEST => ServerSettingsRequestPacket::decode($payload),
            PacketIds::SET_LOCAL_PLAYER_AS_INITIALIZED => SetLocalPlayerAsInitializedPacket::decode($payload),
            PacketIds::UPDATE_SOFT_ENUM => UpdateSoftEnumPacket::decode($payload),
            PacketIds::NETWORK_STACK_LATENCY => NetworkStackLatencyPacket::decode($payload),
            PacketIds::CLIENT_CACHE_STATUS => ClientCacheStatusPacket::decode($payload),
            PacketIds::EMOTE => EmotePacket::decode($payload),
            PacketIds::CORRECT_PLAYER_MOVE_PREDICTION => CorrectPlayerMovePredictionPacket::decode($payload),
            PacketIds::NETWORK_CHUNK_PUBLISHER_UPDATE => NetworkChunkPublisherUpdatePacket::decode($payload),
            PacketIds::PLAYER_AUTH_INPUT => PlayerAuthInputPacket::decodeForProtocol($payload, $protocolVersion),
            PacketIds::SUB_CHUNK_REQUEST => SubChunkRequestPacket::decode($payload),
            PacketIds::ITEM_STACK_REQUEST => ItemStackRequestPacket::decode($payload),
            PacketIds::CREATIVE_CONTENT => CreativeContentPacket::decode($payload),
            PacketIds::UPDATE_PLAYER_GAME_TYPE => UpdatePlayerGameTypePacket::decode($payload),
            PacketIds::EMOTE_LIST => EmoteListPacket::decode($payload),
            PacketIds::REQUEST_ABILITY => RequestAbilityPacket::decode($payload),
            PacketIds::REQUEST_PERMISSIONS => RequestPermissionsPacket::decode($payload),
            PacketIds::DEATH_INFO => DeathInfoPacket::decode($payload),
            PacketIds::TOAST_REQUEST => ToastRequestPacket::decode($payload),
            PacketIds::SERVERBOUND_LOADING_SCREEN => ServerboundLoadingScreenPacket::decode($payload),
            PacketIds::MOVEMENT_PREDICTION_SYNC => MovementPredictionSyncPacket::decode($payload),
            PacketIds::SET_PLAYER_INVENTORY_OPTIONS => SetPlayerInventoryOptionsPacket::decode($payload),
            PacketIds::CONTAINER_REGISTRY_CLEANUP => ContainerRegistryCleanupPacket::decode($payload),
            default => throw new MalformedDataException('Packet ID is not registered in the Bedrock codec.'),
        };
    }

    public static function packetId(Packet $packet): int
    {
        return match ($packet::class) {
            RequestNetworkSettingsPacket::class => PacketIds::REQUEST_NETWORK_SETTINGS,
            NetworkSettingsPacket::class => PacketIds::NETWORK_SETTINGS,
            LoginPacket::class => PacketIds::LOGIN,
            PlayStatusPacket::class => PacketIds::PLAY_STATUS,
            ServerToClientHandshakePacket::class => PacketIds::SERVER_TO_CLIENT_HANDSHAKE,
            ClientToServerHandshakePacket::class => PacketIds::CLIENT_TO_SERVER_HANDSHAKE,
            DisconnectPacket::class => PacketIds::DISCONNECT,
            ResourcePacksInfoPacket::class => PacketIds::RESOURCE_PACKS_INFO,
            ResourcePackStackPacket::class => PacketIds::RESOURCE_PACK_STACK,
            ResourcePackClientResponsePacket::class => PacketIds::RESOURCE_PACK_CLIENT_RESPONSE,
            ChatPacket::class => PacketIds::TEXT,
            SystemTextPacket::class => PacketIds::TEXT,
            TranslatedTextPacket::class => PacketIds::TEXT,
            TextPacket::class => PacketIds::TEXT,
            SetTitlePacket::class => PacketIds::SET_TITLE,
            SetTimePacket::class => PacketIds::SET_TIME,
            StartGamePacket::class => PacketIds::START_GAME,
            AddPlayerPacket::class => PacketIds::ADD_PLAYER,
            AddActorPacket::class => PacketIds::ADD_ACTOR,
            RemoveActorPacket::class => PacketIds::REMOVE_ACTOR,
            AddItemActorPacket::class => PacketIds::ADD_ITEM_ACTOR,
            TakeItemActorPacket::class => PacketIds::TAKE_ITEM_ACTOR,
            MoveActorAbsolutePacket::class => PacketIds::MOVE_ACTOR_ABSOLUTE,
            MoveActorDeltaPacket::class => PacketIds::MOVE_ACTOR_DELTA,
            MovePlayerPacket::class => PacketIds::MOVE_PLAYER,
            UpdateBlockPacket::class => PacketIds::UPDATE_BLOCK,
            LevelEventPacket::class => PacketIds::LEVEL_EVENT,
            BlockEventPacket::class => PacketIds::BLOCK_EVENT,
            LevelSoundEventPacket::class => PacketIds::LEVEL_SOUND_EVENT,
            ActorEventPacket::class => PacketIds::ACTOR_EVENT,
            UpdateAttributesPacket::class => PacketIds::UPDATE_ATTRIBUTES,
            InventoryTransactionPacket::class => PacketIds::INVENTORY_TRANSACTION,
            MobEquipmentPacket::class => PacketIds::MOB_EQUIPMENT,
            MobArmorEquipmentPacket::class => PacketIds::MOB_ARMOR_EQUIPMENT,
            InteractPacket::class => PacketIds::INTERACT,
            PlayerActionPacket::class => PacketIds::PLAYER_ACTION,
            RespawnPacket::class => PacketIds::RESPAWN,
            SetActorDataPacket::class => PacketIds::SET_ACTOR_DATA,
            SetActorMotionPacket::class => PacketIds::SET_ACTOR_MOTION,
            SetActorLinkPacket::class => PacketIds::SET_ACTOR_LINK,
            SetSpawnPositionPacket::class => PacketIds::SET_SPAWN_POSITION,
            AnimatePacket::class => PacketIds::ANIMATE,
            ContainerOpenPacket::class => PacketIds::CONTAINER_OPEN,
            ContainerClosePacket::class => PacketIds::CONTAINER_CLOSE,
            InventoryContentPacket::class => PacketIds::INVENTORY_CONTENT,
            InventorySlotPacket::class => PacketIds::INVENTORY_SLOT,
            CraftingDataPacket::class => PacketIds::CRAFTING_DATA,
            BlockActorDataPacket::class => PacketIds::BLOCK_ACTOR_DATA,
            LevelChunkPacket::class => PacketIds::LEVEL_CHUNK,
            SetCommandsEnabledPacket::class => PacketIds::SET_COMMANDS_ENABLED,
            SetDifficultyPacket::class => PacketIds::SET_DIFFICULTY,
            SetPlayerGameTypePacket::class => PacketIds::SET_PLAYER_GAME_TYPE,
            UpdatePlayerGameTypePacket::class => PacketIds::UPDATE_PLAYER_GAME_TYPE,
            PlayerListRemovePacket::class => PacketIds::PLAYER_LIST,
            PlayerListAddPacket::class => PacketIds::PLAYER_LIST,
            PlayerSkinPacket::class => PacketIds::PLAYER_SKIN,
            RequestChunkRadiusPacket::class => PacketIds::REQUEST_CHUNK_RADIUS,
            ChunkRadiusUpdatedPacket::class => PacketIds::CHUNK_RADIUS_UPDATED,
            ServerSettingsRequestPacket::class => PacketIds::SERVER_SETTINGS_REQUEST,
            GameRulesChangedPacket::class => PacketIds::GAME_RULES_CHANGED,
            AvailableCommandsPacket::class => PacketIds::AVAILABLE_COMMANDS,
            CommandRequestPacket::class => PacketIds::COMMAND_REQUEST,
            CommandOutputPacket::class => PacketIds::COMMAND_OUTPUT,
            SetLocalPlayerAsInitializedPacket::class => PacketIds::SET_LOCAL_PLAYER_AS_INITIALIZED,
            UpdateSoftEnumPacket::class => PacketIds::UPDATE_SOFT_ENUM,
            NetworkStackLatencyPacket::class => PacketIds::NETWORK_STACK_LATENCY,
            ClientCacheStatusPacket::class => PacketIds::CLIENT_CACHE_STATUS,
            EmotePacket::class => PacketIds::EMOTE,
            CorrectPlayerMovePredictionPacket::class => PacketIds::CORRECT_PLAYER_MOVE_PREDICTION,
            AvailableActorIdentifiersPacket::class => PacketIds::AVAILABLE_ACTOR_IDENTIFIERS,
            NetworkChunkPublisherUpdatePacket::class => PacketIds::NETWORK_CHUNK_PUBLISHER_UPDATE,
            BiomeDefinitionListPacket::class => PacketIds::BIOME_DEFINITION_LIST,
            ItemRegistryPacket::class => PacketIds::ITEM_REGISTRY,
            PlayerAuthInputPacket::class => PacketIds::PLAYER_AUTH_INPUT,
            SubChunkPacket::class => PacketIds::SUB_CHUNK,
            SubChunkRequestPacket::class => PacketIds::SUB_CHUNK_REQUEST,
            RequestAbilityPacket::class => PacketIds::REQUEST_ABILITY,
            RequestPermissionsPacket::class => PacketIds::REQUEST_PERMISSIONS,
            CreativeContentPacket::class => PacketIds::CREATIVE_CONTENT,
            ItemStackResponsePacket::class => PacketIds::ITEM_STACK_RESPONSE,
            ItemStackRequestPacket::class => PacketIds::ITEM_STACK_REQUEST,
            EmoteListPacket::class => PacketIds::EMOTE_LIST,
            UpdateAbilitiesPacket::class => PacketIds::UPDATE_ABILITIES,
            UpdateAdventureSettingsPacket::class => PacketIds::UPDATE_ADVENTURE_SETTINGS,
            DeathInfoPacket::class => PacketIds::DEATH_INFO,
            ToastRequestPacket::class => PacketIds::TOAST_REQUEST,
            TrimDataPacket::class => PacketIds::TRIM_DATA,
            ServerboundLoadingScreenPacket::class => PacketIds::SERVERBOUND_LOADING_SCREEN,
            MovementPredictionSyncPacket::class => PacketIds::MOVEMENT_PREDICTION_SYNC,
            JigsawStructureDataPacket::class => PacketIds::JIGSAW_STRUCTURE_DATA,
            VoxelShapesPacket::class => PacketIds::VOXEL_SHAPES,
            SetPlayerInventoryOptionsPacket::class => PacketIds::SET_PLAYER_INVENTORY_OPTIONS,
            ContainerRegistryCleanupPacket::class => PacketIds::CONTAINER_REGISTRY_CLEANUP,
            default => throw new InvalidValueException('Packet type is not registered in the Bedrock codec.'),
        };
    }

    private static function requireSupportedProtocol(int $protocolVersion): void
    {
        if (!ProtocolVersion::supports($protocolVersion)) {
            throw new InvalidValueException('Unsupported Bedrock protocol version.');
        }
    }
}
