<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Identity\VerifiedAnimation;
use Bedriox\Protocol\Identity\VerifiedClientData;

/** Bounded wire appearance. Construct from VerifiedClientData at the trust boundary before sending. */
final readonly class PlayerSkin
{
    /** @param list<VerifiedAnimation> $animations */
    public function __construct(
        public int $skinWidth, public int $skinHeight, public string $skin,
        public int $capeWidth, public int $capeHeight, public string $cape,
        public string $geometryJson, public array $animations,
        public string $skinId, public string $playFabId, public string $resourcePatchJson,
        public string $geometryEngineVersion = '', public string $animationDataJson = '',
        public string $capeId = '', public string $fullSkinId = '', public string $armSize = '', public string $skinColor = '',
        public bool $premium = false, public bool $capeOnClassic = false, public bool $primaryUser = false,
        public bool $overridingPlayerAppearance = false,
        public string $profileHash = '',
    ) {
        if ($skinId === '' || $resourcePatchJson === '') {
            throw new InvalidValueException('Player-list skin ID and resource patch must be retained from verified client data.');
        }
        self::validateImage($skinWidth, $skinHeight, $skin, 262_144, 'Skin');
        if ($skinWidth < 64 || $skinHeight < 32) {
            throw new InvalidValueException('Skin dimensions are below the minimum accepted by Bedrock clients.');
        }
        self::validateImage($capeWidth, $capeHeight, $cape, 65_536, 'Cape', true);
        CodecSupport::validateCount($animations, 32, 'Skin animations');
        foreach ($animations as $animation) {
            if (!$animation instanceof VerifiedAnimation || $animation->type < 0 || $animation->type > 3
                || $animation->expressionType < 0 || $animation->expressionType > 1 || !is_finite($animation->frames)) {
                throw new InvalidValueException('Skin animation is outside the Bedrock bounds.');
            }
            self::validateImage($animation->width, $animation->height, $animation->image, 262_144, 'Animation');
        }
        foreach ([$geometryJson, $resourcePatchJson, $animationDataJson] as $json) {
            if ($json !== '') {
                try {
                    json_decode($json, true, 32, JSON_THROW_ON_ERROR);
                } catch (\JsonException $error) {
                    throw new InvalidValueException('Skin JSON is malformed.', previous: $error);
                }
            }
        }
        try {
            $patch = json_decode($resourcePatchJson, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new InvalidValueException('Skin resource patch JSON is malformed.', previous: $error);
        }
        $defaultGeometry = is_array($patch) && is_array($patch['geometry'] ?? null) ? ($patch['geometry']['default'] ?? null) : null;
        if (!is_string($defaultGeometry) || $defaultGeometry === '' || strlen($defaultGeometry) > 256) {
            throw new InvalidValueException('Skin resource patch must name a bounded default geometry.');
        }
        foreach ([$skinId, $playFabId, $resourcePatchJson, $geometryJson, $geometryEngineVersion, $animationDataJson,
            $capeId, $fullSkinId, $armSize, $skinColor] as $string) {
            CodecSupport::validateString($string, 65_536, 'Skin string');
        }
        CodecSupport::validateString($profileHash, 4_096, 'Skin profile hash');
    }

    public static function fromVerifiedClientData(VerifiedClientData $data): self
    {
        if ($data->persona) {
            throw new InvalidValueException('Persona skins require piece/tint fields not retained by the MVP verifier.');
        }
        return new self(
            $data->skinWidth, $data->skinHeight, $data->skin,
            $data->capeWidth, $data->capeHeight, $data->cape,
            $data->geometryJson, $data->animations, $data->skinId, $data->playFabId,
            $data->skinResourcePatchJson, $data->geometryEngineVersion, $data->animationDataJson,
            $data->capeId, $data->fullSkinId, $data->armSize, $data->skinColor,
            $data->premium, $data->capeOnClassic, $data->primaryUser, $data->overridingPlayerAppearance,
            $data->profileHash,
        );
    }

    public function write(ByteBufferWriter $writer, bool $trusted): ByteBufferWriter
    {
        $writer = $writer->writeString($this->skinId, 65_536)->writeString($this->playFabId, 65_536)
            ->writeString($this->resourcePatchJson, 65_536);
        $writer = self::writeImage($writer, $this->skinWidth, $this->skinHeight, $this->skin)
            ->writeUnsignedVarInt(count($this->animations));
        foreach ($this->animations as $animation) {
            $writer = self::writeImage($writer, $animation->width, $animation->height, $animation->image)
                ->writeUnsignedVarInt($animation->type)->writeFloatLE($animation->frames)
                ->writeUnsignedVarInt($animation->expressionType);
        }
        $writer = self::writeImage($writer, $this->capeWidth, $this->capeHeight, $this->cape);
        foreach ([$this->geometryJson, $this->geometryEngineVersion, $this->animationDataJson, $this->capeId,
            $this->fullSkinId] as $string) {
            $writer = $writer->writeString($string, 65_536);
        }
        $writer = $writer->writeUnsignedByte(strtolower($this->armSize) === 'wide' ? 1 : 0)
            ->writeSignedIntLE(self::skinColorToInt($this->skinColor))
            ->writeUnsignedVarInt(0)->writeUnsignedVarInt(0);
        foreach ([$this->premium, false, $this->capeOnClassic, $this->primaryUser, $this->overridingPlayerAppearance] as $flag) {
            $writer = CodecSupport::writeBoolean($writer, $flag);
        }
        return $writer->writeString($trusted ? 'true' : 'false', 5)->writeString($this->profileHash, 4_096);
    }

    /** @return array{self, ByteBufferReader, bool} */
    public static function read(ByteBufferReader $reader): array
    {
        $skinId = $reader->readString(65_536); $playFab = $skinId->reader->readString(65_536);
        $patch = $playFab->reader->readString(65_536);
        [$skinWidth, $skinHeight, $skin, $reader] = self::readImage($patch->reader, 262_144, false);
        $count = $reader->readUnsignedVarInt();
        if ($count->value > 32) { throw new MalformedDataException('Skin animation count exceeds its limit.'); }
        $animations = []; $reader = $count->reader;
        for ($i = 0; $i < $count->value; ++$i) {
            [$width, $height, $image, $reader] = self::readImage($reader, 262_144, false);
            $type = $reader->readUnsignedVarInt(); $frames = $type->reader->readFloatLE(); $expression = $frames->reader->readUnsignedVarInt();
            if ($type->value < 0 || $type->value > 3 || !is_finite($frames->value) || $expression->value < 0 || $expression->value > 1) {
                throw new MalformedDataException('Skin animation metadata is invalid.');
            }
            $animations[] = new VerifiedAnimation($width, $height, $image, $type->value, $frames->value, $expression->value);
            $reader = $expression->reader;
        }
        [$capeWidth, $capeHeight, $cape, $reader] = self::readImage($reader, 65_536, true);
        $strings = [];
        for ($i = 0; $i < 5; ++$i) { $read = $reader->readString(65_536); $strings[] = $read->value; $reader = $read->reader; }
        $armSize = $reader->readUnsignedByte();
        if ($armSize->value > 1) { throw new MalformedDataException('Skin arm size is invalid.'); }
        $skinColor = $armSize->reader->readSignedIntLE();
        $pieces = $skinColor->reader->readUnsignedVarInt();
        if ($pieces->value !== 0) { throw new MalformedDataException('Persona pieces are outside the MVP player-list slice.'); }
        $tints = $pieces->reader->readUnsignedVarInt();
        if ($tints->value !== 0) { throw new MalformedDataException('Persona tints are outside the MVP player-list slice.'); }
        $flags = []; $reader = $tints->reader;
        for ($i = 0; $i < 5; ++$i) { [$flag, $reader] = CodecSupport::readBoolean($reader); $flags[] = $flag; }
        if ($flags[1]) { throw new MalformedDataException('Persona skins are outside the MVP player-list slice.'); }
        $trusted = $reader->readString(5);
        if (!in_array($trusted->value, ['unset', 'false', 'true'], true)) {
            throw new MalformedDataException('Trusted-skin flag is invalid.');
        }
        $profileHash = $trusted->reader->readString(65_536);
        $reader = $profileHash->reader;
        try {
            $skinValue = new self($skinWidth, $skinHeight, $skin, $capeWidth, $capeHeight, $cape, $strings[0], $animations,
                $skinId->value, $playFab->value, $patch->value, $strings[1], $strings[2], $strings[3], $strings[4],
                $armSize->value === 1 ? 'wide' : '', $skinColor->value === 0 ? '' : sprintf('#%x', $skinColor->value & 0xffffffff),
                $flags[0], $flags[2], $flags[3], $flags[4], $profileHash->value);
        } catch (\Throwable $e) {
            throw new MalformedDataException('Player-list skin payload is invalid.', previous: $e);
        }
        return [$skinValue, $reader, $trusted->value === 'true'];
    }

    private static function writeImage(ByteBufferWriter $writer, int $width, int $height, string $bytes): ByteBufferWriter
    { return $writer->writeSignedIntLE($width)->writeSignedIntLE($height)->writeUnsignedVarInt(strlen($bytes))->writeBytes($bytes); }

    /** @return array{int, int, string, ByteBufferReader} */
    private static function readImage(ByteBufferReader $reader, int $maximum, bool $allowEmpty): array
    {
        $width = $reader->readSignedIntLE(); $height = $width->reader->readSignedIntLE(); $length = $height->reader->readUnsignedVarInt();
        if ($length->value > $maximum) { throw new MalformedDataException('Player skin image exceeds its byte limit.'); }
        $bytes = $length->reader->readBytes($length->value);
        try { self::validateImage($width->value, $height->value, $bytes->value, $maximum, 'Player skin image', $allowEmpty); }
        catch (InvalidValueException $e) { throw new MalformedDataException('Player skin image is invalid.', previous: $e); }
        return [$width->value, $height->value, $bytes->value, $bytes->reader];
    }

    private static function validateImage(int $width, int $height, string $bytes, int $maximum, string $field, bool $allowEmpty = false): void
    {
        if ($width < 0 || $height < 0 || $width > 256 || $height > 256 || strlen($bytes) > $maximum
            || ($allowEmpty && $width === 0 && $height === 0 ? $bytes !== '' : $width < 1 || $height < 1 || strlen($bytes) !== $width * $height * 4)) {
            throw new InvalidValueException($field . ' dimensions do not match bounded RGBA data.');
        }
    }

    private static function skinColorToInt(string $color): int
    {
        if ($color === '') {
            return 0;
        }
        if (preg_match('/^#([0-9a-fA-F]{1,8})$/D', $color, $matches) !== 1) {
            throw new InvalidValueException('Skin color must be an empty value or a hexadecimal color.');
        }
        $value = hexdec($matches[1]);
        if (!is_int($value)) {
            $value -= 4294967296.0;
        } elseif ($value > 0x7fffffff) {
            $value -= 0x100000000;
        }
        return (int) $value;
    }
}
