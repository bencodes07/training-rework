<?php

return [
    'only' => [
        'login',
        'auth.vatsim',
        'auth.vatsim.callback',
        'admin.login',
        'admin.login.store',
        'dashboard',
        'logout',
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'password.edit',
        'password.update',
        'appearance.edit',
        'home',
        'courses',
        // Add endorsement routes
        'endorsements',
        'endorsements.trainee',
        'endorsements.manage',
        'endorsements.tier1.remove',
        'endorsements.tier2.request',
    ],
];