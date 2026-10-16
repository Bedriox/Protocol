<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

/** Current Bedrock persona-piece type ordinal. */
enum PlayerSkinPersonaPieceType: int
{
    case Unknown = 0;
    case Skeleton = 1;
    case Body = 2;
    case Skin = 3;
    case Bottom = 4;
    case Feet = 5;
    case Dress = 6;
    case Top = 7;
    case HighPants = 8;
    case Hands = 9;
    case Outerwear = 10;
    case FacialHair = 11;
    case Mouth = 12;
    case Eyes = 13;
    case Hair = 14;
    case Hood = 15;
    case Back = 16;
    case FaceAccessory = 17;
    case Head = 18;
    case Legs = 19;
    case LeftLeg = 20;
    case RightLeg = 21;
    case Arms = 22;
    case LeftArm = 23;
    case RightArm = 24;
    case Capes = 25;
    case ClassicSkin = 26;
    case Emote = 27;
    case Unsupported = 28;

    public function wireName(): string
    {
        return match ($this) {
            self::Unknown => 'unknown', self::Skeleton => 'skeleton', self::Body => 'body', self::Skin => 'skin',
            self::Bottom => 'bottom', self::Feet => 'feet', self::Dress => 'dress', self::Top => 'top',
            self::HighPants => 'high_pants', self::Hands => 'hands', self::Outerwear => 'outerwear',
            self::FacialHair => 'facialhair', self::Mouth => 'mouth', self::Eyes => 'eyes', self::Hair => 'hair',
            self::Hood => 'hood', self::Back => 'back', self::FaceAccessory => 'faceaccessory', self::Head => 'head',
            self::Legs => 'legs', self::LeftLeg => 'leftleg', self::RightLeg => 'rightleg', self::Arms => 'arms',
            self::LeftArm => 'leftarm', self::RightArm => 'rightarm', self::Capes => 'capes',
            self::ClassicSkin => 'classicskin', self::Emote => 'emote', self::Unsupported => 'unsupported',
        };
    }

    public static function fromWireName(string $name): self
    {
        foreach (self::cases() as $case) {
            if ($case->wireName() === $name) {
                return $case;
            }
        }
        throw new MalformedDataException('Player skin persona tint type is invalid.');
    }
}
