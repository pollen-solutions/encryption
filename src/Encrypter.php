<?php

declare(strict_types=1);

namespace Pollen\Encryption;

use Exception;
use Pollen\Support\Exception\ManagerRuntimeException;
use RuntimeException;

class Encrypter implements EncrypterInterface
{
    /**
     * Encrypter main instance.
     * @var static|null
     */
    private static ?EncrypterInterface $instance = null;

    /**
     * Secret hash key.
     * @var string
     */
    private string $key = '';

    /**
     * Cryptography algorithm.
     * @var string AES-128-CBC|AES-256-CBC
     */
    private string $cipher;

    /**
     * @param string $key
     * @param string $cipher AES-128-CBC|AES-256-CBC.
     */
    public function __construct(string $key, string $cipher = 'AES-128-CBC')
    {
        if (static::supported($key, $cipher)) {
            $this->key = $key;
            $this->cipher = $cipher;
        } else {
            throw new RuntimeException(
                'Encrypter only supports AES-128-CBC and AES-256-CBC cyphers with the correct key lengths'
            );
        }

        if (!self::$instance instanceof static) {
            self::$instance = $this;
        }
    }

    /**
     * Retrieves encrypter main instance.
     *
     * @return static
     */
    public static function getInstance(): EncrypterInterface
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }
        throw new ManagerRuntimeException(sprintf('Unavailable [%s] instance', __CLASS__));
    }

    /**
     * Check if secret hash key and Cryptography algorithm are supported.
     *
     * @param string $key
     * @param string $cipher AES-128-CBC|AES-256-CBC
     *
     * @return bool
     */
    public static function supported(string $key, string $cipher): bool
    {
        if (!in_array($cipher, ['AES-128-CBC', 'AES-256-CBC'], true)) {
            throw new RuntimeException(
                'Only AES-128-CBC and AES-256-CBC are supported by Encrypter.'
            );
        }

        $length = mb_strlen($key, '8bit');

        return ($cipher === 'AES-128-CBC' && $length === 16) ||
            ($cipher === 'AES-256-CBC' && $length === 32);
    }

    /**
     * Generate a secret hash key.
     *
     * @param string $cipher AES-128-CBC|AES-256-CBC
     *
     * @return string
     */
    public static function generateKey(string $cipher): string
    {
        try {
            return random_bytes($cipher === 'AES-128-CBC' ? 16 : 32);
        } catch (Exception $e) {
            throw new RuntimeException('Unable to generate encryption key');
        }
    }

    /**
     * @inheritDoc
     */
    public function decrypt(string $hash): string
    {
        $payload = $this->getPayload($hash);
        $iv = base64_decode($payload['iv']);

        $decrypted = openssl_decrypt(
            $payload['value'], $this->cipher, $this->key, 0, $iv
        );

        if ($decrypted === false) {
            throw new RuntimeException('Unable to decrypt the data');
        }

        return $decrypted;
    }

    /**
     * @inheritDoc
     */
    public function encrypt(string $plain): string
    {
        try {
            $randomIV = random_bytes(openssl_cipher_iv_length($this->cipher));
        } catch (Exception $e) {
            throw new RuntimeException('Encrypt could not create Initialization Vector');
        }

        if (!$value = openssl_encrypt($plain, $this->cipher, $this->key, 0, $randomIV)) {
            throw new RuntimeException('Unable to encrypt the data');
        }

        $mac = $this->hash($iv = base64_encode($randomIV), $value);

        try {
            $json = json_encode(compact('iv', 'value', 'mac'), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            throw new RuntimeException('Encrypt could not create MAC for encrypted value');
        }

        return base64_encode($json);
    }

    /**
     * Gets payload datas from a given hash.
     *
     * @param string $hash
     *
     * @return array
     */
    protected function getPayload(string $hash): array
    {
        try {
            $payload = json_decode(base64_decode($hash), true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            throw new RuntimeException('Could not resolve the payload');
        }

        if (! $this->isValidPayload($payload)) {
            throw new RuntimeException('The payload is invalid');
        }

        if (! $this->isValidPayloadMac($payload)) {
            throw new RuntimeException('The MAC of payload is invalid');
        }

        return $payload;
    }

    /**
     * Create the hash mac for value.
     *
     * @param string $iv
     * @param mixed $value
     *
     * @return string
     */
    protected function hash(string $iv, $value): string
    {
        return hash_hmac('sha256', $iv.$value, $this->key);
    }

    /**
     * Check if an encryption payload is valid.
     *
     * @param array $payload
     *
     * @return bool
     */
    protected function isValidPayload(array $payload): bool
    {
        return isset($payload['iv'], $payload['value'], $payload['mac']) &&
            strlen(base64_decode($payload['iv'], true)) === openssl_cipher_iv_length($this->cipher);
    }

    /**
     * Check payload data integrity.
     *
     * @param array $payload
     *
     * @return bool
     */
    protected function isValidPayloadMac(array $payload): bool
    {
        return hash_equals(
            $this->hash($payload['iv'], $payload['value']), $payload['mac']
        );
    }
}