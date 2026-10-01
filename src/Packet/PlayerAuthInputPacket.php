<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\SignedVarInt;
use Bedriox\Protocol\Codec\UnsignedVarInt;
use Bedriox\Protocol\Codec\UnsignedVarLong;
use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\ProtocolVersion;
use Bedriox\Protocol\Value\UnsignedLong;

/** Bounded Bedrock PlayerAuthInput projection with typed supported actions. */
final readonly class PlayerAuthInputPacket implements Packet
{
    /** @var list<int> */
    private const array CONDITIONAL_PAYLOAD_FLAGS = [34, 35, 36, 45];

    /** @var list<int> */
    private const array UNSUPPORTED_CONDITIONAL_PAYLOAD_FLAGS = [34];

    private const int MAX_BLOCK_ACTIONS = 100;

    /** @var array<int, true> */
    private array $inputFlagSet;

    /** @param list<int> $inputFlags */
    public function __construct(
        public float $pitch, public float $yaw,
        public float $wireX, public float $wireY, public float $wireZ,
        public float $moveX, public float $moveZ, public float $headYaw,
        public array $inputFlags, public int $inputMode, public int $playMode, public int $interactionMode,
        public float $interactPitch, public float $interactYaw, public UnsignedLong $tick,
        public float $deltaX, public float $deltaY, public float $deltaZ,
        public float $analogMoveX, public float $analogMoveZ,
        public float $cameraX, public float $cameraY, public float $cameraZ,
        public float $rawMoveX, public float $rawMoveZ,
        public bool $ignoredOptionalPayload = false,
        public ?int $itemStackRequestId = null,
        /** @var null|list<PlayerBlockAction> Null means absent; an empty list is a present empty action list. */
        public ?array $blockActions = null,
        public ?PlayerItemUseTransaction $itemUseTransaction = null,
        public ?ItemStackRequest $itemStackRequest = null,
        public ?float $vehicleRotationPitch = null,
        public ?float $vehicleRotationYaw = null,
        public ?int $predictedVehicleActorId = null,
    ) {
        foreach ([$pitch, $yaw, $wireX, $wireY, $wireZ, $moveX, $moveZ, $headYaw, $interactPitch, $interactYaw,
            $deltaX, $deltaY, $deltaZ, $analogMoveX, $analogMoveZ, $cameraX, $cameraY, $cameraZ, $rawMoveX, $rawMoveZ] as $value) {
            CodecSupport::validateFiniteFloat($value, 'PlayerAuthInput float');
        }
        if (!array_is_list($inputFlags) || count($inputFlags) > PlayerAuthInputFlag::COUNT
            || $inputFlags !== array_values(array_unique($inputFlags))) {
            throw new InvalidValueException('PlayerAuthInput flags must be a unique bounded list.');
        }
        foreach ($inputFlags as $flag) {
            if (PlayerAuthInputFlag::tryFrom($flag) === null) {
                throw new InvalidValueException('PlayerAuthInput contains an unsupported flag.');
            }
        }
        $this->inputFlagSet = array_fill_keys($inputFlags, true);
        if ($inputMode < 0 || $inputMode > 4 || !in_array($playMode, [0, 1, 2, 7], true)
            || $interactionMode < 0 || $interactionMode > 2) {
            throw new InvalidValueException('PlayerAuthInput mode is outside the Bedrock range.');
        }
        if (($itemStackRequestId !== null || $itemStackRequest !== null) && !$ignoredOptionalPayload) {
            throw new InvalidValueException('An item-stack request ID requires an ignored optional payload.');
        }
        if ($itemStackRequest !== null && $itemStackRequestId !== $itemStackRequest->requestId) {
            throw new InvalidValueException('Item-stack request and projected request ID disagree.');
        }
        if ($blockActions !== null) {
            if (!$ignoredOptionalPayload || !array_is_list($blockActions) || count($blockActions) > self::MAX_BLOCK_ACTIONS) {
                throw new InvalidValueException('PlayerAuthInput block actions must be a bounded present optional payload.');
            }
            foreach ($blockActions as $action) {
                if (!$action instanceof PlayerBlockAction) {
                    throw new InvalidValueException('PlayerAuthInput block actions must be typed values.');
                }
            }
        }
        $hasPredictedVehicle = $this->hasInput(PlayerAuthInputFlag::IsInClientPredictedVehicle);
        if (($vehicleRotationPitch !== null || $vehicleRotationYaw !== null || $predictedVehicleActorId !== null)
            && !$hasPredictedVehicle) {
            throw new InvalidValueException('Predicted vehicle data requires the mounted input flag.');
        }
        if ($hasPredictedVehicle
            && ($vehicleRotationPitch === null || $vehicleRotationYaw === null || $predictedVehicleActorId === null)) {
            throw new InvalidValueException('Mounted input requires complete predicted vehicle data.');
        }
        if ($vehicleRotationPitch !== null && $vehicleRotationYaw !== null) {
            CodecSupport::validateFiniteFloat($vehicleRotationPitch, 'PlayerAuthInput vehicle pitch');
            CodecSupport::validateFiniteFloat($vehicleRotationYaw, 'PlayerAuthInput vehicle yaw');
        }
    }

    public function packetId(): int { return PacketIds::PLAYER_AUTH_INPUT; }
    public function feetY(): float { return PlayerPositionProjection::wireToFeetY($this->wireY); }
    public function hasInput(PlayerAuthInputFlag $flag): bool { return isset($this->inputFlagSet[$flag->value]); }
    /** @return list<PlayerAuthInputFlag> */
    public function typedInputFlags(): array
    {
        return array_map(static fn (int $flag): PlayerAuthInputFlag => PlayerAuthInputFlag::from($flag), $this->inputFlags);
    }
    /** True while either the simulated or raw jump state is held. */
    public function jumpHeld(): bool
    {
        return $this->hasInput(PlayerAuthInputFlag::Jumping) || $this->hasInput(PlayerAuthInputFlag::JumpCurrentRaw);
    }
    /** One-tick jump edge suitable for starting an authoritative jump. */
    public function jumpPressed(): bool
    {
        return $this->hasInput(PlayerAuthInputFlag::StartJumping) || $this->hasInput(PlayerAuthInputFlag::JumpPressedRaw);
    }
    public function jumpReleased(): bool { return $this->hasInput(PlayerAuthInputFlag::JumpReleasedRaw); }
    /** Client collision hint only; the authoritative simulation must determine grounded state. */
    public function horizontalCollisionHint(): bool { return $this->hasInput(PlayerAuthInputFlag::HorizontalCollision); }
    /** Client collision hint only; the authoritative simulation must determine grounded state. */
    public function onGroundHint(): bool { return $this->hasInput(PlayerAuthInputFlag::VerticalCollision); }
    /** @return array{x: float, y: float, z: float} Client-predicted velocity at the end of this input tick. */
    public function predictedVelocity(): array { return ['x' => $this->deltaX, 'y' => $this->deltaY, 'z' => $this->deltaZ]; }

    public function encode(): string
    {
        return $this->encodeForProtocol(ProtocolVersion::CURRENT);
    }

    public function encodeForProtocol(int $protocolVersion): string
    {
        self::requireSupportedProtocol($protocolVersion);
        if (array_intersect($this->inputFlags, self::UNSUPPORTED_CONDITIONAL_PAYLOAD_FLAGS) !== []
            || ($this->itemStackRequestId !== null && $this->itemStackRequest === null)) {
            throw new InvalidValueException('Encoding PlayerAuthInput optional action payloads is unsupported.');
        }
        $hasBlockActions = $this->hasInput(PlayerAuthInputFlag::PerformBlockActions);
        $hasStackRequest = $this->hasInput(PlayerAuthInputFlag::PerformItemStackRequest);
        $hasPredictedVehicle = $this->hasInput(PlayerAuthInputFlag::IsInClientPredictedVehicle);
        if ($this->itemUseTransaction !== null || $hasBlockActions !== ($this->blockActions !== null)
            || $hasStackRequest !== ($this->itemStackRequest !== null)
            || $hasPredictedVehicle !== ($this->predictedVehicleActorId !== null)
            || ($this->ignoredOptionalPayload && !$hasBlockActions && !$hasStackRequest && !$hasPredictedVehicle)) {
            throw new InvalidValueException('PlayerAuthInput optional payload presence does not match its input flags.');
        }
        $optionalPayloads = CodecSupport::writer()->writeUnsignedByte(0);
        $optionalPayloads = CodecSupport::writeBoolean($optionalPayloads, $this->itemStackRequest !== null);
        if ($this->itemStackRequest !== null) {
            $optionalPayloads = ItemStackRequestCodec::writeEntry($optionalPayloads, $this->itemStackRequest);
        }
        if ($this->blockActions === null) {
            $optionalPayloads = $optionalPayloads->writeUnsignedByte(0);
        } else {
            $optionalPayloads = $optionalPayloads->writeUnsignedByte(1)
                ->writeUnsignedVarInt(count($this->blockActions));
            foreach ($this->blockActions as $action) {
                $optionalPayloads = $optionalPayloads->writeSignedVarInt($action->action->value);
                if ($action->position !== null && $action->face !== null) {
                    $optionalPayloads = $optionalPayloads
                        ->writeSignedVarInt($action->position->x)->writeSignedVarInt($action->position->y)
                        ->writeSignedVarInt($action->position->z)->writeSignedVarInt($action->face);
                }
            }
        }
        $optionalPayloads = $optionalPayloads->writeUnsignedByte($hasPredictedVehicle ? 1 : 0);
        if ($hasPredictedVehicle) {
            $vehicleRotationPitch = $this->vehicleRotationPitch;
            $vehicleRotationYaw = $this->vehicleRotationYaw;
            if ($vehicleRotationPitch === null || $vehicleRotationYaw === null) {
                throw new InvalidValueException('Mounted input requires complete vehicle rotation.');
            }
            $optionalPayloads = $optionalPayloads
                ->writeFloatLE($vehicleRotationPitch)
                ->writeFloatLE($vehicleRotationYaw);
        }
        $optionalPayloads = $optionalPayloads->writeUnsignedByte($hasPredictedVehicle ? 1 : 0);
        if ($hasPredictedVehicle) {
            $predictedVehicleActorId = $this->predictedVehicleActorId;
            if ($predictedVehicleActorId === null) {
                throw new InvalidValueException('Mounted input requires a predicted vehicle actor ID.');
            }
            $optionalPayloads = $optionalPayloads->writeSignedVarLong($predictedVehicleActorId);
        }
        return CodecSupport::writer()->writeFloatLE($this->pitch)->writeFloatLE($this->yaw)
            ->writeFloatLE($this->wireX)->writeFloatLE($this->wireY)->writeFloatLE($this->wireZ)
            ->writeFloatLE($this->moveX)->writeFloatLE($this->moveZ)->writeFloatLE($this->headYaw)
            ->writeBytes(self::encodeInputData($this->inputFlags))->writeUnsignedVarInt($this->inputMode)
            ->writeUnsignedVarInt($this->playMode)->writeUnsignedVarInt($this->interactionMode)
            ->writeFloatLE($this->interactPitch)->writeFloatLE($this->interactYaw)->writeUnsignedVarLong($this->tick)
            ->writeFloatLE($this->deltaX)->writeFloatLE($this->deltaY)->writeFloatLE($this->deltaZ)
            ->writeBytes($optionalPayloads->toString())
            ->writeFloatLE($this->analogMoveX)->writeFloatLE($this->analogMoveZ)
            ->writeFloatLE($this->cameraX)->writeFloatLE($this->cameraY)->writeFloatLE($this->cameraZ)
            ->writeFloatLE($this->rawMoveX)->writeFloatLE($this->rawMoveZ)->toString();
    }

    public static function decode(string $bytes): self
    {
        return self::decodeForProtocol($bytes, ProtocolVersion::CURRENT);
    }

    public static function decodeForProtocol(string $bytes, int $protocolVersion): self
    {
        self::requireSupportedProtocol($protocolVersion);
        $ordinary = self::decodeOrdinaryInput($bytes);
        if ($ordinary !== null) {
            return $ordinary;
        }

        return self::decodeConditionalInput($bytes);
    }

    /**
     * Decodes the common movement-only shape without constructing an immutable reader for every scalar.
     * Conditional action payloads retain the general decoder below.
     */
    private static function decodeOrdinaryInput(string $bytes): ?self
    {
        $offset = 0;
        $floats = self::readFloats($bytes, $offset, 8);
        $count = UnsignedVarInt::decode($bytes, $offset);
        $offset += $count['bytes'];
        if ($count['value'] > PlayerAuthInputFlag::COUNT) {
            throw new MalformedDataException('PlayerAuthInput input-data count exceeds its limit.');
        }
        $flags = [];
        $flagSet = [];
        $hasConditionalPayload = false;
        for ($index = 0; $index < $count['value']; ++$index) {
            $decoded = SignedVarInt::decode($bytes, $offset);
            $offset += $decoded['bytes'];
            $flag = $decoded['value'];
            if (PlayerAuthInputFlag::tryFrom($flag) === null || isset($flagSet[$flag])) {
                throw new MalformedDataException('PlayerAuthInput input data contains an unknown or duplicate entry.');
            }
            $flagSet[$flag] = true;
            $flags[] = $flag;
            $hasConditionalPayload = $hasConditionalPayload || in_array($flag, self::CONDITIONAL_PAYLOAD_FLAGS, true);
        }
        if ($hasConditionalPayload) {
            return null;
        }

        $inputMode = UnsignedVarInt::decode($bytes, $offset);
        $offset += $inputMode['bytes'];
        $playMode = UnsignedVarInt::decode($bytes, $offset);
        $offset += $playMode['bytes'];
        $interactionMode = UnsignedVarInt::decode($bytes, $offset);
        $offset += $interactionMode['bytes'];
        array_push($floats, ...self::readFloats($bytes, $offset, 2));
        $tick = UnsignedVarLong::decode($bytes, $offset);
        $offset += $tick['bytes'];
        array_push($floats, ...self::readFloats($bytes, $offset, 3));

        if (strlen($bytes) - $offset < 5) {
            throw new BufferUnderflowException('Truncated PlayerAuthInput optional-payload presence fields.');
        }
        if (substr($bytes, $offset, 5) !== "\0\0\0\0\0") {
            // Let the general path report the precise presence or boolean violation.
            return null;
        }
        $offset += 5;
        array_push($floats, ...self::readFloats($bytes, $offset, 7));
        if ($offset !== strlen($bytes)) {
            throw new MalformedDataException('Unexpected trailing bytes after packet payload.');
        }

        try {
            return new self(
                $floats[0], $floats[1], $floats[2], $floats[3], $floats[4], $floats[5], $floats[6], $floats[7],
                $flags, $inputMode['value'], $playMode['value'], $interactionMode['value'],
                $floats[8], $floats[9], $tick['value'], $floats[10], $floats[11], $floats[12],
                $floats[13], $floats[14], $floats[15], $floats[16], $floats[17], $floats[18], $floats[19],
            );
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('PlayerAuthInput payload is invalid.', previous: $e);
        }
    }

    private static function decodeConditionalInput(string $bytes): self
    {
        $r = CodecSupport::reader($bytes); $floats = [];
        for ($i = 0; $i < 8; ++$i) { [$floats[], $r] = self::float($r); }
        [$flags, $r] = self::readInputData($r);
        $inputMode = $r->readUnsignedVarInt(); $playMode = $inputMode->reader->readUnsignedVarInt();
        $interaction = $playMode->reader->readUnsignedVarInt(); $r = $interaction->reader;
        for ($i = 0; $i < 2; ++$i) { [$floats[], $r] = self::float($r); }
        $tick = $r->readUnsignedVarLong(); $r = $tick->reader;
        for ($i = 0; $i < 3; ++$i) { [$floats[], $r] = self::float($r); }
        $itemStackRequest = null;
        $blockActions = null;
        $itemUseTransaction = null;
        [$itemUsePresent, $r] = CodecSupport::readBoolean($r);
        self::requirePresence($flags, PlayerAuthInputFlag::PerformItemInteraction, $itemUsePresent, 'item-use transaction');
        if ($itemUsePresent) {
            [$itemUseTransaction, $r] = PlayerItemUseTransactionCodec::read($r);
        }
        [$stackRequestPresent, $r] = CodecSupport::readBoolean($r);
        self::requirePresence($flags, PlayerAuthInputFlag::PerformItemStackRequest, $stackRequestPresent, 'item-stack request');
        if ($stackRequestPresent) {
            [$itemStackRequest, $r] = ItemStackRequestCodec::readEntry($r);
        }
        [$blockActionsPresent, $r] = CodecSupport::readBoolean($r);
        self::requirePresence($flags, PlayerAuthInputFlag::PerformBlockActions, $blockActionsPresent, 'block actions');
        if ($blockActionsPresent) {
            [$blockActions, $r] = self::readBlockActions($r);
        }
        $vehicleRotationPitch = null;
        $vehicleRotationYaw = null;
        $predictedVehicleActorId = null;
        [$vehicleRotationPresent, $r] = CodecSupport::readBoolean($r);
        self::requirePresence($flags, PlayerAuthInputFlag::IsInClientPredictedVehicle, $vehicleRotationPresent, 'vehicle rotation');
        if ($vehicleRotationPresent) {
            [$vehicleRotationPitch, $r] = self::float($r);
            [$vehicleRotationYaw, $r] = self::float($r);
        }
        [$predictedVehiclePresent, $r] = CodecSupport::readBoolean($r);
        self::requirePresence($flags, PlayerAuthInputFlag::IsInClientPredictedVehicle, $predictedVehiclePresent, 'predicted vehicle');
        if ($predictedVehiclePresent) {
            $predictedVehicle = $r->readSignedVarLong();
            $predictedVehicleActorId = $predictedVehicle->value;
            $r = $predictedVehicle->reader;
        }
        for ($i = 0; $i < 7; ++$i) { [$floats[], $r] = self::float($r); }
        CodecSupport::requireEnd($r);
        try {
            return new self($floats[0], $floats[1], $floats[2], $floats[3], $floats[4], $floats[5], $floats[6], $floats[7],
                $flags, $inputMode->value, $playMode->value, $interaction->value, $floats[8], $floats[9], $tick->value,
                $floats[10], $floats[11], $floats[12], $floats[13], $floats[14], $floats[15], $floats[16], $floats[17],
                $floats[18], $floats[19], $itemStackRequest !== null || $blockActions !== null || $itemUseTransaction !== null,
                $itemStackRequest?->requestId, $blockActions, $itemUseTransaction, $itemStackRequest,
                $vehicleRotationPitch, $vehicleRotationYaw, $predictedVehicleActorId);
        } catch (InvalidValueException $e) { throw new MalformedDataException('PlayerAuthInput payload is invalid.', previous: $e); }
    }

    /** @return list<float> */
    private static function readFloats(string $bytes, int &$offset, int $count): array
    {
        $length = $count * 4;
        if (strlen($bytes) - $offset < $length) {
            throw new BufferUnderflowException('Truncated PlayerAuthInput float fields.');
        }
        $decoded = unpack('g' . $count, substr($bytes, $offset, $length));
        if ($decoded === false || count($decoded) !== $count) {
            throw new MalformedDataException('Unable to decode PlayerAuthInput float fields.');
        }
        $offset += $length;

        $floats = [];
        foreach ($decoded as $value) {
            if (!is_float($value)) {
                throw new MalformedDataException('PlayerAuthInput float field has an unexpected decoded type.');
            }
            $floats[] = $value;
        }

        return $floats;
    }

    /** @return array{float, ByteBufferReader} */
    private static function float(ByteBufferReader $reader): array
    {
        $value = $reader->readFloatLE(); CodecSupport::validateFiniteFloat($value->value, 'PlayerAuthInput float', true);
        return [$value->value, $value->reader];
    }

    /** @return array{list<int>, ByteBufferReader} */
    private static function readInputData(ByteBufferReader $reader): array
    {
        $count = $reader->readUnsignedVarInt();
        if ($count->value > PlayerAuthInputFlag::COUNT) {
            throw new MalformedDataException('PlayerAuthInput input-data count exceeds its limit.');
        }
        $flags = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $flag = $reader->readSignedVarInt();
            if (PlayerAuthInputFlag::tryFrom($flag->value) === null || in_array($flag->value, $flags, true)) {
                throw new MalformedDataException('PlayerAuthInput input data contains an unknown or duplicate entry.');
            }
            $flags[] = $flag->value;
            $reader = $flag->reader;
        }
        return [$flags, $reader];
    }

    /** @param list<int> $flags */
    private static function encodeInputData(array $flags): string
    {
        $writer = CodecSupport::writer();
        $writer = $writer->writeUnsignedVarInt(count($flags));
        foreach ($flags as $flag) {
            $writer = $writer->writeSignedVarInt($flag);
        }
        return $writer->toString();
    }

    /** @param list<int> $flags */
    private static function requirePresence(
        array $flags,
        PlayerAuthInputFlag $flag,
        bool $present,
        string $payload,
    ): void {
        if (in_array($flag->value, $flags, true) !== $present) {
            throw new MalformedDataException("PlayerAuthInput {$payload} presence does not match its input flag.");
        }
    }

    /** @return array{list<PlayerBlockAction>, ByteBufferReader} */
    private static function readBlockActions(ByteBufferReader $reader): array
    {
        $count = $reader->readUnsignedVarInt();
        if ($count->value > self::MAX_BLOCK_ACTIONS) {
            throw new MalformedDataException('PlayerAuthInput block-action count exceeds its limit.');
        }
        $actions = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $action = $reader->readSignedVarInt();
            $actionType = PlayerActionType::tryFrom($action->value);
            if ($actionType === null) {
                throw new MalformedDataException('PlayerAuthInput block-action type is unknown.');
            }
            if ($actionType === PlayerActionType::StopDestroyBlock) {
                $actions[] = new PlayerBlockAction($actionType);
                $reader = $action->reader;
                continue;
            }
            $x = $action->reader->readSignedVarInt();
            $y = $x->reader->readSignedVarInt();
            $z = $y->reader->readSignedVarInt();
            $face = $z->reader->readSignedVarInt();
            try {
                $actions[] = new PlayerBlockAction(
                    $actionType,
                    new BlockPosition($x->value, $y->value, $z->value),
                    $face->value,
                );
            } catch (InvalidValueException $e) {
                throw new MalformedDataException('PlayerAuthInput block action is invalid.', previous: $e);
            }
            $reader = $face->reader;
        }
        return [$actions, $reader];
    }

    private static function requireSupportedProtocol(int $protocolVersion): void
    {
        if (!ProtocolVersion::supports($protocolVersion)) {
            throw new InvalidValueException('Unsupported protocol version for PlayerAuthInput.');
        }
    }
}
