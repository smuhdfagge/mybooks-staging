<?php

namespace App\Exceptions;

use LogicException;

/**
 * A tenant-scoped model was queried with no business to scope it to
 * (audit MB-26): wrap the work in Tenant::run($id, ...) or, when it really
 * is for every business, Tenant::acrossAll(...).
 */
class MissingTenantContext extends LogicException
{
    public function __construct(public readonly string $model)
    {
        parent::__construct("{$model} was queried with no tenant to scope it to. Wrap the work in Tenant::run(\$tenantId, ...) or, if it is meant for every business, Tenant::acrossAll(...).");
    }
}
