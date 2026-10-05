<?php

declare(strict_types=1);

return [
    'error_report' => [
        'required'           => 'A required value is missing.',
        'identity_conflict'  => 'The value conflicts with an existing record.',
        'duplicate_identity' => 'The identity appears more than once in this file.',
        'invalid'            => 'The value is invalid.',
        'provider_failed'    => 'The requested provider operation failed.',
    ],
    'provider_results' => [
        'retrying'            => 'Temporary provider failure; retry queued.',
        'manual_verification' => 'Provider account outcome is uncertain; manual verification is required.',
        'failed'              => 'Provider account creation failed.',
    ],
    'columns' => [
        'phone'              => 'Mobile phone',
        'email'              => 'Email',
        'first_name'         => 'First name',
        'last_name'          => 'Last name',
        'phone2'             => 'Secondary phone',
        'civil_id'           => 'Civil ID',
        'civil_id_type'      => 'Civil ID type',
        'date_of_birth'      => 'Date of birth (Jalali)',
        'father_name'        => "Father's name",
        'gender'             => 'Gender',
        'education_level'    => 'Education level',
        'field_of_study'     => 'Field of study',
        'education_status'   => 'Education status',
        'password'           => 'Password',
        'provision_moodle'   => 'Provision Moodle account',
        'provision_ims'      => 'Provision IMS account',
        'provision_niliroom' => 'Provision Niliroom account',
        'provision_skyroom'  => 'Provision Skyroom account',
    ],

    'fields' => [
        'file'         => 'Excel file',
        'identity_key' => 'Identity key',
    ],

    'guidance' => [
        'phone'              => 'Mobile phone number of the user; required for a new user. Example: 09123456789',
        'email'              => 'Email address of the user. Required when the identity key is "email".',
        'first_name'         => 'First name of the user; required for a new user.',
        'last_name'          => 'Last name of the user; required for a new user.',
        'phone2'             => 'Optional secondary mobile phone number.',
        'civil_id'           => 'National code, immigrant code or passport number; required for a new user and must match the civil ID type.',
        'civil_id_type'      => 'Allowed values: National Code, Immigrant Code or Passport. Persian labels and the raw keys national_code, immigrant_code and passport are also accepted.',
        'date_of_birth'      => 'Jalali date of birth in YYYY-MM-DD format; required for a new user. Example: 1370-01-01',
        'father_name'        => "Father's name of the user; required for a new user.",
        'gender'             => 'Allowed values: Male or Female. Persian labels and the raw keys male and female are also accepted.',
        'education_level'    => 'Optional education level. Example: Bachelor. Persian labels and raw keys such as bachelor are also accepted.',
        'field_of_study'     => 'Optional field of study.',
        'education_status'   => 'Optional education status. Example: Graduated. Persian labels and raw keys such as graduated are also accepted.',
        'password'           => 'Optional password with at least 8 characters. Leaving it empty keeps the current password, and it is never shown in the preview.',
        'provision_moodle'   => 'Set to true to create or ensure the user account on Moodle, otherwise leave it empty.',
        'provision_ims'      => 'Set to true to create or ensure the user account on IMS, otherwise leave it empty.',
        'provision_niliroom' => 'Set to true to create or ensure the user account on Niliroom, otherwise leave it empty.',
        'provision_skyroom'  => 'Set to true to create or ensure the user account on Skyroom, otherwise leave it empty.',
    ],

    'approval_warning' => 'No data has been changed. Approval will import all valid rows.',

    'template' => [
        'required'       => 'Required',
        'example'        => 'Example: :example',
        'english_key'    => 'English column name: :key',
        'example_notice' => 'This row is an example and must be removed before uploading.',
    ],

    'errors' => [
        'expired'                     => 'This import preview has expired. Upload the spreadsheet again to continue.',
        'empty'                       => 'The worksheet does not contain any data row.',
        'too_many_rows'               => 'At most :max data rows are allowed per file.',
        'unknown_columns'             => 'Unknown headings: :columns',
        'missing_columns'             => 'Missing required headings: :columns',
        'duplicate_identity'          => 'Identity ":identity" is duplicated in this file.',
        'invalid_boolean'             => 'The :column column must be true or false.',
        'include_valid_rows_required' => 'You must confirm that only valid rows should be imported.',
        'no_valid_rows'               => 'This import run has no valid rows to approve.',
        'snapshot_changed'            => 'The import preview no longer matches its stored source. Create a new preview.',
    ],
];
