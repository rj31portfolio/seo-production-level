# Architecture

Laravel 13 serves Blade views compiled with Vite/Tailwind and Alpine. All business actions currently use authenticated session routes with CSRF protection. A separate REST API/token-authentication layer remains to be implemented.

## Tenant boundaries

`TenantContext` is container-scoped. `ResolveTenant` resolves the session's agency ID through the authenticated user's agency memberships, rejects inactive users/suspended agencies, runs before route-model bindings, and clears the context in `finally`. An untrusted request cannot select an agency merely by submitting its ID.

`TenantModel` applies an agency query scope and throws if context is missing. Creation fills agency_id from the trusted context; model mutation/deletion rejects a foreign agency or changed agency_id. Controllers never accept agency_id as writable input. Composite database foreign keys also prevent relationships connecting records across agencies.

Platform agencies/users/shared roles/permissions/settings are intentionally global. Platform routes use `superadmin.manage`; they do not silently bypass business-model tenant scopes. Activity logs have nullable agency_id for platform events; agency activity queries explicitly filter the current agency. Any new query on that global model must preserve that distinction.

Workers/commands must enter `TenantContext::run($agency, $callback)`, reload scoped records inside that callback, and never derive context from a job-submitted user ID alone. Queued reminder emails contain only the necessary immutable client name/expiry text and perform no unscoped model lookup in the worker.

## Authorization

Permissions reside in relational roles/permissions tables. Gates check actual database permissions through the current membership; operational policies additionally check agency ownership and employee project assignments. Super Admin has platform access, not an implicit tenant membership. Owner roles cannot be assigned/removed through employee forms. Revoking membership removes agency access without deleting the user's historical work.

## Business services

`ClientBilling` owns transactional renewals, invoices, manual payments, and subscription history. Renewal/payment writes lock their subscription/invoice row. Money is represented as integer minor units, avoiding floating-point currency arithmetic. History rows retain before/after service state and actor/reason.

`OperationsController` uses an allowlisted module definition for clients/projects/websites. Resource model classes and validation fields cannot be selected by client input. Parent IDs are validated against scoped records and project websites must belong to the selected client.

Future crawler, AI, ranking providers, exports, task automation and SaaS billing are separate modules. They do not currently exist; extending the application must follow the original sequential test gate.
