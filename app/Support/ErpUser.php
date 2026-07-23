<?php

namespace App\Support;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Support\Facades\Session;

/**
 * The signed-in user, as far as this app is concerned: an ERP HPY account held in the
 * session. There is no local users table, so authorisation reads the ERP HPY roles
 * captured at login.
 */
class ErpUser
{
    /** Session key holding the role names of the signed-in user. */
    public const ROLES_KEY = 'erpnext.roles';

    /** ERP HPY user id (email), or null when nobody is signed in. */
    public static function id(): ?string
    {
        return Session::get(ErpnextClient::SESSION_KEY . '.user');
    }

    public static function name(): ?string
    {
        return Session::get(ErpnextClient::SESSION_KEY . '.full_name');
    }

    /** @return array<int, string> */
    public static function roles(): array
    {
        return (array) Session::get(self::ROLES_KEY, []);
    }

    public static function hasAnyRole(string ...$roles): bool
    {
        return array_intersect($roles, self::roles()) !== [];
    }
}
