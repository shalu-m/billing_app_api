<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function getConfig(Request $request)
    {
        $config = [
            'product_units' => config('app.product_units'),
            'purchase_units' => config('app.purchase_units'),
            'payment_methods' => config('app.payment_methods'),
            'shop_info' => config('app.shop_info'),
            'enabled_shops' => config('app.enabled_shops'),
            'navigation' => $request->user()?->navigation(),
        ];

        return response()->json($config);
    }
}
