<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Security;

use JsonException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final class BoundedJson
{
    /** @return array<string, mixed> */
    public static function decodeObject(string $json, int $maximumBytes, int $maximumDepth) : array
    {
        if ($maximumBytes < 2 || $maximumDepth < 2 || $maximumDepth > 64) {
            throw new InvalidValueException('Invalid JSON bounds');
        }
        if (strlen($json) > $maximumBytes) {
            throw new MalformedDataException('JSON exceeds its byte limit');
        }
        $trimmed = trim($json, " \t\r\n");
        if (!str_starts_with($trimmed, '{') || !str_ends_with($trimmed, '}')) {
            throw new MalformedDataException('JSON value must be an object');
        }
        try {
            $value = json_decode($json, true, $maximumDepth, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw new MalformedDataException('Malformed JSON');
        }
        if (!is_array($value)) {
            throw new MalformedDataException('JSON value must be an object');
        }
        self::assertNoDuplicateObjectKeys($json, $maximumDepth);

        /** @var array<string, mixed> $value */
        return $value;
    }

    private static function assertNoDuplicateObjectKeys(string $json, int $maximumDepth) : void
    {
        $offset = 0;
        self::scanValue($json, $offset, 1, $maximumDepth);
    }

    private static function scanValue(string $json, int &$offset, int $depth, int $maximumDepth) : void
    {
        self::skipWhitespace($json, $offset);
        $character = $json[$offset] ?? '';
        if ($character === '{') {
            if ($depth > $maximumDepth) {
                throw new MalformedDataException('JSON nesting exceeds its depth limit');
            }
            ++$offset;
            $keys = [];
            self::skipWhitespace($json, $offset);
            while (($json[$offset] ?? '') !== '}') {
                $raw = self::scanString($json, $offset);
                try {
                    $key = json_decode($raw, true, 2, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new MalformedDataException('Malformed JSON object key');
                }
                if (!is_string($key) || isset($keys[$key])) {
                    throw new MalformedDataException('Duplicate JSON object member');
                }
                $keys[$key] = true;
                self::skipWhitespace($json, $offset);
                ++$offset; // json_decode already proved this is a colon.
                self::scanValue($json, $offset, $depth + 1, $maximumDepth);
                self::skipWhitespace($json, $offset);
                if (($json[$offset] ?? '') === ',') {
                    ++$offset;
                    self::skipWhitespace($json, $offset);
                    continue;
                }
                break;
            }
            ++$offset;
            return;
        }
        if ($character === '[') {
            if ($depth > $maximumDepth) {
                throw new MalformedDataException('JSON nesting exceeds its depth limit');
            }
            ++$offset;
            self::skipWhitespace($json, $offset);
            while (($json[$offset] ?? '') !== ']') {
                self::scanValue($json, $offset, $depth + 1, $maximumDepth);
                self::skipWhitespace($json, $offset);
                if (($json[$offset] ?? '') === ',') {
                    ++$offset;
                    continue;
                }
                break;
            }
            ++$offset;
            return;
        }
        if ($character === '"') {
            self::scanString($json, $offset);
            return;
        }
        while (isset($json[$offset]) && !str_contains(",]} \t\r\n", $json[$offset])) {
            ++$offset;
        }
    }

    private static function scanString(string $json, int &$offset) : string
    {
        $start = $offset++;
        while (isset($json[$offset])) {
            if ($json[$offset] === '\\') {
                $offset += 2;
                continue;
            }
            if ($json[$offset++] === '"') {
                return substr($json, $start, $offset - $start);
            }
        }
        throw new MalformedDataException('Unterminated JSON string');
    }

    private static function skipWhitespace(string $json, int &$offset) : void
    {
        while (isset($json[$offset]) && str_contains(" \t\r\n", $json[$offset])) {
            ++$offset;
        }
    }
}
