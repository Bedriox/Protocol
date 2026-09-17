<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

use OpenSSLAsymmetricKey;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Security\Base64Url;
use Bedriox\Protocol\Security\BoundedJson;
use Bedriox\Protocol\Security\CompactJws;
use Bedriox\Protocol\Security\P384;
use Bedriox\Protocol\Value\DeviceOs;

final readonly class ClientDataJwtVerifier
{
    private ClientDataLimits $limits;

    public function __construct(?ClientDataLimits $limits = null)
    {
        $this->limits = $limits ?? new ClientDataLimits();
    }

    public function verify(string $compact, OpenSSLAsymmetricKey $identityPublicKey): VerifiedClientData
    {
        P384::assertPublicKey($identityPublicKey);
        $jws = CompactJws::parse($compact, $this->limits->jws);
        if (!CompactJws::verify($jws, $identityPublicKey)) {
            throw new MalformedDataException('Client-data JWS signature is invalid.');
        }

        // Claims are deliberately inspected only after signature verification.
        $segments = explode('.', $jws->signingInput);
        if (count($segments) !== 2) {
            throw new MalformedDataException('Client-data signing input is malformed.');
        }
        $payloadJson = Base64Url::decode($segments[1], $this->limits->jws->maximumPayloadBytes);
        JsonTokenCounter::assertWithin($payloadJson, $this->limits->maximumJsonTokens);

        $payload = $jws->payload;
        $skinWidth = self::dimension($payload, 'SkinImageWidth', $this->limits->maximumDimension, false);
        $skinHeight = self::dimension($payload, 'SkinImageHeight', $this->limits->maximumDimension, false);
        $skin = self::image($payload, 'SkinData', $skinWidth, $skinHeight, $this->limits->maximumSkinBytes, false);
        $capeWidth = self::dimension($payload, 'CapeImageWidth', $this->limits->maximumDimension, true);
        $capeHeight = self::dimension($payload, 'CapeImageHeight', $this->limits->maximumDimension, true);
        if (($capeWidth === 0) !== ($capeHeight === 0)) {
            throw new MalformedDataException('Cape dimensions must both be zero or both be positive.');
        }
        $cape = self::image($payload, 'CapeData', $capeWidth, $capeHeight, $this->limits->maximumCapeBytes, true);
        $geometry = self::decodedBase64($payload, 'SkinGeometryData', $this->limits->maximumGeometryBytes, false);
        if (trim($geometry, " \t\r\n") !== 'null') {
            BoundedJson::decodeObject($geometry, $this->limits->maximumGeometryBytes, $this->limits->jws->maximumJsonDepth);
        }
        JsonTokenCounter::assertWithin($geometry, $this->limits->maximumGeometryJsonTokens);

        $animationValues = $payload['AnimatedImageData'] ?? null;
        if (!is_array($animationValues) || !array_is_list($animationValues)
            || count($animationValues) > $this->limits->maximumAnimations) {
            throw new MalformedDataException('AnimatedImageData must be a bounded list.');
        }
        $animations = [];
        $aggregateBytes = strlen($skin) + strlen($cape) + strlen($geometry);
        foreach ($animationValues as $value) {
            if (!is_array($value)) {
                throw new MalformedDataException('Animation entry must be an object.');
            }
            $width = self::dimension($value, 'ImageWidth', $this->limits->maximumDimension, false);
            $height = self::dimension($value, 'ImageHeight', $this->limits->maximumDimension, false);
            $image = self::image($value, 'Image', $width, $height, $this->limits->maximumSkinBytes, false);
            $type = $value['Type'] ?? null;
            $frames = $value['Frames'] ?? null;
            $expressionType = $value['ExpressionType'] ?? 0;
            if (!is_int($type) || $type < 0 || $type > 255 || (!is_int($frames) && !is_float($frames))
                || !is_finite((float) $frames) || $frames < 0 || !is_int($expressionType)
                || $expressionType < 0 || $expressionType > 1) {
                throw new MalformedDataException('Animation metadata is invalid.');
            }
            $aggregateBytes = self::addBounded($aggregateBytes, strlen($image), $this->limits->maximumAggregateDecodedBytes);
            $animations[] = new VerifiedAnimation($width, $height, $image, $type, (float) $frames, $expressionType);
        }
        if ($aggregateBytes > $this->limits->maximumAggregateDecodedBytes) {
            throw new MalformedDataException('Decoded client data exceeds its aggregate byte limit.');
        }

        $resourcePatch = self::optionalDecodedJson($payload, 'SkinResourcePatch', 65_536);
        $animationData = self::optionalDecodedJson($payload, 'SkinAnimationData', 65_536);
        $aggregateBytes = self::addBounded($aggregateBytes, strlen($resourcePatch), $this->limits->maximumAggregateDecodedBytes);
        self::addBounded($aggregateBytes, strlen($animationData), $this->limits->maximumAggregateDecodedBytes);

        return new VerifiedClientData(
            $skinWidth, $skinHeight, $skin, $capeWidth, $capeHeight, $cape, $geometry, $animations,
            self::optionalString($payload, 'SkinId', 4_096),
            self::optionalString($payload, 'PlayFabId', 4_096),
            $resourcePatch,
            self::optionalString($payload, 'SkinGeometryDataEngineVersion', 128),
            $animationData,
            self::optionalString($payload, 'CapeId', 4_096),
            self::optionalString($payload, 'FullSkinId', 4_096),
            self::optionalString($payload, 'ArmSize', 64),
            self::optionalString($payload, 'SkinColor', 64),
            self::optionalBoolean($payload, 'PremiumSkin'),
            self::optionalBoolean($payload, 'PersonaSkin'),
            self::optionalBoolean($payload, 'CapeOnClassicSkin'),
            self::optionalBoolean($payload, 'IsPrimaryUser'),
            self::optionalBoolean($payload, 'OverrideSkin'),
            self::optionalString($payload, 'ProfileHash', 4_096),
            self::optionalDeviceOs($payload),
        );
    }

    /** @param array<array-key, mixed> $object */
    private static function optionalDeviceOs(array $object): DeviceOs
    {
        $value = $object['DeviceOS'] ?? DeviceOs::Unknown->value;
        if (!is_int($value)) {
            throw new MalformedDataException('Client-data DeviceOS is not an integer.');
        }
        $platform = DeviceOs::tryFrom($value);
        if ($platform === null) {
            throw new MalformedDataException('Client-data DeviceOS is outside the current platform domain.');
        }
        return $platform;
    }

    /** @param array<array-key, mixed> $object */
    private static function optionalString(array $object, string $name, int $maximum): string
    {
        $value = $object[$name] ?? '';
        if (!is_string($value) || strlen($value) > $maximum || preg_match('//u', $value) !== 1) {
            throw new MalformedDataException("Client-data {$name} is not a bounded UTF-8 string.");
        }
        return $value;
    }

    /** @param array<array-key, mixed> $object */
    private static function optionalBoolean(array $object, string $name): bool
    {
        $value = $object[$name] ?? false;
        if (!is_bool($value)) {
            throw new MalformedDataException("Client-data {$name} is not a boolean.");
        }
        return $value;
    }

    /** @param array<array-key, mixed> $object */
    private static function optionalDecodedJson(array $object, string $name, int $maximum): string
    {
        if (!array_key_exists($name, $object)) {
            return '';
        }
        $decoded = self::decodedBase64($object, $name, $maximum, true);
        if ($decoded !== '') {
            BoundedJson::decodeObject($decoded, $maximum, 32);
            JsonTokenCounter::assertWithin($decoded, 4_096);
        }
        return $decoded;
    }

    /** @param array<array-key, mixed> $object */
    private static function dimension(array $object, string $name, int $maximum, bool $allowZero): int
    {
        $value = $object[$name] ?? null;
        if (!is_int($value) || $value > $maximum || ($allowZero ? $value < 0 : $value < 1)) {
            throw new MalformedDataException("Client-data {$name} is outside its dimension limit.");
        }
        return $value;
    }

    /** @param array<array-key, mixed> $object */
    private static function image(array $object, string $name, int $width, int $height, int $maximum, bool $allowEmpty): string
    {
        $decoded = self::decodedBase64($object, $name, $maximum, $allowEmpty);
        $expected = $width * $height * 4;
        if (strlen($decoded) !== $expected) {
            throw new MalformedDataException("Client-data {$name} length does not match its dimensions.");
        }
        return $decoded;
    }

    /** @param array<array-key, mixed> $object */
    private static function decodedBase64(array $object, string $name, int $maximum, bool $allowEmpty): string
    {
        $encoded = $object[$name] ?? null;
        $maximumEncodedBytes = intdiv($maximum + 2, 3) * 4;
        if (!is_string($encoded) || (!$allowEmpty && $encoded === '') || strlen($encoded) > $maximumEncodedBytes) {
            throw new MalformedDataException("Client-data {$name} is missing or oversized.");
        }
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strlen($decoded) > $maximum || base64_encode($decoded) !== $encoded) {
            throw new MalformedDataException("Client-data {$name} is not canonical standard base64.");
        }
        return $decoded;
    }

    private static function addBounded(int $current, int $additional, int $maximum): int
    {
        if ($additional > $maximum - $current) {
            throw new MalformedDataException('Decoded client data exceeds its aggregate byte limit.');
        }
        return $current + $additional;
    }
}
