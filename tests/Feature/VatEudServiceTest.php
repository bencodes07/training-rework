<?php

namespace Tests\Feature;

use App\Services\VatEudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('vateud service fetches tier 1 endorsements', function () {
    Http::fake([
        'core.vateud.net/api/facility/endorsements/tier-1' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'user_cid' => 1234567,
                    'instructor_cid' => 7654321,
                    'position' => 'EDDF_TWR',
                    'facility' => 9,
                    'created_at' => '2025-01-15T12:00:00.000000Z',
                ],
                [
                    'id' => 2,
                    'user_cid' => 1234567,
                    'instructor_cid' => 7654321,
                    'position' => 'EDDF_APP',
                    'facility' => 9,
                    'created_at' => '2025-01-20T14:00:00.000000Z',
                ],
            ],
        ], 200),
    ]);

    $service = app(VatEudService::class);
    $endorsements = $service->getTier1Endorsements();

    expect($endorsements)->toBeArray()
        ->toHaveCount(2)
        ->and($endorsements[0]['position'])->toBe('EDDF_TWR');
});

test('vateud service caches tier 1 endorsements', function () {
    Http::fake([
        'core.vateud.net/api/facility/endorsements/tier-1' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'user_cid' => 1234567,
                    'position' => 'EDDF_TWR',
                ],
            ],
        ], 200),
    ]);

    $service = app(VatEudService::class);
    
    // First call - should hit API
    $service->getTier1Endorsements();
    
    // Second call - should use cache
    $service->getTier1Endorsements();

    Http::assertSentCount(1); // Only one actual API call
});

test('vateud service sorts endorsements correctly', function () {
    Http::fake([
        'core.vateud.net/api/facility/endorsements/tier-1' => Http::response([
            'data' => [
                ['id' => 1, 'user_cid' => 1234567, 'position' => 'EDDF_TWR'],
                ['id' => 2, 'user_cid' => 1234567, 'position' => 'EDWW_CTR'],
                ['id' => 3, 'user_cid' => 1234567, 'position' => 'EDDF_APP'],
                ['id' => 4, 'user_cid' => 1234567, 'position' => 'EDDF_GNDDEL'],
            ],
        ], 200),
    ]);

    $service = app(VatEudService::class);
    $endorsements = $service->getTier1Endorsements();

    // CTR should come first (0_CTR_*), then airport positions (1_AIRPORT_*)
    // Within airport: APP (1), TWR (2), GNDDEL (3)
    expect($endorsements[0]['position'])->toBe('EDWW_CTR') // CTR first
        ->and($endorsements[1]['position'])->toBe('EDDF_APP') // Then APP
        ->and($endorsements[2]['position'])->toBe('EDDF_TWR') // Then TWR
        ->and($endorsements[3]['position'])->toBe('EDDF_GNDDEL'); // Then GNDDEL
});

test('vateud service fetches tier 2 endorsements', function () {
    Http::fake([
        'core.vateud.net/api/facility/endorsements/tier-2' => Http::response([
            'data' => [
                [
                    'id' => 25,
                    'user_cid' => 1234567,
                    'instructor_cid' => 7654321,
                    'position' => 'EDXX_AFIS',
                    'facility' => 9,
                ],
            ],
        ], 200),
    ]);

    $service = app(VatEudService::class);
    $endorsements = $service->getTier2Endorsements();

    expect($endorsements)->toBeArray()
        ->toHaveCount(1)
        ->and($endorsements[0]['position'])->toBe('EDXX_AFIS');
});

test('vateud service fetches solo endorsements', function () {
    Http::fake([
        'core.vateud.net/api/facility/endorsements/solo' => Http::response([
            'data' => [
                [
                    'id' => 1903,
                    'user_cid' => 1234567,
                    'instructor_cid' => 7654321,
                    'position' => 'EDDL_TWR',
                    'expiry' => '2025-10-09T23:59:00.000000Z',
                ],
            ],
        ], 200),
    ]);

    $service = app(VatEudService::class);
    $endorsements = $service->getSoloEndorsements();

    expect($endorsements)->toBeArray()
        ->toHaveCount(1)
        ->and($endorsements[0]['position'])->toBe('EDDL_TWR');
});

test('vateud service removes tier 1 endorsement', function () {
    Http::fake([
        'core.vateud.net/api/facility/endorsements/tier-1/123' => Http::response([], 200),
    ]);

    $service = app(VatEudService::class);
    $result = $service->removeTier1Endorsement(123);

    expect($result)->toBeTrue();
    Http::assertSent(fn ($request) => 
        $request->url() === 'https://core.vateud.net/api/facility/endorsements/tier-1/123' &&
        $request->method() === 'DELETE'
    );
});

test('vateud service creates tier 2 endorsement', function () {
    Http::fake([
        'core.vateud.net/api/facility/endorsements/tier-2' => Http::response([
            'data' => [
                'id' => 999,
                'user_cid' => 1234567,
                'position' => 'EDXX_AFIS',
            ],
        ], 201),
    ]);

    $service = app(VatEudService::class);
    $result = $service->createTier2Endorsement(1234567, 'EDXX_AFIS', 7654321);

    expect($result)->toBeTrue();
    Http::assertSent(fn ($request) => 
        $request->url() === 'https://core.vateud.net/api/facility/endorsements/tier-2' &&
        $request->method() === 'POST' &&
        $request['user_cid'] === 1234567 &&
        $request['position'] === 'EDXX_AFIS'
    );
});

test('vateud service handles api errors gracefully', function () {
    Http::fake([
        'core.vateud.net/api/facility/endorsements/tier-1' => Http::response([], 500),
    ]);

    $service = app(VatEudService::class);
    $endorsements = $service->getTier1Endorsements();

    expect($endorsements)->toBeArray()->toBeEmpty();
});

test('vateud service uses mock data when configured', function () {
    config(['services.vateud.use_mock' => true]);

    $service = app(VatEudService::class);
    $endorsements = $service->getTier1Endorsements();

    expect($endorsements)->toBeArray()
        ->and($endorsements[0]['position'])->toBe('EDDL_TWR')
        ->and($endorsements[1]['position'])->toBe('EDDL_APP');
});

test('vateud service sends correct headers', function () {
    config(['services.vateud.token' => 'test_api_key']);

    Http::fake([
        'core.vateud.net/*' => Http::response(['data' => []], 200),
    ]);

    $service = app(VatEudService::class);
    $service->getTier1Endorsements();

    Http::assertSent(fn ($request) => 
        $request->hasHeader('X-API-KEY', 'test_api_key') &&
        $request->hasHeader('Accept', 'application/json') &&
        $request->hasHeader('User-Agent', 'VATGER Training System')
    );
});