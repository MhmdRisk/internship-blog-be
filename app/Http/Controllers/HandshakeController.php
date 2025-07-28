<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HandshakeController extends Controller
{
    //
    public function handle(Request $request, Closure $next)
    {
        $signature = $request->header('X-Request-Signature');
        $nonce = $request->header('X-Request-Payload');
        $timestamp = $request->header('X-Request-Timestamp');

        if (!$signature || !$nonce || !$timestamp) {
            abort(403, "Missing signature, nonce, or timestamp");
        }

        // validate timestamp 
        if (abs(time() * 1000 - (int)$timestamp) > 30000) {
            abort(403, "Request expired");
        } 
        
        // check if nonce is valid and unused
        if (!Cache::pull("handshake_nonce:$nonce")) {
            abort(403, "Invalid or reused nonce");
        }

        $method = $request->method();
        
        if ($method == "GET") { 
            $payload = json_encode([
                "request" => $request->getPathInfo(), 
                "referer" => "front-end-website",
                "timestamp" => (int)$timestamp,
                "nonce" => $nonce,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        }
        else $payload = json_encode(array_merge($request->all(), [
            "timestamp" => (int)$timestamp,
            "nonce" => $nonce
        ]));
    
        $key = config("req_verification.secret_key");
        $expectedSignature = hash_hmac('sha256', $payload, $key);
        
        if (!hash_equals($expectedSignature, $signature)) {
            abort(403, "Unauthorized");
        }
        
        return $next($request);
    }
}
