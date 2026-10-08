<?php

use App\Models\OrgUnit;
use App\Services\Access;

if (! function_exists('can_do')) {
    /** Server-side permission check usable in views and controllers. */
    function can_do(string $permission, ?OrgUnit $unit = null): bool
    {
        return app(Access::class)->can(auth()->user(), $permission, $unit);
    }
}
