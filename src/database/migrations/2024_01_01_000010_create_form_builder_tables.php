<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Form Builder — DocType & Field metadata tables
|--------------------------------------------------------------------------
| These tables ARE the Form Builder. Every DocType created through the UI
| lives here. The platform reads these at runtime to know what fields exist,
| how to validate, and what table to query.
|
| System DocTypes (User, etc.) are code-registered but also mirrored here
| so the Form Builder can display and extend them with custom fields.
*/

return new class extends Migration
{
    public function up(): void
    {
        // ── DocTypes ──────────────────────────────────────────────────────────
        Schema::create('pascal_doctypes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();          // "Customer", "SalesOrder"
            $table->string('module', 60)->default('Custom');
            $table->string('label', 255)->nullable();       // human-readable
            $table->text('description')->nullable();
            $table->string('icon', 60)->nullable();         // heroicon name
            $table->boolean('is_submittable')->default(false);
            $table->boolean('is_tree')->default(false);
            $table->boolean('track_changes')->default(true);
            $table->boolean('is_system')->default(false);   // code-registered, protected
            $table->boolean('is_custom')->default(true);    // created via Form Builder
            $table->string('title_field', 120)->nullable(); // which field to use as record title
            $table->string('search_fields', 500)->nullable();// comma-separated
            $table->json('settings')->nullable();
            $table->string('owner', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Fields ────────────────────────────────────────────────────────────
        Schema::create('pascal_docfields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctype_id')->constrained('pascal_doctypes')->cascadeOnDelete();
            $table->string('fieldname', 120);
            $table->string('fieldtype', 60);    // Data|Int|Float|Currency|Date|Datetime|
                                                 // Select|Link|Table|Attach|Check|
                                                 // Text Editor|Section Break|Column Break|HTML
            $table->string('label', 255);
            $table->boolean('required')->default(false);
            $table->boolean('in_list_view')->default(false);    // show in table columns
            $table->boolean('in_standard_filter')->default(false);
            $table->boolean('read_only')->default(false);
            $table->boolean('hidden')->default(false);
            $table->boolean('bold')->default(false);
            $table->tinyInteger('columns')->default(1);         // grid: 1=full, 2=half
            $table->integer('sort_order')->default(0);          // drag & drop order
            $table->integer('permlevel')->default(0);           // 0-9 permission level
            $table->string('options', 2000)->nullable();        // Select: "A\nB\nC" | Link: "Customer"
            $table->string('depends_on', 500)->nullable();      // "eval:doc.status=='Open'"
            $table->string('default_value', 500)->nullable();
            $table->string('placeholder', 255)->nullable();
            $table->text('description')->nullable();            // help text under field
            $table->string('precision', 10)->nullable();        // for Float/Currency
            $table->json('validation_rules')->nullable();       // extra validation
            $table->timestamps();

            $table->unique(['doctype_id', 'fieldname']);
            $table->index(['doctype_id', 'sort_order']);
        });

        // ── Custom table storage (for DocTypes created via Form Builder) ──────
        // Each custom DocType gets a row in pascal_custom_data.
        // This avoids needing dynamic DDL for every new DocType.
        // (Advanced: can migrate to real tables later for performance)
        Schema::create('pascal_custom_data', function (Blueprint $table) {
            $table->id();
            $table->string('doctype', 120);
            $table->string('name', 255);              // record identifier
            $table->tinyInteger('docstatus')->default(0);
            $table->string('workflow_state', 120)->nullable();
            $table->json('data');                     // all field values as JSON
            $table->string('owner', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['doctype', 'name']);
            $table->index(['doctype', 'docstatus']);
            $table->index(['doctype', 'workflow_state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pascal_custom_data');
        Schema::dropIfExists('pascal_docfields');
        Schema::dropIfExists('pascal_doctypes');
    }
};
