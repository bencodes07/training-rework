<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\EndorsementController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\WaitingListController;
use App\Http\Controllers\FamiliarisationController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Endorsement routes
    Route::prefix('endorsements')->name('endorsements.')->group(function () {
        // Trainee endorsement view (main route)
        Route::get('/my-endorsements', [EndorsementController::class, 'traineeView'])
            ->name('trainee');

        // Mentor/Management routes
        Route::middleware('can:mentor')->group(function () {
            Route::get('/manage', [EndorsementController::class, 'mentorView'])
                ->name('manage');

            Route::delete('/tier1/{endorsementId}/remove', [EndorsementController::class, 'removeTier1'])
                ->name('tier1.remove');
        });

        // Tier 2 endorsement requests
        Route::post('/tier2/{tier2Id}/request', [EndorsementController::class, 'requestTier2'])
            ->name('tier2.request');
    });

    // Add this route for compatibility with the sidebar
    Route::get('endorsements/my-endorsements', [EndorsementController::class, 'traineeView'])
        ->name('endorsements');

    // Course routes for trainees
    Route::prefix('courses')->name('courses.')->group(function () {
        Route::get('/', [CourseController::class, 'index'])->name('index');
        Route::post('/{course}/waiting-list', [CourseController::class, 'toggleWaitingList'])->name('toggle-waiting-list');
    });

    Route::get('/debug/courses', function () {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Not authenticated']);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'vatsim_id' => $user->vatsim_id,
                'rating' => $user->rating,
                'subdivision' => $user->subdivision,
                'is_vatsim_user' => $user->isVatsimUser(),
            ],
            'total_courses' => \App\Models\Course::count(),
            'courses_for_rating' => \App\Models\Course::forRating($user->rating ?? 1)->count(),
            'available_courses' => \App\Models\Course::forRating($user->rating ?? 1)
                ->availableFor($user)
                ->get()
                ->map(function ($course) {
                    return [
                        'id' => $course->id,
                        'name' => $course->name,
                        'trainee_display_name' => $course->trainee_display_name,
                        'type' => $course->type,
                        'min_rating' => $course->min_rating,
                        'max_rating' => $course->max_rating,
                    ];
                }),
        ]);
    });

    // Waiting list management for mentors
    Route::prefix('waiting-lists')->name('waiting-lists.')->middleware('can:mentor')->group(function () {
        Route::get('/manage', [WaitingListController::class, 'mentorView'])->name('manage');
        Route::post('/{entry}/start-training', [WaitingListController::class, 'startTraining'])->name('start-training');
        Route::post('/update-remarks', [WaitingListController::class, 'updateRemarks'])->name('update-remarks');
    });

    // Familiarisation routes
    Route::prefix('familiarisations')->name('familiarisations.')->group(function () {
        Route::get('/', [FamiliarisationController::class, 'index'])->name('index');
        Route::get('/my-familiarisations', [FamiliarisationController::class, 'userFamiliarisations'])->name('user');
    });
});

require __DIR__.'/settings.php';
require __DIR__ . '/auth.php';