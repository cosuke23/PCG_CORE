<?php

namespace App\Support;

/**
 * Role -> capability matrix for admin accounts (owner-approved role set,
 * 2026-10-31). Routes use the admin.can:<capability> middleware; views and
 * controllers call AdminPermissions::can(). Data scope (which personnel) is
 * separate: App\Services\OrgScope.
 *
 * The exact split between roles is a proposal to be confirmed against PCG
 * procedures; it lives only here so it can be changed in one place.
 */
class AdminPermissions
{
    public const MATRIX = [
        'personnel.view'       => ['super_admin', 'hr_admin', 'records_reviewer', 'records_approver', 'auditor'],
        'personnel.edit'       => ['super_admin', 'hr_admin'],
        'registration.create'  => ['super_admin', 'hr_admin'],
        'registration.review'  => ['super_admin', 'hr_admin', 'records_reviewer', 'records_approver'],
        'registration.approve' => ['super_admin', 'hr_admin', 'records_approver'],
        'documents.view'       => ['super_admin', 'hr_admin', 'records_reviewer', 'records_approver', 'auditor'],
        'documents.upload'     => ['super_admin', 'hr_admin'],
        'documents.verify'     => ['super_admin', 'hr_admin', 'records_reviewer', 'records_approver'],
        'documents.restricted' => ['super_admin', 'records_approver'],
        'reports.view'         => ['super_admin', 'hr_admin', 'records_reviewer', 'records_approver', 'auditor'],
        'org.view'             => ['super_admin', 'hr_admin', 'records_reviewer', 'records_approver', 'auditor'],
        'org.manage'           => ['super_admin'],
        'audit.view'           => ['super_admin', 'auditor'],
    ];

    public static function allows(?string $role, string $capability): bool
    {
        return $role !== null && in_array($role, self::MATRIX[$capability] ?? [], true);
    }

    public static function can(string $capability): bool
    {
        return self::allows(session('admin_role'), $capability);
    }

    /** Roles that may be limited to a command/unit. */
    public static function scopableRoles(): array
    {
        return ['hr_admin', 'records_reviewer', 'records_approver', 'auditor'];
    }
}
