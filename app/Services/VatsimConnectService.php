<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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

        // VATSIM Connect returns data in a 'data' wrapper
        $profile = $rawProfile;
        if (isset($rawProfile['data'])) {
            $profile = $rawProfile['data'];
        }

        // Handle different possible response formats
        $transformedProfile = [
            'id' => null,
            'firstname' => null,
            'lastname' => null,
            'email' => null,
            'rating_atc' => 1,
            'subdivision_code' => null,
            'last_rating_change_at' => null,
            'teams' => []
        ];

        // Try to extract CID/ID from various possible locations
        if (isset($profile['cid'])) {
            $transformedProfile['id'] = $profile['cid'];
        } elseif (isset($profile['id'])) {
            $transformedProfile['id'] = $profile['id'];
        } elseif (isset($profile['vatsim_id'])) {
            $transformedProfile['id'] = $profile['vatsim_id'];
        }

        // Try to extract name from various possible locations
        if (isset($profile['personal']['name_first'])) {
            $transformedProfile['firstname'] = $profile['personal']['name_first'];
        } elseif (isset($profile['name_first'])) {
            $transformedProfile['firstname'] = $profile['name_first'];
        } elseif (isset($profile['first_name'])) {
            $transformedProfile['firstname'] = $profile['first_name'];
        } elseif (isset($profile['firstname'])) {
            $transformedProfile['firstname'] = $profile['firstname'];
        }

        if (isset($profile['personal']['name_last'])) {
            $transformedProfile['lastname'] = $profile['personal']['name_last'];
        } elseif (isset($profile['name_last'])) {
            $transformedProfile['lastname'] = $profile['name_last'];
        } elseif (isset($profile['last_name'])) {
            $transformedProfile['lastname'] = $profile['last_name'];
        } elseif (isset($profile['lastname'])) {
            $transformedProfile['lastname'] = $profile['lastname'];
        }

        // Try to extract email
        if (isset($profile['personal']['email'])) {
            $transformedProfile['email'] = $profile['personal']['email'];
        } elseif (isset($profile['email'])) {
            $transformedProfile['email'] = $profile['email'];
        }

        // Try to extract rating from various possible locations
        if (isset($profile['vatsim']['rating']['id'])) {
            $transformedProfile['rating_atc'] = $profile['vatsim']['rating']['id'];
        } elseif (isset($profile['rating']['id'])) {
            $transformedProfile['rating_atc'] = $profile['rating']['id'];
        } elseif (isset($profile['rating_atc'])) {
            $transformedProfile['rating_atc'] = $profile['rating_atc'];
        } elseif (isset($profile['rating'])) {
            $transformedProfile['rating_atc'] = is_array($profile['rating']) ? ($profile['rating']['id'] ?? 1) : $profile['rating'];
        }

        // Try to extract subdivision from various possible locations
        if (isset($profile['vatsim']['subdivision']['code'])) {
            $transformedProfile['subdivision_code'] = $profile['vatsim']['subdivision']['code'];
        } elseif (isset($profile['subdivision']['code'])) {
            $transformedProfile['subdivision_code'] = $profile['subdivision']['code'];
        } elseif (isset($profile['subdivision_code'])) {
            $transformedProfile['subdivision_code'] = $profile['subdivision_code'];
        } elseif (isset($profile['subdivision'])) {
            $transformedProfile['subdivision_code'] = is_array($profile['subdivision']) ? ($profile['subdivision']['code'] ?? null) : $profile['subdivision'];
        }

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

        // Also check for direct teams array
        if (isset($profile['teams']) && is_array($profile['teams'])) {
            $transformedProfile['teams'] = array_merge($transformedProfile['teams'], $profile['teams']);
        }

        // Validate that we have the minimum required data
        if (!$transformedProfile['id']) {
            throw new \Exception('Could not extract user ID from VATSIM Connect response. Raw response: ' . json_encode($rawProfile));
        }

        if (!$transformedProfile['firstname'] || !$transformedProfile['lastname']) {
            throw new \Exception('Could not extract user name from VATSIM Connect response. Raw response: ' . json_encode($rawProfile));
        }

        return $transformedProfile;
    }
}