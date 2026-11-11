<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            return response()->json(['status' => 'ok', 'release' => config('release.commit')])
                ->header('Cache-Control', 'no-store');
        } catch (Throwable $e) {
            report($e);
            return response()->json(['status' => 'unavailable'], 503)->header('Cache-Control', 'no-store');
        }
    }
}
