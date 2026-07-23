<?php

namespace App\Policies;

/**
 * Principals follow the same rule as the rest of the app: HR/admin may do anything,
 * crewing officers may work with them but not delete. Registered as gates in
 * AppServiceProvider, since the "user" here is an ERP HPY session.
 */
class PrincipalPolicy extends CrewCandidatePolicy
{
}
