<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('courses', function () { // Trainee Waiting list page
        return Inertia::render('training/courses');
    })->name('courses');

    Route::get('endorsements/my-endorsements', function () { // Trainee endorsements page
        return Inertia::render('endorsements/trainee');
    })->name('endorsements');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
