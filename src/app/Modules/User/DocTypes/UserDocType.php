<?php

namespace App\Modules\User\DocTypes;

/**
 * UserDocType — defines the "User" DocType fields and table structure.
 *
 * These fields mirror the migration. They are the single source of truth
 * for what data the DocType stores.
 */
class UserDocType
{
    public const NAME   = 'User';
    public const TABLE  = 'pascal_users';

    public const FIELDS = [
        ['fieldname' => 'name',              'fieldtype' => 'Data',     'label' => 'Username',      'required' => true, 'unique' => true],
        ['fieldname' => 'full_name',         'fieldtype' => 'Data',     'label' => 'Full Name',     'required' => true],
        ['fieldname' => 'email',             'fieldtype' => 'Data',     'label' => 'Email',         'required' => true, 'unique' => true],
        ['fieldname' => 'password',          'fieldtype' => 'Password', 'label' => 'Password',      'required' => true],
        ['fieldname' => 'status',            'fieldtype' => 'Select',   'label' => 'Status',        'options'  => ['Active', 'Inactive', 'Banned'], 'default' => 'Active'],
        ['fieldname' => 'role',              'fieldtype' => 'Select',   'label' => 'Role',          'options'  => ['user', 'manager', 'admin'],     'default' => 'user'],
        ['fieldname' => 'avatar',            'fieldtype' => 'Attach',   'label' => 'Avatar',        'required' => false],
        ['fieldname' => 'phone',             'fieldtype' => 'Data',     'label' => 'Phone',         'required' => false],
        ['fieldname' => 'email_verified_at', 'fieldtype' => 'Datetime', 'label' => 'Email Verified','required' => false, 'read_only' => true],
        ['fieldname' => 'last_login_at',     'fieldtype' => 'Datetime', 'label' => 'Last Login',   'required' => false, 'read_only' => true],
        ['fieldname' => 'last_login_ip',     'fieldtype' => 'Data',     'label' => 'Last Login IP', 'required' => false, 'read_only' => true],
    ];

    public static function options(): array
    {
        return [
            'module'         => 'User',
            'is_submittable' => false,
            'track_changes'  => true,
        ];
    }
}
