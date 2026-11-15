<?php

namespace App\Http\Controllers\Api\Menu;

use App\Http\Controllers\Controller;
use App\Models\ProductPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductPrice::with(['product', 'variant', 'serviceType', 'location'])
            ->orderByDesc('created_at');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }
        if ($request->filled('price_type')) {
            $query->where('price_type', $request->get('price_type'));
        }

        return response()->json($query->limit(200)->get());
    }
}
