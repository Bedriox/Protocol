<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

use Bedriox\Protocol\Security\CompactJws;
use Bedriox\Protocol\Security\P384KeyPair;

/** Re-signs already verified client presentation data for a trusted downstream session. */
final class ClientDataJwt
{
    public static function create(VerifiedClientData $data, P384KeyPair $keys, ?ClientDataLimits $limits = null): string
    {
        $animations = [];
        foreach ($data->animations as $animation) {
            $animations[] = [
                'ImageWidth' => $animation->width,
                'ImageHeight' => $animation->height,
                'Image' => base64_encode($animation->image),
                'Type' => $animation->type,
                'Frames' => $animation->frames,
                'ExpressionType' => $animation->expressionType,
            ];
        }

        return CompactJws::sign(['alg' => 'ES384'], [
            'SkinImageWidth' => $data->skinWidth,
            'SkinImageHeight' => $data->skinHeight,
            'SkinData' => base64_encode($data->skin),
            'CapeImageWidth' => $data->capeWidth,
            'CapeImageHeight' => $data->capeHeight,
            'CapeData' => base64_encode($data->cape),
            'SkinGeometryData' => base64_encode($data->geometryJson),
            'AnimatedImageData' => $animations,
            'SkinId' => $data->skinId,
            'PlayFabId' => $data->playFabId,
            'SkinResourcePatch' => base64_encode($data->skinResourcePatchJson),
            'SkinGeometryDataEngineVersion' => $data->geometryEngineVersion,
            'SkinAnimationData' => base64_encode($data->animationDataJson),
            'CapeId' => $data->capeId,
            'FullSkinId' => $data->fullSkinId,
            'ArmSize' => $data->armSize,
            'SkinColor' => $data->skinColor,
            'PremiumSkin' => $data->premium,
            'PersonaSkin' => $data->persona,
            'CapeOnClassicSkin' => $data->capeOnClassic,
            'IsPrimaryUser' => $data->primaryUser,
            'OverrideSkin' => $data->overridingPlayerAppearance,
            'ProfileHash' => $data->profileHash,
            'DeviceOS' => $data->deviceOs->value,
        ], $keys->privateKey, ($limits ?? new ClientDataLimits())->jws);
    }
}
