<?php
// Reference only. Copy the config.php template and fill its placeholders on the server.
// Never put real keys in this example file.
return [
    'lsq_host'       => 'https://api-in21.leadsquared.com',
    'lsq_access_key' => 'REPLACE_ON_SERVER',
    'lsq_secret_key' => 'REPLACE_ON_SERVER',
    'lsq_source'     => 'Google Ad',
    'courses'        => [
        'REPLACE_COURSE_SLUG' => 'REPLACE_EXACT_LSQ_COURSE_VALUE',
    ],
    'course_field'   => 'mx_Courses',
    'allowed_values' => [
        'profile'       => ['REPLACE_PROFILE_VALUE'],
        'enquiry_type'  => ['REPLACE_ENQUIRY_TYPE'],
        'form_location' => ['hero', 'popup', 'footer'],
    ],
    'field_map'      => [
        'profile'        => '',
        'enquiry_type'   => '',
        'enquiry_detail' => 'mx_Enquiry_Detail',
        'form_location'  => '',
        'utm_term'       => '',
        'landing_page'   => '',
        'gclid'          => '',
        'gbraid'         => '',
        'wbraid'         => '',
    ],
    'allowed_origins'    => [],
    'activity_code'      => 0,
    'rate_limit'         => 12,
    'log_retention_days' => 7,
    'dry_run'            => true,
    'storage_dir'        => __DIR__ . '/storage',
];
