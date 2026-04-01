<?php

namespace App\Core\FormBuilder\Http\Controllers;

use App\Core\FormBuilder\Services\FormBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FormBuilderController extends Controller
{
    public function __construct(protected FormBuilderService $fb) {}

    // GET /api/v1/form-builder/doctypes
    public function listDocTypes(Request $request): JsonResponse
    {
        $includeSystem = $request->boolean('include_system', true);
        return response()->json(['data' => $this->fb->listDocTypes($includeSystem)]);
    }

    // GET /api/v1/form-builder/doctypes/{name}
    public function getDocType(string $name): JsonResponse
    {
        return response()->json(['data' => $this->fb->getDocType($name)]);
    }

    // POST /api/v1/form-builder/doctypes
    public function createDocType(Request $request): JsonResponse
    {
        $request->validate([
            'name'           => 'required|string|max:120|regex:/^[A-Za-z][A-Za-z0-9 ]*$/',
            'module'         => 'sometimes|string|max:60',
            'label'          => 'sometimes|string|max:255',
            'is_submittable' => 'sometimes|boolean',
            'is_tree'        => 'sometimes|boolean',
        ]);

        $doctype = $this->fb->createDocType($request->all(), $request->user());

        return response()->json(['message' => "DocType [{$request->name}] created.", 'data' => $doctype], 201);
    }

    // PUT /api/v1/form-builder/doctypes/{name}
    public function updateDocType(Request $request, string $name): JsonResponse
    {
        $doctype = $this->fb->updateDocType($name, $request->all());
        return response()->json(['message' => 'DocType updated.', 'data' => $doctype]);
    }

    // DELETE /api/v1/form-builder/doctypes/{name}
    public function deleteDocType(string $name): JsonResponse
    {
        $this->fb->deleteDocType($name);
        return response()->json(['message' => "DocType [{$name}] deleted."]);
    }

    // GET /api/v1/form-builder/field-types
    public function fieldTypes(): JsonResponse
    {
        return response()->json(['data' => $this->fb->getFieldTypes()]);
    }

    // POST /api/v1/form-builder/doctypes/{name}/fields
    public function addField(Request $request, string $name): JsonResponse
    {
        $request->validate([
            'fieldtype' => 'required|string',
            'label'     => 'required|string|max:255',
            'fieldname' => 'sometimes|string|max:120',
        ]);

        $field = $this->fb->addField($name, $request->all());
        return response()->json(['message' => 'Field added.', 'data' => $field], 201);
    }

    // PUT /api/v1/form-builder/doctypes/{name}/fields/{fieldname}
    public function updateField(Request $request, string $name, string $fieldname): JsonResponse
    {
        $field = $this->fb->updateField($name, $fieldname, $request->all());
        return response()->json(['message' => 'Field updated.', 'data' => $field]);
    }

    // DELETE /api/v1/form-builder/doctypes/{name}/fields/{fieldname}
    public function deleteField(string $name, string $fieldname): JsonResponse
    {
        $this->fb->deleteField($name, $fieldname);
        return response()->json(['message' => 'Field deleted.']);
    }

    // POST /api/v1/form-builder/doctypes/{name}/fields/reorder
    public function reorderFields(Request $request, string $name): JsonResponse
    {
        $request->validate(['order' => 'required|array']);
        $this->fb->reorderFields($name, $request->order);
        return response()->json(['message' => 'Fields reordered.']);
    }
}
