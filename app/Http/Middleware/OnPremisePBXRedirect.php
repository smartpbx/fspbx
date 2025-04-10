<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnPremisePBXRedirect
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if ($user && $user->user_adv_fields && $user->user_adv_fields->is_on_premise) {
            $onPremiseIp = $user->user_adv_fields->on_premise_pbx_ip;
            
            if ($onPremiseIp) {
                // Get the current path and query parameters
                $path = $request->path();
                $query = $request->query();
                
                // Construct the on-premise URL
                $onPremiseUrl = "https://{$onPremiseIp}/{$path}";
                if (!empty($query)) {
                    $onPremiseUrl .= '?' . http_build_query($query);
                }
                
                // Redirect to the on-premise PBX
                return redirect()->away($onPremiseUrl);
            }
        }
        
        return $next($request);
    }
} 