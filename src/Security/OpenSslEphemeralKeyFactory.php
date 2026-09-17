<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Security;

use Bedriox\Protocol\Exception\CryptographicException;
use Bedriox\Protocol\Exception\InvalidValueException;

final class OpenSslEphemeralKeyFactory implements EphemeralKeyFactory
{
    public function __construct(private readonly ?string $configurationFile = null)
    {
        if ($configurationFile !== null && (!is_file($configurationFile) || !is_readable($configurationFile))) {
            throw new InvalidValueException('OpenSSL configuration file is not readable');
        }
    }

    public function generate() : P384KeyPair
    {
        $options = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp384r1',
        ];
        if ($this->configurationFile !== null) {
            $options['config'] = $this->configurationFile;
        }
        $private = @openssl_pkey_new($options);
        if ($private === false) {
            throw new CryptographicException('OpenSSL could not generate a P-384 key');
        }
        $details = openssl_pkey_get_details($private);
        if ($details === false || !isset($details['key']) || !is_string($details['key'])) {
            throw new CryptographicException('OpenSSL could not export the generated public key');
        }
        $public = openssl_pkey_get_public($details['key']);
        if ($public === false) {
            throw new CryptographicException('OpenSSL could not import the generated public key');
        }

        return new P384KeyPair($private, $public);
    }
}
