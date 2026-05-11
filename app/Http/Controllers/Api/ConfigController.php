<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class ConfigController extends Controller
{
    public function getConfig()
    {
        $config = [
            'product_units' => config('app.product_units'),
            'purchase_units' => config('app.purchase_units'),
            'payment_methods' => config('app.payment_methods'),
            'shop_info' => config('app.shop_info')        
        ];

        return response()->json($config);
    }
}