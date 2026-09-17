<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Bounded serverbound protocol-2193 item-stack request batch. */
final readonly class ItemStackRequestPacket implements Packet
{
    public const int MAXIMUM_REQUESTS = 100;

    /** @var list<ItemStackRequest> */
    public array $requests;
    /** @var list<int> Backward-compatible request-ID projection. */
    public array $requestIds;

    /** @param list<ItemStackRequest|int> $requests */
    public function __construct(array $requests)
    {
        if (!array_is_list($requests) || $requests === [] || count($requests) > self::MAXIMUM_REQUESTS) {
            throw new InvalidValueException('Item-stack requests must be a non-empty bounded list.');
        }
        $typed = [];
        foreach ($requests as $request) {
            if (is_int($request)) {
                $request = new ItemStackRequest($request, []);
            }
            if (!$request instanceof ItemStackRequest) {
                throw new InvalidValueException('Item-stack requests must contain typed values.');
            }
            $typed[] = $request;
        }
        $this->requests = $typed;
        $this->requestIds = array_map(static fn (ItemStackRequest $request): int => $request->requestId, $typed);
    }

    public function packetId(): int { return PacketIds::ITEM_STACK_REQUEST; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($this->requests));
        foreach ($this->requests as $request) {
            $writer = ItemStackRequestCodec::writeEntry($writer, $request);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $count = CodecSupport::reader($bytes)->readUnsignedVarInt();
        if ($count->value < 1 || $count->value > self::MAXIMUM_REQUESTS) {
            throw new MalformedDataException('Item-stack request count is invalid.');
        }
        $reader = $count->reader;
        $requests = [];
        for ($index = 0; $index < $count->value; ++$index) {
            [$requests[], $reader] = ItemStackRequestCodec::readEntry($reader);
        }
        CodecSupport::requireEnd($reader);
        return new self($requests);
    }
}
