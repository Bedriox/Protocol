# Troubleshooting

## Composer rejects the platform

Use 64-bit PHP `^8.4`. The unsigned 64-bit limb representation and signed bit operations are deliberately unsupported on 32-bit PHP.

## A decoder reports non-canonical input

The VarInt codecs reject redundant encodings, overflow, and unterminated continuation bytes. Preserve the original bytes as a sanitized regression fixture and verify the field type against the supported protocol definition.

## A string is rejected

String limits are measured in bytes, not Unicode characters. Inputs must be valid UTF-8 and fit both the declared wire length and the caller-provided maximum.

