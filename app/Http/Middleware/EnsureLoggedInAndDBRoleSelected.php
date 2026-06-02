<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Http;

class EnsureLoggedInAndDBRoleSelected
{
    public function handle($request, Closure $next, $exprected_role_name = null)
    {              
        $imsAccessToken = $request->cookie('ims_access_token');
        //Fallback to bearer token
        if (!$imsAccessToken) {
            $imsAccessToken = $request->bearerToken();
        }
        // Fallback to request input
        if (!$imsAccessToken) {
            $imsAccessToken = $request->input('ims_access_token');
        }

        // \Log::info('EnsureLoggedInAndDBRoleSelected middleware triggered', [
        //     'expected_role' => $exprected_role_name,
        //     'ims_access_token' => $imsAccessToken,
        //     'request_url' => $request->fullUrl(),
        //     'request_method' => $request->method(),
        // ]);
        
        
        $roleMap = [
            'web_curator' => 'Web Curator',
        ];
        $exprected_role_name = $roleMap[$exprected_role_name] ?? $exprected_role_name;

        if ($imsAccessToken) {
            $auth_check_url = env('IMS_AUTH_CHECK_URL', 'https://ims.nstu.edu.bd/auth/check');
            $response = \Http::withHeaders([
                    'Authorization' => 'Bearer ' . $imsAccessToken,
                ])->get($auth_check_url);

            if ($response->successful()) {
                if ($response['authenticated']) {
                    $user = $response['user'];
                    if($exprected_role_name) {
                        $current_role_id = $user['current_db_role_id'] ?? null;
                        if($current_role_id) {
                            $user_roles = collect($user['db_roles'] ?? []);
                            $current_role = $user_roles->firstWhere('assignment_id', $current_role_id);
                            if($current_role) {
                                $current_role_name = $current_role['name'] ?? '';
                                if (strtolower($current_role_name) !== strtolower($exprected_role_name)) {
                                    return redirect()->back()->with('error', 'You do not have the required role to access this url.');
                                }
                                else {
                                    $request->attributes->set('current_role_scope', $current_role['scope_entity_id'] ?? null);
                                    return $next($request);
                                
                                }
                            } else {
                                return redirect()->back()->with('error', 'Current role not found.');
                            }
                        }
                    }
                    return $next($request);
                }
            }    
        } 

        \Log::warning('User is not authenticated or access token is missing', [
            'ims_access_token' => $imsAccessToken,
        ]);

        return redirect()->back()->with('error', 'You must be logged in to access this url.');
    }
}
