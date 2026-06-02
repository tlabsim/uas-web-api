<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Http;

class EnsureLoggedInFromIMS
{
    public function handle($request, Closure $next)
    {          
        $strict = false;
        if(!strict) {
            $imsUser = $request->cookie('ims_user');
            if ($imsUser) {
                return $next($request);
            }
        }

        $imsAccessToken = $request->cookie('ims_access_token');

        if ($imsAccessToken) {
            $auth_check_url = env('IMS_AUTH_CHECK_URL', 'https://ims.nstu.edu.bd/auth/check');
            $response = \Http::withHeaders([
                    'Authorization' => 'Bearer ' . $imsAccessToken,
                ])->get($auth_check_url);

            if ($response->successful()) {
                if ($response['authenticated']) {                    
                    return $next($request);
                }
            }    
        } 

        return redirect()->back()->with('error', 'You must be logged in to access this url.');
    }
}
