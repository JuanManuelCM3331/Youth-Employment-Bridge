<?php

namespace Modules\Identity\Contracts;

use Modules\Identity\Dto\CedulaRecord;

/**
 * Contrato de los proveedores que consultan la existencia de una cédula
 * en un registro de identidad (API externa o simulador local).
 */
interface CedulaProvider
{
    /**
     * Consulta la cédula y devuelve el registro encontrado.
     *
     * @throws \RuntimeException cuando el proveedor no puede responder.
     */
    public function lookup(string $cedula): CedulaRecord;
}
