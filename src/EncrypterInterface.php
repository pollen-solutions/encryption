<?php

declare(strict_types=1);

namespace Pollen\Encryption;

interface EncrypterInterface
{
    /**
     * Decrypts a hash string.
     *
     * @param string $hash
     *
     * @return string
     */
    public function decrypt(string $hash): string;

    /**
     * Encrypts a plain string.
     *
     * @param string $plain
     *
     * @return string
     */
    public function encrypt(string $plain): string;
}