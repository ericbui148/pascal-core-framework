<?php

namespace App\Core\Http\Controllers\Api;

use App\Core\DocType\DocTypeRegistry;
use App\Core\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * ResourceController — One controller serves every registered DocType.
 *
 * Routes (all under /api/v1/resource/{doctype}):
 *   GET    /           → list
 *   POST   /           → create
 *   GET    /{name}     → show
 *   PUT    /{name}     → update
 *   DELETE /{name}     → delete
 *   POST   /{name}/submit
 *   POST   /{name}/cancel
 */
class ResourceController extends Controller
{
    public function __construct(protected DocumentService $service) {}

    public function index(Request $request, string $doctype): JsonResponse
    {
        $this->guardDoctype($doctype);

        $filters = $request->only(['docstatus', 'owner']);
        $result  = $this->service->list(
            $doctype, $filters,
            (int) $request->query('limit', 20),
            (int) $request->query('offset', 0),
        );

        return response()->json($result);
    }

    public function show(Request $request, string $doctype, string $name): JsonResponse
    {
        $this->guardDoctype($doctype);

        return response()->json(['data' => $this->service->get($doctype, $name)]);
    }

    public function store(Request $request, string $doctype): JsonResponse
    {
        $this->guardDoctype($doctype);

        $data = $this->service->create($doctype, $request->all(), $request->user());

        return response()->json(['data' => $data], 201);
    }

    public function update(Request $request, string $doctype, string $name): JsonResponse
    {
        $this->guardDoctype($doctype);

        $data = $this->service->update($doctype, $name, $request->all(), $request->user());

        return response()->json(['data' => $data]);
    }

    public function destroy(Request $request, string $doctype, string $name): JsonResponse
    {
        $this->guardDoctype($doctype);

        $this->service->delete($doctype, $name, $request->user());

        return response()->json(['message' => 'Deleted.']);
    }

    public function submit(Request $request, string $doctype, string $name): JsonResponse
    {
        $this->guardDoctype($doctype);

        $data = $this->service->submit($doctype, $name, $request->user());

        return response()->json(['data' => $data]);
    }

    public function cancel(Request $request, string $doctype, string $name): JsonResponse
    {
        $this->guardDoctype($doctype);

        $data = $this->service->cancel($doctype, $name, $request->user());

        return response()->json(['data' => $data]);
    }

    private function guardDoctype(string $doctype): void
    {
        if (!DocTypeRegistry::exists($doctype)) {
            abort(404, "DocType [{$doctype}] is not registered.");
        }
    }
}
