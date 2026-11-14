<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberController extends Controller
{
    public function store(StoreMemberRequest $request): JsonResponse
    {
        $member = ['id' => (string) Str::uuid()] + $request->validated();

        try {
            DB::transaction(function () use ($member): void {
                DB::table('members')->insert($member);
            });
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

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'size' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = DB::table('members');
        $search = trim($filters['q'] ?? '');

        if ($search !== '') {
            $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

            $query->where(function ($query) use ($pattern): void {
                $query->whereRaw("full_name ILIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("document_number ILIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        $members = $query->orderBy('full_name')
            ->orderBy('id')
            ->paginate(
                (int) ($filters['size'] ?? 20),
                ['id', 'full_name', 'document_number'],
                'page',
                (int) ($filters['page'] ?? 1),
            );

        return response()->json([
            'items' => $members->items(),
            'page' => $members->currentPage(),
            'size' => $members->perPage(),
            'total' => $members->total(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $member = Str::isUuid($id)
            ? DB::table('members')
                ->where('id', $id)
                ->first(['id', 'full_name', 'document_number'])
            : null;

        if ($member === null) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'El socio no existe.',
                'details' => [],
            ], 404);
        }

        $activeLoans = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.member_id', $member->id)
            ->whereNull('loan_items.returned_at')
            ->count();

        return response()->json([
            'id' => $member->id,
            'full_name' => $member->full_name,
            'document_number' => $member->document_number,
            'active_loans' => $activeLoans,
        ]);
    }
}