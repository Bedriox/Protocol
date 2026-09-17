<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

use Bedriox\Protocol\Exception\MalformedDataException;

/** @internal Linear lexical token bound applied after strict JSON decoding. */
final class JsonTokenCounter
{
    public static function assertWithin(string $json, int $maximumTokens): void
    {
        $tokens = 0;
        $length = strlen($json);
        for ($offset = 0; $offset < $length;) {
            $character = $json[$offset];
            if (str_contains(" \t\r\n", $character)) {
                ++$offset;
                continue;
            }
            if ($character === '"') {
                ++$tokens;
                for (++$offset; $offset < $length; ++$offset) {
                    if ($json[$offset] === '\\') {
                        ++$offset;
                        continue;
                    }
                    if ($json[$offset] === '"') {
                        ++$offset;
                        break;
                    }
                }
            } elseif (str_contains('{}[]:,', $character)) {
                ++$tokens;
                ++$offset;
            } else {
                ++$tokens;
                while ($offset < $length && !str_contains("{}[]:, \t\r\n", $json[$offset])) {
                    ++$offset;
                }
            }
            if ($tokens > $maximumTokens) {
                throw new MalformedDataException('JSON token count exceeds its limit.');
            }
        }
    }
}
