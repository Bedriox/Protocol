<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use JsonException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class LoginAuthentication
{
    public const int MAX_CHAIN_ENTRIES = 16;

    /** @param ?list<string> $certificateChain */
    public function __construct(
        public AuthenticationType $type,
        public ?string $token = null,
        public ?array $certificateChain = null,
    ) {
        if (($token === null) === ($certificateChain === null)) {
            throw new InvalidValueException('Login authentication requires exactly one token or certificate chain.');
        }
        if ($token !== null) {
            CodecSupport::validateString($token, CodecSupport::MAX_JWT_BYTES, 'Authentication token');
            if ($token === '') {
                throw new InvalidValueException('Authentication token cannot be empty.');
            }
        }
        if ($certificateChain !== null) {
            CodecSupport::validateCount($certificateChain, self::MAX_CHAIN_ENTRIES, 'Certificate chain');
            if ($certificateChain === []) {
                throw new InvalidValueException('Certificate chain cannot be empty.');
            }
            foreach ($certificateChain as $certificate) {
                CodecSupport::validateString($certificate, CodecSupport::MAX_JWT_BYTES, 'Certificate JWT');
                if ($certificate === '') {
                    throw new InvalidValueException('Certificate JWT cannot be empty.');
                }
            }
        }
    }

    public function toJson(): string
    {
        try {
            $certificate = $this->certificateChain === null
                ? ''
                : json_encode(['chain' => $this->certificateChain], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $json = json_encode([
                'AuthenticationType' => $this->type->value,
                'Token' => $this->token ?? '',
                'Certificate' => $certificate,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            CodecSupport::validateString($json, CodecSupport::MAX_JWT_BYTES, 'Authentication JSON');
            return $json;
        } catch (JsonException $exception) {
            throw new InvalidValueException('Login authentication cannot be encoded as JSON.', previous: $exception);
        }
    }

    public static function fromJson(string $json): self
    {
        CodecSupport::validateWireString($json, CodecSupport::MAX_JWT_BYTES, 'Authentication JSON');
        try {
            $value = json_decode($json, true, 8, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            throw new MalformedDataException('Authentication JSON is malformed.', previous: $exception);
        }
        if (!is_array($value) || !array_key_exists('AuthenticationType', $value)) {
            throw new MalformedDataException('Authentication JSON lacks AuthenticationType.');
        }
        $typeValue = $value['AuthenticationType'];
        $type = is_int($typeValue) ? AuthenticationType::tryFrom($typeValue) : null;
        if ($type === null) {
            throw new MalformedDataException('AuthenticationType is invalid for the supported Bedrock protocol.');
        }
        $token = $value['Token'] ?? '';
        $certificate = $value['Certificate'] ?? '';
        if (!is_string($token) || !is_string($certificate)) {
            throw new MalformedDataException('Authentication Token and Certificate must be strings.');
        }
        $hasToken = $token !== '';
        $hasCertificate = $certificate !== '';
        if ($hasToken === $hasCertificate) {
            throw new MalformedDataException('Authentication JSON requires exactly one non-empty Token or Certificate.');
        }
        if ($hasToken) {
            return new self($type, $token);
        }
        try {
            $certificateValue = json_decode($certificate, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MalformedDataException('Certificate JSON is malformed.', previous: $exception);
        }
        $chain = is_array($certificateValue) ? ($certificateValue['chain'] ?? null) : null;
        if (!is_array($chain) || !array_is_list($chain) || $chain === [] || count($chain) > self::MAX_CHAIN_ENTRIES) {
            throw new MalformedDataException('Certificate chain is missing or exceeds its count limit.');
        }
        $validatedChain = [];
        foreach ($chain as $entry) {
            if (!is_string($entry) || $entry === '') {
                throw new MalformedDataException('Certificate chain entries must be non-empty strings.');
            }
            CodecSupport::validateWireString($entry, CodecSupport::MAX_JWT_BYTES, 'Certificate JWT');
            $validatedChain[] = $entry;
        }
        return new self($type, certificateChain: $validatedChain);
    }
}
