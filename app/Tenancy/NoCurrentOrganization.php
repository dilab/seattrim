<?php

namespace App\Tenancy;

use RuntimeException;

class NoCurrentOrganization extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No current organization is set. Tenant-scoped models cannot be queried or created outside Tenancy::runAs() or an organization-scoped request.');
    }
}
