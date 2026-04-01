<?php

use App\Core\FormBuilder\Http\Controllers\FormBuilderController;
use App\Core\Http\Controllers\Api\MCPController;
use App\Core\Http\Controllers\Api\ResourceController;
use App\Core\Workflow\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api', 'pascal.auth'])->group(function () {

    // Generic DocType resource API
    Route::apiResource('resource/{doctype}', ResourceController::class)
        ->parameters(['{doctype}' => 'name']);
    Route::post('resource/{doctype}/{name}/submit', [ResourceController::class, 'submit']);
    Route::post('resource/{doctype}/{name}/cancel', [ResourceController::class, 'cancel']);

    // Form Builder (admin only)
    Route::prefix('form-builder')->middleware('permission:FormBuilder.admin')->group(function () {
        Route::get('doctypes',                          [FormBuilderController::class, 'listDocTypes']);
        Route::post('doctypes',                         [FormBuilderController::class, 'createDocType']);
        Route::get('doctypes/{name}',                   [FormBuilderController::class, 'getDocType']);
        Route::put('doctypes/{name}',                   [FormBuilderController::class, 'updateDocType']);
        Route::delete('doctypes/{name}',                [FormBuilderController::class, 'deleteDocType']);
        Route::get('field-types',                       [FormBuilderController::class, 'fieldTypes']);
        Route::post('doctypes/{name}/fields',           [FormBuilderController::class, 'addField']);
        Route::put('doctypes/{name}/fields/{field}',    [FormBuilderController::class, 'updateField']);
        Route::delete('doctypes/{name}/fields/{field}', [FormBuilderController::class, 'deleteField']);
        Route::post('doctypes/{name}/fields/reorder',   [FormBuilderController::class, 'reorderFields']);
    });

    // Workflow
    Route::prefix('workflows')->group(function () {
        Route::middleware('permission:Workflow.admin')->group(function () {
            Route::get('/',        [WorkflowController::class, 'index']);
            Route::post('/',       [WorkflowController::class, 'store']);
            Route::get('/{id}',    [WorkflowController::class, 'show']);
            Route::delete('/{id}', [WorkflowController::class, 'destroy']);
        });
        Route::get('doctype/{doctype}',          [WorkflowController::class, 'forDocType']);
        Route::get('transitions/{doctype}/{name}',[WorkflowController::class, 'availableTransitions']);
        Route::post('apply/{doctype}/{name}',     [WorkflowController::class, 'applyTransition']);
        Route::get('history/{doctype}/{name}',    [WorkflowController::class, 'history']);
    });

    // MCP
    Route::prefix('mcp')->group(function () {
        Route::post('tools',   [MCPController::class, 'listTools']);
        Route::post('execute', [MCPController::class, 'execute']);
    });
});
