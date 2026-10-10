<?php

return [
    'timezone' => env('AGENCYOS_TIMEZONE', 'Asia/Kolkata'),
    'currency' => env('AGENCYOS_CURRENCY', 'INR'),
    'billing_permissions' => ['subscriptions.view', 'subscriptions.create', 'subscriptions.renew', 'subscriptions.manage', 'billing.view', 'billing.manage'],
    'roles' => [
        'agency_owner' => ['agency.manage', 'employees.manage', 'clients.view', 'clients.create', 'clients.edit', 'clients.delete', 'projects.view', 'projects.create', 'projects.edit', 'projects.delete', 'websites.view', 'websites.create', 'websites.edit', 'websites.delete', 'activity.view'],
        'seo_manager' => ['clients.view', 'projects.view', 'projects.create', 'projects.edit', 'websites.view', 'websites.create', 'websites.edit', 'activity.view'],
        'seo_executive' => ['projects.view', 'websites.view'],
        'content_writer' => ['projects.view'],
        'developer' => ['projects.view', 'websites.view'],
        'client' => ['client.portal'],
    ],
];
