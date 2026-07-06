<?php
namespace Sample;
use PaypalPayoutsSDK\Core\PayPalHttpClient;
use PaypalPayoutsSDK\Core\SandboxEnvironment;
ini_set('error_reporting', E_ALL); // or error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
class PayPalClient
{
    /**
     * Returns PayPal HTTP client instance with environment which has access
     * credentials context. This can be used invoke PayPal API's provided the
     * credentials have the access to do so.
     */
    public static function client()
    {
        return new PayPalHttpClient(self::environment());
    }
    
    /**
     * Setting up and Returns PayPal SDK environment with PayPal Access credentials.
     * For demo purpose, we are using SandboxEnvironment. In production this will be
     * ProductionEnvironment.
     */
    public static function environment()
    {
        $clientId = getenv("CLIENT_ID") ?: "AU5FOvNaZefwO6dSFEmfsvePgqh0mjBmAybryG8HFFF4OmQQabfVwy09f2TrrnACCpHIty2sVowZT0Wc";
        $clientSecret = getenv("CLIENT_SECRET") ?: "EIX1I639OQU2ic-B7tVjiN6Vn1bECzEAuNyyI34qC8YgHzq94pCdQB4BkEABBHckc04pbdKUZX8xjRRq";
        return new SandboxEnvironment($clientId, $clientSecret);
    }
}