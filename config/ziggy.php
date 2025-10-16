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

        // Course routes
        'courses',
        'courses.index',
        'courses.toggle-waiting-list',

        // Waiting list management routes
        'waiting-lists.manage',
        'waiting-lists.start-training',
        'waiting-lists.update-remarks',

        // Familiarisation routes
        'familiarisations.index',
        'familiarisations.user',

        // Find user 
        'users.search',
        'users.profile',
        'users.data',

        // Mentor Overview
        'overview',
        'overview.update-remark',
        'overview.remove-trainee',
        'overview.assign-trainee',
        'overview.claim-trainee',
        'overview.unclaim-trainee',
        'overview.get-course-mentors',
        'overview.add-mentor',
        'overview.remove-mentor',
        'overview.add-trainee-to-course'
    ],
];