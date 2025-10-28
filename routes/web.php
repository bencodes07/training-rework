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
use App\Http\Controllers\TrainingLogController;

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

    // Mentor Overview
    Route::middleware(['can:mentor'])->group(function () {

        Route::post('users/search', [UserSearchController::class, 'search'])->name('users.search');
        Route::get('users/{vatsimId}', [UserSearchController::class, 'show'])->name('users.profile');

        Route::get('/course/{course}/mentors', [MentorOverviewController::class, 'getCourseMentors'])->name('overview.get-course-mentors');

        Route::prefix('overview')->name('overview.')->group(function () {
            Route::get('/', [MentorOverviewController::class, 'index'])
                ->name('index');

            Route::post('/update-remark', [MentorOverviewController::class, 'updateRemark'])
                ->name('update-remark');

            Route::post('/remove-trainee', [MentorOverviewController::class, 'removeTrainee'])
                ->name('remove-trainee');

            Route::post('/finish-trainee', [MentorOverviewController::class, 'finishCourse'])
                ->name('finish-trainee');

            Route::post('/claim-trainee', [MentorOverviewController::class, 'claimTrainee'])
                ->name('claim-trainee');
            Route::post('/unclaim-trainee', [MentorOverviewController::class, 'unclaimTrainee'])
                ->name('unclaim-trainee');
            Route::post('/assign-trainee', [MentorOverviewController::class, 'assignTrainee'])
                ->name('assign-trainee');

            Route::post('/add-mentor', [MentorOverviewController::class, 'addMentor'])
                ->name('add-mentor');
            Route::post('/remove-mentor', [MentorOverviewController::class, 'removeMentor'])
                ->name('remove-mentor');

            Route::get('/past-trainees/{course}', [MentorOverviewController::class, 'getPastTrainees'])
                ->name('past-trainees');

            Route::post('/reactivate-trainee', [MentorOverviewController::class, 'reactivateTrainee'])
                ->name('reactivate-trainee');

            Route::post('/add-trainee-to-course', [MentorOverviewController::class, 'addTraineeToCourse'])
                ->name('add-trainee-to-course');

            Route::post('/update-trainee-order', [TraineeOrderController::class, 'updateOrder'])
                ->name('update-trainee-order');
            Route::post('/reset-trainee-order', [TraineeOrderController::class, 'resetOrder'])
                ->name('reset-trainee-order');

            Route::post('/grant-endorsement', [MentorOverviewController::class, 'grantEndorsement'])
                ->name('grant-endorsement');

            Route::post('/solo/add', [SoloController::class, 'addSolo'])->name('add-solo');
            Route::post('/solo/extend', [SoloController::class, 'extendSolo'])->name('extend-solo');
            Route::post('/solo/remove', [SoloController::class, 'removeSolo'])->name('remove-solo');
        });
    });

    Route::prefix('training-logs')->name('training-logs.')->group(function () {

        // List all logs (filtered by permission)
        Route::get('/', [TrainingLogController::class, 'index'])
            ->name('index');

        // Create new log
        Route::get('/create/{traineeId}/{courseId}', [TrainingLogController::class, 'create'])
            ->name('create')
            ->middleware('can:create,App\Models\TrainingLog');

        // Store new log
        Route::post('/', [TrainingLogController::class, 'store'])
            ->name('store')
            ->middleware('can:create,App\Models\TrainingLog');

        // View specific log
        Route::get('/{id}', [TrainingLogController::class, 'show'])
            ->name('show');

        // Edit log
        Route::get('/{id}/edit', [TrainingLogController::class, 'edit'])
            ->name('edit');

        // Update log
        Route::put('/{id}', [TrainingLogController::class, 'update'])
            ->name('update');

        // Delete log
        Route::delete('/{id}', [TrainingLogController::class, 'destroy'])
            ->name('destroy');
    });

    // API routes for fetching logs
    Route::prefix('api/training-logs')->name('api.training-logs.')->group(function () {

        // Get logs for a specific trainee
        Route::get('/trainee/{traineeId}', [TrainingLogController::class, 'getTraineeLogs'])
            ->name('trainee');

        // Get logs for a specific course
        Route::get('/course/{courseId}', [TrainingLogController::class, 'getCourseLogs'])
            ->name('course');

        // Get statistics for a trainee
        Route::get('/trainee/{traineeId}/statistics', [TrainingLogController::class, 'getTraineeStatistics'])
            ->name('trainee.statistics');
    });
});

require __DIR__.'/settings.php';
require __DIR__ . '/auth.php';