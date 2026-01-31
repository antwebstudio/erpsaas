<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use App\Concerns\CompanyOwned;

class Role extends SpatieRole
{
    use CompanyOwned;
    //
}
