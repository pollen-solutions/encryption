<?php

declare(strict_types=1);

namespace Pollen\Encryption;

use Pollen\Container\BootableServiceProvider;
use Pollen\Support\Env;

class EncryptionServiceProvider extends BootableServiceProvider
{
    /**
     * @var string[]
     */
    protected $provides = [
        EncrypterInterface::class
    ];

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->getContainer()->share(EncrypterInterface::class, function () {
            $cipher = Env::get('APP_CIPHER', 'AES-256-CBC');
            $key = Env::get('APP_KEY', Encrypter::generateKey($cipher));

            return new Encrypter($key, $cipher);
        });
    }
}