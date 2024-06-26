<?php

// ekpay configuration

$apiDomain = env('EKPAY_TESTMODE') ? "https://sandbox.ekpay.gov.bd/ekpaypg/v1" : "https://pg.ekpay.gov.bd/ekpaypg/v1";
$serverIp = "1.1.1.1";
return [
	'apiCredentials' => [
		'user_id' => env("EKPAY_USERID"),
		'store_password' => env("EKPAY_PASSWORD"),
	],
	'apiDomain' => $apiDomain,
	'success_url' => env('APP_URL').'/success',
	'failed_url' => env('APP_URL').'/fail',
	'cancel_url' => env('APP_URL').'/cancel',
	'ipn_url' => env('APP_URL').'/ipn',
	'ipn_email' => 'wevic@mailinator.com',
    'server_ip' => $serverIp,
];
