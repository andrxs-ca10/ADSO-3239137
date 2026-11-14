<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = DB::table('categories')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($categories);
    }
}