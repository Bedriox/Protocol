<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

/** Opaque authentication envelope; verification belongs to the server authentication layer. */
final readonly class LoginPacket implements Packet
{
    public function __construct(public int $protocolVersion, public LoginAuthentication $authentication, public string $clientJwt)
    {
        CodecSupport::validateString($clientJwt, CodecSupport::MAX_JWT_BYTES, 'Client JWT');
        if ($clientJwt === '') {
            throw new \Bedriox\Protocol\Exception\InvalidValueException('Client JWT cannot be empty.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::LOGIN;
    }

    public function encode(): string
    {
        $authenticationJson = $this->authentication->toJson();
        $jwtLength = strlen($authenticationJson) + strlen($this->clientJwt) + 8;
        $writer = CodecSupport::writeSignedIntBE(CodecSupport::writer(), $this->protocolVersion)
            ->writeUnsignedVarInt($jwtLength)
            ->writeSignedIntLE(strlen($authenticationJson))
            ->writeBytes($authenticationJson)
            ->writeSignedIntLE(strlen($this->clientJwt))
            ->writeBytes($this->clientJwt);
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        [$version, $reader] = CodecSupport::readSignedIntBE(CodecSupport::reader($bytes));
        $envelopeLength = $reader->readUnsignedVarInt();
        if ($envelopeLength->value > CodecSupport::MAX_JWT_BYTES * 2 + 8) {
            throw new MalformedDataException('Login JWT envelope exceeds its byte limit.');
        }
        $envelope = $envelopeLength->reader->readBytes($envelopeLength->value);
        CodecSupport::requireEnd($envelope->reader);
        $jwtReader = CodecSupport::reader($envelope->value);
        $authLength = $jwtReader->readSignedIntLE();
        if ($authLength->value < 0 || $authLength->value > CodecSupport::MAX_JWT_BYTES) {
            throw new MalformedDataException('Authentication JSON length is invalid.');
        }
        $auth = $authLength->reader->readBytes($authLength->value);
        $clientLength = $auth->reader->readSignedIntLE();
        if ($clientLength->value < 0 || $clientLength->value > CodecSupport::MAX_JWT_BYTES) {
            throw new MalformedDataException('Client JWT length is invalid.');
        }
        $client = $clientLength->reader->readBytes($clientLength->value);
        CodecSupport::requireEnd($client->reader);
        if ($client->value === '') {
            throw new MalformedDataException('Client JWT cannot be empty.');
        }
        if (preg_match('//u', $auth->value) !== 1 || preg_match('//u', $client->value) !== 1) {
            throw new MalformedDataException('Login strings must be valid UTF-8.');
        }
        return new self($version, LoginAuthentication::fromJson($auth->value), $client->value);
    }
}
