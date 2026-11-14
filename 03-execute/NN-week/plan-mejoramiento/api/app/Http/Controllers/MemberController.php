<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberController extends Controller
{
    public function store(StoreMemberRequest $request): JsonResponse
    {
        $member = ['id' => (string) Str::uuid()] + $request->validated();

        try {
            DB::table('members')->insert($member);
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->errorInfo[2] ?? '', '"members_document_number_unique"')) {
                throw $exception;
            }

            return response()->json([
                'code' => 'DOCUMENT_TAKEN',
                'message' => 'Ya existe un socio con ese número de documento.',
                'details' => [],
            ], 409);
        }

        return response()->json($member, 201);
    }
}