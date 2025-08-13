<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OTPController extends Controller
{
    // should generate and store OTP, send request to FE, receive and validate OTP submission from FE

    public function generateOTP() { // generate and store OTP
        $randomId = rand(1000, 9999);
        return "Your One Time Password: {$randomId}";
    }

    

}
