# Gateway control protocol

The Gateway control codec is a transport-neutral, non-Bedrock application
framing contract for a dedicated authenticated Gateway-to-Bedriox session. It
does not allocate sockets, admit peers, maintain session state, or authorize
server behavior.

Each frame starts with `BXGC`, version `1`, a typed message byte, bounded flags,
a 16-byte request identifier, an unsigned 32-bit sequence, an unsigned 32-bit
payload length, and at most 65,536 payload bytes. Decoding rejects unknown
versions, types, flags, oversized payloads, truncation, and trailing data.

`ControlHandshakeToken` signs bounded hello, challenge, and authentication
claims using HMAC-SHA-256. Claims bind the issuer, destination audience, key
identifier, two 32-byte random nonces, and a time window of no more than 60
seconds. Consumers validate the expected values, wall-clock window, key
rotation policy, and replay state before deriving a session key.

After authentication, consumers use independent `gateway-to-server` and
`server-to-gateway` `ControlFrameCipher` instances. AES-256-GCM authenticates
the message type, request ID, sequence, direction, and payload. Session owners
must begin sequences at zero, reject repeats and gaps according to their
policy, and reconnect before the 32-bit sequence space is exhausted.

Handshake messages are `Hello`, `Challenge`, `Authenticate`, and `Ready`.
Authenticated messages include health, server status and stopping notices,
drain requests and responses, and bounded namespaced plugin messages. The
codec deliberately leaves payload schemas and authorization to Gateway and
Bedriox.
