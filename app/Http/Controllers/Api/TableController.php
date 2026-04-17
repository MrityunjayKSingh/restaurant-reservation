<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTableRequest;
use App\Http\Requests\UpdateTableRequest;
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

    /**
     * PUT /api/tables/{table}
     *
     * Update a table's details.
     */
    public function update(UpdateTableRequest $request, Table $table): TableResource
    {
        $table->update($request->validated());

        return new TableResource($table);
    }

    /**
     * DELETE /api/tables/{table}
     *
     * Permanently delete a table (soft delete).
     */
    public function destroy(Table $table): JsonResponse
    {
        $table->delete();

        return response()->json(
            ['message' => 'Table deleted successfully'],
            Response::HTTP_NO_CONTENT
        );
    }

    /**
     * PATCH /api/tables/{table}/inactivate
     *
     * Inactivate a table without deleting it.
     */
    public function inactivate(Table $table): TableResource
    {
        $table->update(['is_active' => false]);

        return new TableResource($table);
    }
}
