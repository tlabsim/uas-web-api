<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Http;

class EnsureOfficialWebClientOrLoggedInFromIMS
{
    public function handle($request, Closure $next)
    {
        // Array of allowed domain suffixes
        $allowedDomainSuffixes = [
            '.nstu.local',
            '.nstu.edu.bd',
            '.nstu.ac.bd',
        ];

        $apiSecretFromRequest = $request->header('X-Webclient-Secret');

        // Retrieve and normalize Origin and Referer
        $origin = strtolower($request->header('Origin', ''));
        $referer = strtolower($request->header('Referer', ''));

        $isOriginAllowed = $this->originMatchesAnySuffix($origin, $allowedDomainSuffixes);
        $isRefererAllowed = $this->originMatchesAnySuffix($referer, $allowedDomainSuffixes);

        // Check for secret key match
        $expectedSecret = env('OFFICIAL_WEBCLIENT_SECRET', '');
        $hasValidSecret = $apiSecretFromRequest === $expectedSecret;

        // Allow if coming from an official web client
        if (($isOriginAllowed || $isRefererAllowed) && $hasValidSecret) {
            return $next($request);
        }

        $strict = false;
        if(!strict) {
            $imsUser = $request->cookie('ims_user');
            if ($imsUser) {
                return $next($request);
            }
        }

        // Otherwise check if user is logged in via IMS
        $imsAccessToken = $request->cookie('ims_access_token');

        if ($imsAccessToken) {
            $authCheckUrl = env('IMS_AUTH_CHECK_URL', 'https://ims.nstu.edu.bd/auth/check');

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $imsAccessToken,
                ])->timeout(5)
                  ->get($authCheckUrl);

                if ($response->successful() && $response['authenticated']) {
                    return $next($request);
                }
            } catch (\Exception $e) {
                \Log::error('IMS auth check failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized. You must be logged in or coming from an official website.'
        ], 403);
    }

    protected function originMatchesAnySuffix($url, $allowedSuffixes)
    {
        if (empty($url)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            return false;
        }

        $host = strtolower($host);

        foreach ($allowedSuffixes as $suffix) {
            if (str_ends_with($host, strtolower($suffix))) {
                return true;
            }
        }

        return false;
    }
}