<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class VatsimConnectService
{
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;
    protected $authUrl;
    protected $tokenUrl;
    protected $apiBaseUrl;

    public function __construct()
    {
        $this->clientId = config('services.vatsim.client_id');
        $this->clientSecret = config('services.vatsim.client_secret');
        $this->redirectUri = config('services.vatsim.redirect_uri');
        $this->authUrl = config('services.vatsim.auth_url');
        $this->tokenUrl = config('services.vatsim.token_url');
        $this->apiBaseUrl = config('services.vatsim.api_base_url');
    }

    /**
     * Generate authorization URL for VATSIM Connect
     * 
     * @return string
     */
    public function getAuthorizationUrl(): string
    {
        $state = Str::random(40);
        
        // Store state in cache for verification
        Cache::put('oauth_state_' . $state, true, now()->addMinutes(10));

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'full_name email vatsim_details country',
            'state' => $state,
        ];

        return $this->authUrl . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     * 
     * @param string $code
     * @param string|null $state
     * @return array
     * @throws \Exception
     */
    public function getAccessToken(string $code, ?string $state = null): array
    {
        // Verify state if provided
        if ($state && !Cache::get('oauth_state_' . $state)) {
            throw new \Exception('Invalid OAuth state parameter');
        }

        // Remove state from cache
        if ($state) {
            Cache::forget('oauth_state_' . $state);
        }

        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to obtain access token: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get user profile from VATSIM Connect API
     * 
     * @param string $accessToken
     * @return array
     * @throws \Exception
     */
    public function getUserProfile(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
            'User-Agent' => 'VATGER Training System',
        ])->get($this->apiBaseUrl . '/user');

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch user profile: ' . $response->body());
        }

        $rawProfile = $response->json();

        // Log the raw response for debugging
        Log::info('VATSIM Connect raw response', ['response' => $rawProfile]);

        // VATSIM Connect returns data in a 'data' wrapper
        $profile = $rawProfile['data'] ?? $rawProfile;

        // Build standardized profile
        $transformedProfile = [
            'id' => $profile['cid'] ?? null,
            'firstname' => $profile['personal']['name_first'] ?? null,
            'lastname' => $profile['personal']['name_last'] ?? null,
            'email' => $profile['personal']['email'] ?? null,
            'rating_atc' => $profile['vatsim']['rating']['id'] ?? 1,
            'subdivision_code' => $profile['vatsim']['subdivision']['id'] ?? null,
            'last_rating_change_at' => null,
            'teams' => []
        ];

        // Extract region/division staff roles if available
        if (isset($profile['vatsim']['region']['staff']) && is_array($profile['vatsim']['region']['staff'])) {
            foreach ($profile['vatsim']['region']['staff'] as $staff) {
                if (isset($staff['position']['name'])) {
                    $transformedProfile['teams'][] = $staff['position']['name'];
                }
            }
        }

        if (isset($profile['vatsim']['division']['staff']) && is_array($profile['vatsim']['division']['staff'])) {
            foreach ($profile['vatsim']['division']['staff'] as $staff) {
                if (isset($staff['position']['name'])) {
                    $transformedProfile['teams'][] = $staff['position']['name'];
                }
            }
        }

        // Validate that we have the minimum required data
        if (!$transformedProfile['id']) {
            throw new \Exception('Could not extract user ID from VATSIM Connect response. Raw response: ' . json_encode($rawProfile));
        }

        if (!$transformedProfile['firstname'] || !$transformedProfile['lastname']) {
            throw new \Exception('Could not extract user name from VATSIM Connect response. Raw response: ' . json_encode($rawProfile));
        }

        Log::info('VATSIM Connect transformed profile', ['profile' => $transformedProfile]);

        return $transformedProfile;
    }
}