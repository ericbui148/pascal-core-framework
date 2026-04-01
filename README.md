# Pascal Platform

> Laravel 11 · Filament v3 · API-first · Event-driven · DocType Engine · Form Builder · Workflow Engine · Audit Trail

---

## Table of Contents

1. [Quick Start](#1-quick-start)
2. [Architecture Overview](#2-architecture-overview)
3. [Core Platform](#3-core-platform)
4. [User Module](#4-user-module)
5. [Form Builder](#5-form-builder)
6. [Workflow Engine](#6-workflow-engine)
7. [Admin UI — Filament v3](#7-admin-ui--filament-v3)
8. [REST API Reference](#8-rest-api-reference)
9. [Adding a New Module](#9-adding-a-new-module)
10. [Project Structure](#10-project-structure)
11. [Database Schema](#11-database-schema)

---

## 1. Quick Start

**Requirements:** PHP 8.3+, Composer 2+, Docker Desktop

```bash
# 1. Create fresh Laravel 11 project
composer create-project laravel/laravel pascal-platform
cd pascal-platform

# 2. Install packages
composer require filament/filament:"^3.0"
php artisan filament:install --panels --no-interaction

# 3. Copy platform source files
#    (unzip pascal-platform-full.zip, then run from inside the zip folder:)
rsync -a src/app/           app/
rsync -a src/database/      database/
rsync -a src/resources/     resources/
rsync -a src/docker/        docker/
cp      src/routes/api.php  routes/api.php
cp      src/config/auth.php config/auth.php
cp      src/Dockerfile      .
cp      src/docker-compose.yml .

# 4. Register providers
cat > bootstrap/providers.php << 'PHP'
<?php
return [
    App\Providers\AppServiceProvider::class,
    App\Core\Providers\CoreServiceProvider::class,
    App\Modules\User\Providers\UserServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
];
PHP

# 5. Configure .env for Docker
cp .env.example .env
sed -i 's/DB_HOST=127.0.0.1/DB_HOST=db/'          .env
sed -i 's/DB_DATABASE=laravel/DB_DATABASE=pascal/' .env
sed -i 's/DB_USERNAME=root/DB_USERNAME=pascal/'    .env
sed -i 's/DB_PASSWORD=/DB_PASSWORD=secret/'        .env
sed -i 's/REDIS_HOST=127.0.0.1/REDIS_HOST=redis/' .env
printf "\nMAIL_HOST=mailpit\nMAIL_PORT=1025\n"    >> .env
php artisan key:generate

# 6. Start Docker + migrate + seed
docker compose up -d --build
# Wait ~30s for MySQL, then:
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
docker compose exec app php artisan optimize:clear
```

| Service | URL |
|---------|-----|
| **Admin UI** | http://localhost:8000/admin |
| **REST API** | http://localhost:8000/api/v1 |
| **Mail (dev)** | http://localhost:8025 |

**Default admin:** `admin@pascal.com` / `Admin@123456`
> ⚠ Change this password immediately after first login.

---

## 2. Architecture Overview

Pascal Platform follows the same philosophy as Frappe — rebuilt on Laravel:

| Principle | What it means |
|-----------|---------------|
| **API-first** | All business logic flows through the API. UI is just a client. |
| **Event-driven** | All side effects go through the Event Bus. Modules never call each other directly. |
| **Metadata-driven** | DocTypes are defined in the database. New entities need no code deployment. |
| **MCP-ready** | AI agents can interact with every DocType via MCP endpoints from day one. |
| **Audit everything** | Every data change is recorded: who, when, what, from which IP. |

### Layer diagram

```
┌─────────────────────────────────────────────────────────────┐
│  L7  Admin UI  (Filament v3)                                │
│      Dashboard · Form Builder · Workflow Manager · Users    │
├─────────────────────────────────────────────────────────────┤
│  L6  API Gateway                                            │
│      REST /api/v1 · MCP /api/v1/mcp                        │
│      pascal.auth middleware · permission middleware         │
├─────────────────────────────────────────────────────────────┤
│  L5  Core Platform  (the engine, no business logic)         │
│      DocTypeRegistry    DocumentService    AuditService      │
│      FormBuilderService WorkflowService    PermissionService │
│      Event Bus (DocumentCreated / Updated / Submitted / …)  │
├─────────────────────────────────────────────────────────────┤
│  L4  Business Modules  (pluggable, isolated)                │
│      User  ···  (future: CRM, HR, Accounting, Inventory)   │
├─────────────────────────────────────────────────────────────┤
│  L3  Queue & Async  (Redis + Laravel Queue)                 │
├─────────────────────────────────────────────────────────────┤
│  L2  Multi-tenant hook  (InitializeTenant middleware)        │
├─────────────────────────────────────────────────────────────┤
│  L1  Data  MySQL 8 · Redis 7                                │
└─────────────────────────────────────────────────────────────┘
```

### Every request flows through the same pipeline

```
HTTP POST /api/v1/resource/Customer
  │
  ├─ pascal.auth middleware        resolve user from Bearer token
  ├─ permission middleware          check role-based access
  │
  └─ ResourceController::store()   delegates to DocumentService (no logic here)
        │
        └─ DocumentService::create('Customer', $data, $user)
              ├─ PermissionService::check('Customer', 'create', $user)
              ├─ DocTypeRegistry::controller('Customer')
              │     └─ CustomerDocumentController::validate($data)
              │     └─ CustomerDocumentController::beforeSave($data)
              ├─ DB::table('customers')->insert($data)
              ├─ CustomerDocumentController::afterSave($data, 'create')
              │     └─ Event::dispatch(new CustomerCreated($data))
              ├─ AuditService::log('Customer', $name, 'create', ...)
              └─ Event::dispatch(new DocumentCreated('Customer', $data, $user))
```

### Why not one Eloquent model per DocType?

In ERPNext, you create a "Customer" DocType in the UI with 40 fields and immediately
get a full form, list view, REST API, and audit trail — with zero code.

Pascal Platform replicates this with a single generic `DocumentService` that handles
CRUD for every DocType. A module like "User" is just a DocType with a custom
`DocumentController` that overrides the hooks it needs.

---

## 3. Core Platform

### DocTypeRegistry

In-memory registry populated at boot time by module ServiceProviders.

```php
// app/Modules/CRM/Providers/CRMServiceProvider.php
DocTypeRegistry::register('Customer', CustomerDocumentController::class, [
    'module'         => 'CRM',
    'is_submittable' => true,
    'track_changes'  => true,
]);
```

After this one call, `Customer` automatically gets:
- `GET/POST /api/v1/resource/Customer`
- `GET/PUT/DELETE /api/v1/resource/Customer/{name}`
- `POST /api/v1/resource/Customer/{name}/submit`
- Full audit trail
- MCP tools: `list_customer`, `create_customer`, `submit_customer` ...
- Workflow support (attach a workflow in the UI, no code needed)

### DocumentController hooks

```php
interface DocumentController {
    // Throw ValidationException to block save
    public function validate(array &$data): void;

    // Transform data, set computed fields, hash passwords
    public function beforeSave(array &$data, ?array $existing): void;

    // Dispatch domain events, update denormalised fields
    public function afterSave(array $data, string $action): void;

    // Business logic on Submit (docstatus 0→1) — dispatch events only
    public function onSubmit(array $data): void;

    // Business logic on Cancel (docstatus 1→2)
    public function onCancel(array $data): void;

    // Throw to prevent deletion (e.g. last admin check)
    public function beforeDelete(array $data): void;
}
```

All hooks have default no-op implementations in `BaseDocumentController`.
A module overrides only what it needs.

### AuditService

Automatically called by `DocumentService` on every operation.
No opt-in needed.

```
pascal_audit_logs
  doctype     "Customer"
  docname     "CUST-20250401-0001"
  action      create | update | delete | submit | cancel
  user_email  "alice@company.com"
  ip_address  "192.168.1.100"
  before_data JSON snapshot before
  after_data  JSON snapshot after
  diff        JSON of only the changed fields
  created_at  immutable timestamp
```

### PermissionService

Role hierarchy: `user (1) < manager (2) < admin (3)`

| Action | Min role |
|--------|----------|
| read, create, write | user |
| delete, submit, cancel | manager |
| admin actions | admin |

Route middleware syntax:
```php
->middleware('permission:User.admin')        // User doctype, admin action
->middleware('permission:FormBuilder.admin') // Form Builder access
->middleware('permission:Workflow.admin')    // Workflow management
```

---

## 4. User Module

The first real DocType — a full working example of the module pattern.

### Authentication flow

```
POST /api/v1/auth/login
  └─ UserAuthService::login()
        ├─ DB lookup pascal_users by email
        ├─ Hash::check(password, stored_hash)
        ├─ Check status (Active / Banned / Inactive)
        ├─ INSERT into personal_access_tokens (id|plaintext format)
        ├─ UPDATE last_login_at + last_login_ip
        ├─ INSERT into pascal_login_histories
        └─ Event::dispatch(new UserLoggedIn(...))
```

### Token format

`{token_id}|{40-char-random}` — stored as `sha256(plaintext)` in the DB.
The `AuthenticatePascalUser` middleware resolves on every authenticated request.
No Sanctum model dependency — pure DB queries.

### Endpoints

```
Public:  POST /api/v1/auth/register
         POST /api/v1/auth/login
         POST /api/v1/auth/forgot-password
         POST /api/v1/auth/reset-password

Auth:    GET  /api/v1/auth/me
         POST /api/v1/auth/logout
         POST /api/v1/auth/logout-all
         POST /api/v1/auth/change-password
         GET  /api/v1/user/profile
         PUT  /api/v1/user/profile
         POST /api/v1/user/avatar
         DELETE /api/v1/user/avatar
         GET  /api/v1/user/login-history
         GET  /api/v1/user/sessions
         DELETE /api/v1/user/sessions/{id}

Admin:   GET/POST /api/v1/admin/users
         GET/PUT/DELETE /api/v1/admin/users/{name}
         POST /api/v1/admin/users/{name}/ban
         POST /api/v1/admin/users/{name}/unban
         GET  /api/v1/admin/users/{name}/audit-trail
```

---

## 5. Form Builder

Create and modify DocTypes at runtime. No migration commands, no deployments.

### How it works end-to-end

```
Admin clicks "New DocType" in UI
  └─ FormBuilderService::createDocType()
        ├─ Validate name (letters/spaces only, unique)
        ├─ INSERT into pascal_doctypes
        └─ DocTypeRegistry::register()        ← available immediately in same request

On next boot (or immediately via registry sync):
  CoreServiceProvider::boot()
    └─ FormBuilderService::bootAllFromDatabase()
          └─ reads pascal_doctypes, registers each into DocTypeRegistry

From this point:
  GET  /api/v1/resource/{new-doctype}         ← works
  POST /api/v1/resource/{new-doctype}         ← works
  POST /api/v1/mcp/tools                      ← includes new doctype tools
  Admin Form Builder page                     ← shows the doctype
```

### Field storage

Records for Form Builder DocTypes go into `pascal_custom_data` as JSON.
No DDL per DocType — one table handles all custom DocTypes.

```
pascal_custom_data
  doctype        "Leave Application"
  name           "LEAVE-20250401-ABCD"
  docstatus      0 | 1 | 2
  workflow_state "Pending Approval"
  data           {"employee":"alice","from_date":"2025-04-01",...}
```

For high-volume DocTypes, create a real table and register a custom
`DocumentController` that queries it directly — the rest of the platform
(audit, workflow, MCP) still works unchanged.

### Supported field types

| Category | Types |
|----------|-------|
| Basic | Data, Text Editor, Int, Float, Currency, Percent, Check |
| Date/Time | Date, Datetime, Time |
| Choice | Select (options list), Link (FK to another DocType) |
| File | Attach |
| Layout | Section Break, Column Break, HTML |
| Child | Table (related child records) |

### API quick reference

```bash
TOKEN="1|your-admin-token"

# Create a DocType
curl -X POST http://localhost:8000/api/v1/form-builder/doctypes \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"Leave Application","module":"HR","is_submittable":true}'

# Add fields
curl -X POST "http://localhost:8000/api/v1/form-builder/doctypes/Leave%20Application/fields" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"label":"Employee","fieldtype":"Link","options":"User","required":true}'

curl -X POST "http://localhost:8000/api/v1/form-builder/doctypes/Leave%20Application/fields" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"label":"From Date","fieldtype":"Date","required":true}'

# Reorder fields (result of drag & drop)
curl -X POST "http://localhost:8000/api/v1/form-builder/doctypes/Leave%20Application/fields/reorder" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"order":{"employee":10,"from_date":20,"to_date":30}}'

# Now create a record using the generic API
curl -X POST "http://localhost:8000/api/v1/resource/Leave%20Application" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"employee":"alice","from_date":"2025-04-01","to_date":"2025-04-03"}'
```

---

## 6. Workflow Engine

A state machine that attaches to any DocType. Defined entirely through the UI.

### Concepts

| Term | Description |
|------|-------------|
| Workflow | A named state machine attached to one DocType |
| State | A named status (`Draft`, `Pending Approval`, `Approved`) |
| Transition | A rule moving from one state to another |
| Action | The button label shown to the user (`Approve`, `Reject`) |
| doc_status | Each state maps to 0=Draft, 1=Submitted, or 2=Cancelled |

### Example: Leave Approval

```
States:
  Draft (gray, docstatus=0, initial)
    ──[Submit]──→  Pending Approval (yellow, docstatus=0)
                     ──[Approve]──→  Approved (green, docstatus=1)  + email sent
                     ──[Reject]───→  Rejected (red,   docstatus=2)  + comment required

Role rules:
  Submit  → allowed for: [user]
  Approve → allowed for: [manager, admin]
  Reject  → allowed for: [manager, admin]
```

### Transition execution

```
POST /api/v1/workflows/apply/Leave Application/LEAVE-20250401-ABCD
  { "transition_id": 2, "comment": "Approved" }

WorkflowService::applyTransition()
  ├─ Guard: document is in the expected from_state
  ├─ Guard: user role is in allowed_roles
  ├─ Guard: comment provided if requires_comment=true
  ├─ UPDATE document.workflow_state = "Approved"
  ├─ UPDATE document.docstatus = 1   (from state's doc_status mapping)
  ├─ INSERT into pascal_workflow_logs
  └─ Mail::raw(...)  if send_email=true
```

### API quick reference

```bash
# Create a workflow
curl -X POST http://localhost:8000/api/v1/workflows \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{
    "name": "Leave Approval",
    "doctype": "Leave Application",
    "states": [
      {"state":"Draft",            "color":"gray",   "doc_status":"0","is_initial":true},
      {"state":"Pending Approval", "color":"yellow", "doc_status":"0"},
      {"state":"Approved",         "color":"green",  "doc_status":"1"},
      {"state":"Rejected",         "color":"red",    "doc_status":"2"}
    ],
    "transitions": [
      {"from_state":"Draft",            "to_state":"Pending Approval","action":"Submit",  "allowed_roles":["user"],           "action_color":"primary"},
      {"from_state":"Pending Approval", "to_state":"Approved",        "action":"Approve", "allowed_roles":["manager","admin"], "action_color":"success","send_email":true},
      {"from_state":"Pending Approval", "to_state":"Rejected",        "action":"Reject",  "allowed_roles":["manager","admin"], "action_color":"danger", "requires_comment":true}
    ]
  }'

# Get action buttons available RIGHT NOW for this user + document
curl "http://localhost:8000/api/v1/workflows/transitions/Leave%20Application/LEAVE-20250401-ABCD" \
  -H "Authorization: Bearer $TOKEN"

# Apply a transition
curl -X POST "http://localhost:8000/api/v1/workflows/apply/Leave%20Application/LEAVE-20250401-ABCD" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"transition_id": 2, "comment": "Looks good, approved."}'

# View transition history
curl "http://localhost:8000/api/v1/workflows/history/Leave%20Application/LEAVE-20250401-ABCD" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 7. Admin UI — Filament v3

Access: **http://localhost:8000/admin**
Only users with role `admin` or `manager` can log in (enforced by `PascalUser::canAccessPanel()`).

### Navigation

| Page | URL | Description |
|------|-----|-------------|
| Dashboard | `/admin` | Stats cards + DocType registry table |
| Form Builder | `/admin/form-builder` | Live DocType & field editor |
| Workflow Manager | `/admin/workflow-manager` | Create workflows, view state diagrams |
| Users | `/admin/users` | Full user management |
| Audit Trail | `/admin/audit-trail` | Browse every change ever made |

### Dashboard

- Stats: total users, active, admins, logins today, audit events today
- DocType Registry widget: every registered DocType with table name, record count,
  submittable flag, audit flag — live from `DocTypeRegistry::allSchemas()`

### Form Builder

- **Left sidebar** — list of all DocTypes (system + custom), click to select
- **Right panel** — field table with columns: Label, Fieldname, Type, Required (toggle), In List View (toggle), Options, Order (↑↓ buttons), Delete
- **Add Field row** at the bottom — Label, Type dropdown, Options input, Required checkbox, Add button
- **New DocType** button in page header — opens modal form
- **Delete DocType** button (hidden for system DocTypes, requires zero records)

### Workflow Manager

- **Left sidebar** — all workflow definitions
- **Right panel** — visual state diagram with colored state badges, arrow between states
- Transitions table: From State, Action button (colored), To State, Allowed Roles badges,
  Email icon, Comment icon
- **New Workflow** button — opens modal with Repeater for states and transitions
- Inline API usage hint showing exact cURL commands for the selected workflow

### Users

- Avatar, full name, email, role badge, status badge, last login
- Search by name/email, filter by role/status, include soft-deleted
- **Create** — full form: full name, username, email, phone, role, status, password, avatar upload
- **Edit** — same form; password field is optional (leave blank to keep existing)
- **Ban** — confirmation modal, revokes all API tokens immediately
- **Unban** — restores Active status
- **Soft delete** + **Restore**
- **Bulk actions** — activate, deactivate, delete multiple

### Audit Trail

- Filter by DocType and/or record name
- Columns: timestamp, DocType, record name, action badge (colour-coded), user email, IP, changed fields

---

## 8. REST API Reference

### Auth (no token required)

| Method | Endpoint | Body |
|--------|----------|------|
| POST | `/api/v1/auth/register` | `full_name, email, password, password_confirmation` |
| POST | `/api/v1/auth/login` | `email, password, [remember_me]` |
| POST | `/api/v1/auth/forgot-password` | `email` |
| POST | `/api/v1/auth/reset-password` | `token, email, password, password_confirmation` |

Login response:
```json
{ "token": "1|abc...", "expires_at": "2025-04-09T08:00:00Z", "user": { "name": "admin", "role": "admin", ... } }
```

### Auth (Bearer token required)

```bash
# Header for all authenticated requests:
-H "Authorization: Bearer 1|your-token-here"
```

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/auth/me` | Own profile + role |
| POST | `/api/v1/auth/logout` | Revoke current token |
| POST | `/api/v1/auth/logout-all` | Revoke all tokens |
| POST | `/api/v1/auth/change-password` | `current_password, password, password_confirmation` |
| GET | `/api/v1/user/profile` | Own profile |
| PUT | `/api/v1/user/profile` | `full_name, email, phone` |
| POST | `/api/v1/user/avatar` | `multipart/form-data, avatar=<file>` |
| DELETE | `/api/v1/user/avatar` | Remove avatar |
| GET | `/api/v1/user/login-history` | Paginated |
| GET | `/api/v1/user/sessions` | Active tokens |
| DELETE | `/api/v1/user/sessions/{id}` | Revoke one session |

### Admin (role: admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/users` | `?search=&status=&role=&limit=&offset=` |
| POST | `/api/v1/admin/users` | Create user |
| GET | `/api/v1/admin/users/{name}` | Get user |
| PUT | `/api/v1/admin/users/{name}` | Update user |
| DELETE | `/api/v1/admin/users/{name}` | Soft delete |
| POST | `/api/v1/admin/users/{name}/ban` | Ban + revoke tokens |
| POST | `/api/v1/admin/users/{name}/unban` | Restore to Active |
| GET | `/api/v1/admin/users/{name}/audit-trail` | 50 most recent changes |

### Generic DocType API

Works for every registered DocType — both code-registered and Form Builder DocTypes.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/resource/{doctype}` | `?limit=&offset=&docstatus=` |
| POST | `/api/v1/resource/{doctype}` | Create record |
| GET | `/api/v1/resource/{doctype}/{name}` | Get record |
| PUT | `/api/v1/resource/{doctype}/{name}` | Update record |
| DELETE | `/api/v1/resource/{doctype}/{name}` | Delete record |
| POST | `/api/v1/resource/{doctype}/{name}/submit` | docstatus 0→1 |
| POST | `/api/v1/resource/{doctype}/{name}/cancel` | docstatus 1→2 |

### Form Builder (role: admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/form-builder/doctypes` | All DocTypes |
| POST | `/api/v1/form-builder/doctypes` | Create DocType |
| GET | `/api/v1/form-builder/doctypes/{name}` | DocType + fields |
| PUT | `/api/v1/form-builder/doctypes/{name}` | Update DocType |
| DELETE | `/api/v1/form-builder/doctypes/{name}` | Delete DocType |
| GET | `/api/v1/form-builder/field-types` | Available field types |
| POST | `/api/v1/form-builder/doctypes/{name}/fields` | Add field |
| PUT | `/api/v1/form-builder/doctypes/{name}/fields/{field}` | Update field |
| DELETE | `/api/v1/form-builder/doctypes/{name}/fields/{field}` | Delete field |
| POST | `/api/v1/form-builder/doctypes/{name}/fields/reorder` | Reorder |

### Workflow

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/workflows` | All workflows (admin) |
| POST | `/api/v1/workflows` | Create workflow (admin) |
| GET | `/api/v1/workflows/{id}` | Workflow + states + transitions (admin) |
| DELETE | `/api/v1/workflows/{id}` | Delete (admin) |
| GET | `/api/v1/workflows/doctype/{doctype}` | Active workflow for DocType |
| GET | `/api/v1/workflows/transitions/{doctype}/{name}` | Available buttons for current user |
| POST | `/api/v1/workflows/apply/{doctype}/{name}` | `transition_id, [comment]` |
| GET | `/api/v1/workflows/history/{doctype}/{name}` | Transition log |

### MCP — AI Agent

```bash
# Discover all tools
curl -X POST http://localhost:8000/api/v1/mcp/tools \
  -H "Authorization: Bearer $TOKEN"

# Execute a tool
curl -X POST http://localhost:8000/api/v1/mcp/execute \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"tool":"list_user","arguments":{"filters":{"role":"admin"}}}'

# Available tool patterns per DocType:
#   get_{doctype}     list_{doctype}    create_{doctype}
#   update_{doctype}  delete_{doctype}
#   submit_{doctype}  cancel_{doctype}  (only if is_submittable=true)
```

---

## 9. Adding a New Module

### Step 1 — Migration

```php
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('name', 140)->unique();        // DocType primary key
    $table->tinyInteger('docstatus')->default(0); // 0=Draft 1=Submitted 2=Cancelled
    $table->string('workflow_state', 120)->nullable();
    $table->string('customer_name');
    $table->string('email')->nullable();
    $table->string('owner', 255)->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### Step 2 — DocumentController

```php
// app/Modules/CRM/Controllers/CustomerDocumentController.php

class CustomerDocumentController extends BaseDocumentController
{
    public function validate(array &$data): void
    {
        if (empty($data['customer_name'])) {
            throw ValidationException::withMessages(['customer_name' => ['Required.']]);
        }
    }

    public function beforeSave(array &$data, ?array $existing = null): void
    {
        if (empty($data['name'])) {
            $data['name'] = 'CUST-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        }
    }

    public function afterSave(array $data, string $action): void
    {
        if ($action === 'create') {
            Event::dispatch(new CustomerCreated($data));  // your domain event
        }
    }

    public function onSubmit(array $data): void
    {
        // Dispatch event only — never call another service directly
        Event::dispatch(new CustomerSubmitted($data));
    }
}
```

### Step 3 — ServiceProvider

```php
// app/Modules/CRM/Providers/CRMServiceProvider.php

class CRMServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        DocTypeRegistry::register('Customer', CustomerDocumentController::class, [
            'module'         => 'CRM',
            'is_submittable' => true,
            'track_changes'  => true,
        ]);

        // Register listeners
        Event::listen(CustomerCreated::class, SendWelcomeEmailListener::class);
    }
}
```

### Step 4 — Register

```php
// bootstrap/providers.php
App\Modules\CRM\Providers\CRMServiceProvider::class,
```

### Step 5 — Filament Resource (optional)

```bash
docker compose exec app php artisan make:filament-resource Customer
```

Edit the generated file to define `form()` fields and `table()` columns.

### What you get automatically after these 5 steps

- ✅ Full REST API (`GET/POST/PUT/DELETE /api/v1/resource/Customer`)
- ✅ Submit / Cancel lifecycle
- ✅ Audit trail on every change
- ✅ MCP tools: `list_customer`, `create_customer`, `submit_customer` ...
- ✅ Workflow support (attach via UI, no code)
- ✅ Form Builder can add custom fields on top

---

## 10. Project Structure

```
src/
├── app/
│   ├── Core/                                     # Engine — no business logic
│   │   ├── Contracts/DocumentController.php      # Interface all modules implement
│   │   ├── DocType/
│   │   │   ├── DocTypeRegistry.php               # In-memory registry
│   │   │   ├── DocTypeSchema.php                 # Metadata for one DocType
│   │   │   └── BaseDocumentController.php        # Default no-op hooks
│   │   ├── Services/
│   │   │   ├── DocumentService.php               # Core CRUD + lifecycle
│   │   │   ├── AuditService.php                  # Immutable audit trail
│   │   │   └── PermissionService.php             # Role-based access
│   │   ├── Events/DocumentEvents.php             # Platform lifecycle events
│   │   ├── FormBuilder/
│   │   │   ├── Services/FormBuilderService.php   # Create DocTypes & fields
│   │   │   └── Http/Controllers/FormBuilderController.php
│   │   ├── Workflow/
│   │   │   ├── Services/WorkflowService.php      # State machine engine
│   │   │   └── Http/Controllers/WorkflowController.php
│   │   ├── Http/Controllers/Api/
│   │   │   ├── ResourceController.php            # One controller, all DocTypes
│   │   │   └── MCPController.php                 # AI agent endpoints
│   │   ├── Middleware/
│   │   │   ├── CheckPermission.php               # permission:DocType.action
│   │   │   └── InitializeTenant.php              # Multi-tenant hook
│   │   └── Providers/CoreServiceProvider.php     # Registers singletons, boots DB DocTypes
│   │
│   ├── Modules/User/                             # Full module example
│   │   ├── DocTypes/UserDocType.php
│   │   ├── Controllers/
│   │   │   ├── UserDocumentController.php        # validate, hash pw, events
│   │   │   ├── AuthController.php
│   │   │   ├── ProfileController.php
│   │   │   └── AdminUserController.php
│   │   ├── Services/UserAuthService.php
│   │   ├── Middleware/AuthenticatePascalUser.php
│   │   ├── Events/UserEvents.php
│   │   ├── Mail/PasswordResetMail.php
│   │   └── Providers/UserServiceProvider.php
│   │
│   ├── Filament/                                 # Admin UI
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   ├── FormBuilder.php                   # Live DocType editor
│   │   │   ├── WorkflowManager.php               # Workflow creator + diagram
│   │   │   └── AuditTrail.php
│   │   ├── Resources/UserResource.php
│   │   └── Widgets/Widgets.php
│   │
│   ├── Models/PascalUser.php                     # Eloquent model for Filament auth
│   └── Providers/
│       ├── AppServiceProvider.php
│       ├── bootstrap_providers.php               # → copy to bootstrap/providers.php
│       └── Filament/AdminPanelProvider.php
│
├── database/
│   ├── migrations/
│   │   ├── ..._create_pascal_users_table.php
│   │   ├── ..._create_auth_tables.php            # tokens, password_reset_tokens
│   │   ├── ..._create_platform_tables.php        # audit_logs, login_histories
│   │   ├── ..._create_form_builder_tables.php    # doctypes, docfields, custom_data
│   │   └── ..._create_workflow_tables.php        # workflows, states, transitions, logs
│   └── seeders/DatabaseSeeder.php                # admin@pascal.com / Admin@123456
│
├── routes/api.php                                # All API routes
├── config/auth.php                               # 'pascal' guard for pascal_users
├── resources/views/filament/
│   ├── pages/
│   │   ├── form-builder.blade.php
│   │   ├── workflow-manager.blade.php
│   │   └── audit-trail.blade.php
│   └── widgets/doctype-registry.blade.php
├── docker-compose.yml                            # MySQL 8, Redis 7, Nginx, Mailpit
├── Dockerfile                                    # PHP 8.3-fpm + extensions
└── tests/Feature/UserModuleTest.php              # 10 feature tests
```

---

## 11. Database Schema

```
pascal_users            name(unique), email(unique), password, role, status,
                        avatar, phone, email_verified_at, last_login_at,
                        last_login_ip, owner, timestamps, soft_deletes

personal_access_tokens  tokenable_type, tokenable_id, name,
                        token(sha256), abilities, last_used_at, expires_at

password_reset_tokens   email(pk), token, created_at

pascal_audit_logs       uuid pk, doctype, docname, action, user_id,
                        user_email, ip_address, before_data(json),
                        after_data(json), diff(json), created_at (immutable)

pascal_login_histories  user_id, ip_address, user_agent, status,
                        failure_reason, logged_in_at, logged_out_at

pascal_doctypes         name(unique), module, label, description, icon,
                        is_submittable, is_tree, track_changes,
                        is_system, is_custom, title_field, timestamps, soft_deletes

pascal_docfields        doctype_id(fk), fieldname, fieldtype, label,
                        required, in_list_view, in_standard_filter,
                        read_only, hidden, bold, columns, sort_order,
                        options, depends_on, default_value, placeholder, timestamps

pascal_custom_data      doctype, name, docstatus, workflow_state,
                        data(json), owner, timestamps, soft_deletes

pascal_workflows        name(unique), doctype, is_active, state_field,
                        timestamps, soft_deletes

pascal_workflow_states  workflow_id(fk), state, doc_status, color, icon,
                        is_initial, allow_edit, sort_order, timestamps

pascal_workflow_transitions  workflow_id(fk), from_state, to_state, action,
                             action_icon, action_color, allowed_roles(json),
                             condition, send_email, requires_comment,
                             requires_confirmation, sort_order, timestamps

pascal_workflow_logs    doctype, docname, transition_id(fk), from_state,
                        to_state, user_id, user_email, comment, created_at
```

---

## Useful Commands

```bash
# Rebuild Docker containers
docker compose up -d --build

# Run all migrations
docker compose exec app php artisan migrate

# Re-seed (recreate admin user)
docker compose exec app php artisan db:seed

# Clear all caches
docker compose exec app php artisan optimize:clear

# Run tests
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=UserModuleTest

# View application logs
docker compose logs app -f

# Open MySQL
docker compose exec db mysql -u pascal -psecret pascal

# Open tinker (REPL)
docker compose exec app php artisan tinker
```
