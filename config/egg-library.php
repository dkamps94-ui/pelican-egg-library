<?php

return [
    // Let every logged-in user browse the library and install eggs (default: only users allowed to create eggs)
    'allow_user_install' => (bool) env('EGG_LIBRARY_ALLOW_USER_INSTALL', false),

    // Egg installs per user and hour
    'installs_per_hour' => (int) env('EGG_LIBRARY_INSTALLS_PER_HOUR', 10),

    // Look up Steam artwork for catalog eggs automatically (hourly, a few eggs per run)
    'image_search' => (bool) env('EGG_LIBRARY_IMAGE_SEARCH', true),
];
