<?php

return [
    'only' => [
        // Auth routes
        'login',
        'auth.vatsim',
        'auth.vatsim.callback',
        'admin.login',
        'admin.login.store',
        'logout',

        // Main app routes
        'dashboard',
        'home',

        // Profile/Settings routes
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'password.edit',
        'password.update',
        'appearance.edit',

        // Endorsement routes
        'endorsements',
        'endorsements.trainee',
        'endorsements.manage',
        'endorsements.tier1.remove',
        'endorsements.tier2.request',

        // Course routes (NEW)
        'courses',
        'courses.index',
        'courses.toggle-waiting-list',

        // Waiting list management routes (NEW)
        'waiting-lists.manage',
        'waiting-lists.start-training',
        'waiting-lists.update-remarks',

        // Familiarisation routes (NEW)
        'familiarisations.index',
        'familiarisations.user',

        'users.search',
        'users.profile',
        'users.data',
    ],
];