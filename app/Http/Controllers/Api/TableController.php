<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTableRequest;
use App\Http\Resources\TableResource;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TableController extends Controller
{
    /**
     * POST /api/tables
     *
     * Create a new restaurant table.
     */
    public function store(CreateTableRequest $request): JsonResponse
    {
        $table = Table::create($request->validated());

        return (new TableResource($table))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * GET /api/tables
     *
     * List all active tables, optionally filtered by location.
     */
    public function index(): AnonymousResourceCollection
    {
        $tables = Table::active()
            ->when(request('location'), fn ($q, $loc) => $q->byLocation($loc))
            ->orderBy('table_number')
            ->get();

        return TableResource::collection($tables);
    }

    /**
     * GET /api/tables/{table}
     */
    public function show(Table $table): TableResource
    {
        return new TableResource($table);
    }
}
