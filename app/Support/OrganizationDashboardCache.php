<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class OrganizationDashboardCache
{
    public static function flushForOrganization(string $organizationId): void
    {
        Cache::store('tenant_dashboard')
            ->tags('tenant_dashboard:organization_' . $organizationId)
            ->flush();
    }
}

