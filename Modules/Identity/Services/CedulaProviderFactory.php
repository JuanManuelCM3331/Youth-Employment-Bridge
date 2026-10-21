<?php

namespace Modules\Identity\Services;

use Modules\Identity\Contracts\CedulaProvider;
use Modules\Identity\Dto\CedulaConfig;
use Modules\Identity\Dto\CedulaRecord;
use RuntimeException;

/**
 * Fábrica del proveedor de cédulas configurado en el portal.
 */
final class CedulaProviderFactory
{
    public static function make(CedulaConfig $config): CedulaProvider
    {
        if ($config->provider === 'http' && trim($config->url) !== '') {
            return new \Modules\Identity\Providers\HttpCedulaProvider(
                $config,
                new \Modules\Identity\Clients\CurlHttpTransport(),
                new \Modules\Identity\Support\CedulaResponseMapper()
            );
        }

        return new \Modules\Identity\Providers\SandboxCedulaProvider();
    }

    public static function fromConfigFile(): CedulaProvider
    {
        $config = require __DIR__ . '/../../../config/cedula.php';

        return self::make(CedulaConfig::fromArray($config));
    }

    public static function configFromFile(): CedulaConfig
    {
        $config = require __DIR__ . '/../../../config/cedula.php';

        return CedulaConfig::fromArray($config);
    }
}
