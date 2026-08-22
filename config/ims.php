<?php

return [

    'api_base_url' => 'http://ims.nstu.local/api',

    // X-API-KEY of the unified "WebAPIClient" registered in IMS AdminX. Used
    // server-side only (never exposed to the browser) to read guarded IMS
    // endpoints (e.g. additional-contacts, office-addresses).
    'api_key' => env('IMS_API_KEY', ''),

    // Public dashboard profile URL. Owners are linked here (edit mode) to manage
    // contact/office info, which IMS owns as the source of truth.
    'dashboard_profile_url' => env('DASHBOARD_PROFILE_URL', 'http://dashboard.nstu.local/profile'),

    'cache_update_threshold' => 10, // in minutes, default to 60 minutes if not set
    'teacher_directory_cache_update_threshold' => 15,
    'personnel_cache_update_threshold' => 15,

    // How long (minutes) to cache IMS contacts/office lookups.
    'contact_cache_threshold' => (int) env('IMS_CONTACT_CACHE_MINUTES', 5),

];
