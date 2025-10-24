<?php

use App\Http\Controllers\MentorOverviewController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\EndorsementController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\WaitingListController;
use App\Http\Controllers\FamiliarisationController;
use App\Http\Controllers\UserSearchController;
use App\Http\Controllers\TraineeOrderController;
use App\Http\Controllers\SoloController;

Route::get('/', function () {
    return redirect("/dashboard");
});

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

    // User search
    Route::middleware('can:mentor')->group(function () {
        Route::post('users/search', [UserSearchController::class, 'search'])->name('users.search');
        Route::get('users/{vatsimId}', [UserSearchController::class, 'show'])->name('users.profile');
    });

    // Mentor Overview
    Route::middleware(['can:mentor'])->group(function () {
        Route::get('overview', [MentorOverviewController::class, 'index'])
            ->name('overview');

        Route::post('overview/update-remark', [MentorOverviewController::class, 'updateRemark'])
            ->name('overview.update-remark');

        Route::post('overview/remove-trainee', [MentorOverviewController::class, 'removeTrainee'])
            ->name('overview.remove-trainee');

        Route::post('overview/claim-trainee', [MentorOverviewController::class, 'claimTrainee'])
            ->name('overview.claim-trainee');
        Route::post('overview/unclaim-trainee', [MentorOverviewController::class, 'unclaimTrainee'])
            ->name('overview.unclaim-trainee');
        Route::post('overview/assign-trainee', [MentorOverviewController::class, 'assignTrainee'])
            ->name('overview.assign-trainee');

        Route::get('/course/{course}/mentors', [MentorOverviewController::class, 'getCourseMentors'])->name('overview.get-course-mentors');
        Route::post('overview/add-mentor', [MentorOverviewController::class, 'addMentor'])
            ->name('overview.add-mentor');
        Route::post('overview/remove-mentor', [MentorOverviewController::class, 'removeMentor'])
            ->name('overview.remove-mentor');

        Route::get('overview/past-trainees/{course}', [MentorOverviewController::class, 'getPastTrainees'])
            ->name('overview.past-trainees');

        Route::post('overview/reactivate-trainee', [MentorOverviewController::class, 'reactivateTrainee'])
            ->name('overview.reactivate-trainee');

        Route::post('overview/add-trainee-to-course', [MentorOverviewController::class, 'addTraineeToCourse'])
            ->name('overview.add-trainee-to-course');

        Route::post('overview/update-trainee-order', [TraineeOrderController::class, 'updateOrder'])
            ->name('overview.update-trainee-order');
        Route::post('overview/reset-trainee-order', [TraineeOrderController::class, 'resetOrder'])
            ->name('overview.reset-trainee-order');

        Route::post('overview/grant-endorsement', [MentorOverviewController::class, 'grantEndorsement'])
            ->name('overview.grant-endorsement');

        Route::post('overview/solo/add', [SoloController::class, 'addSolo'])->name('overview.add-solo');
        Route::post('overview/solo/extend', [SoloController::class, 'extendSolo'])->name('overview.extend-solo');
        Route::post('overview/solo/remove', [SoloController::class, 'removeSolo'])->name('overview.remove-solo');
    });
});

require __DIR__.'/settings.php';
require __DIR__ . '/auth.php';