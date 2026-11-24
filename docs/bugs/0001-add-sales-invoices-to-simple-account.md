# Plan: Add sales invoices (Issue #1)

## Summary

Simple Accounts has no sales invoicing today. Users need to create draft invoices for customers, edit them, issue them with a unique company-level number, and download an A4 PDF. Once issued, an invoice must be immutable. That covers its number, dates, seller and customer details, lines and totals, and it must stay that way even when master data changes later.

### What the codebase already has (and doesn't)

| Concern | Current state | Consequence for this ticket |
| -- | -- | -- |
| Multi-company scoping | `BelongsToCompany` trait + `CompanyScope` global scope (`app/Traits/BelongsToCompany.php`, `app/Scopes/CompanyScope.php`). `SetCompanyContext` middleware resolves the company from the `X-Company-ID` header and active `company_users` membership. Controllers also filter explicitly with `->where('company_id', $companyId)` (e.g. `ReceiptController`). | Reuse as-is. New models use the trait, and controllers keep the explicit filter. |
| Customers | **No customer entity.** "Customers" exist only as postable accounts under the `العملاء` (1130) group in the chart of accounts (`DatabaseSeeder.php:239`). Accounts have no address or tax number. | A small `customers` table is required (see Decision 1). |
| Product/service catalog | None. | Manual line descriptions only. No `product_id` column (see Decision 2). |
| Tax settings | None. `companies.tax_number` exists, and that is all. | Tax rate is entered per line (0–100 %), validated numerically (see Decision 3). |
| Currency | Global `currencies` table with `decimal_places`; `companies.base_currency_id`; `companies.timezone`. | Default the invoice currency to the company base currency, and use `decimal_places` as the rounding precision. |
| Rounding convention | None. Services use PHP floats with `round(..., 4)`. | Implement the issue's fallback rule with `brick/math` `BigDecimal` (already installed via `laravel/framework`, v0.18 in `composer.lock`). |
| Numbering | `DocumentNumberingService::generate()` produces `{PREFIX}-{YEAR}-{000001}` by scanning `MAX` of existing numbers. That approach is **not concurrency-safe**. | Reuse the *format* (`INV-2026-000001`) but allocate from a locked sequence row (see Decision 4). |
| Authorization | No policies/gates/permissions. Role string on `company_users.role` (`COMPANY_ADMIN`, `ADMIN`, `ACCOUNTANT`, `AUDITOR`, `VIEWER`), checked inline (`UserController::isCompanyAdmin`). `roles.permissions` JSON exists but is unused. | Add an `InvoicePolicy` built on the role string (see Decision 5). |
| PDF | No PDF library. Reports use `window.print()`. UI is Arabic RTL (`welcome.blade.php` `dir="rtl"`). | Add `mpdf/mpdf` (see Decision 6). |
| Audit | `AuditLog::create([...])` in services (`ReceiptService`). | Log create/update/delete/issue the same way. |
| API style | `/api/v1/...` under `auth:sanctum`, JSON `{success, message, data}`, inline `$request->validate`, services wrap writes in `DB::transaction`, paginate 15. | Follow the same conventions. |
| Frontend | One view per screen in `resources/js/views`, routes in `resources/js/router.js`, sidebar in `App.vue`, axios with Bearer token + `X-Company-ID` from `localStorage`. | Add the views, routes and sidebar entries. The PDF is downloaded via axios `blob`, because auth is a header token rather than a cookie. |

### Key decisions

1. **Customers table.** The issue requires selecting "existing customers" with billing name, address, and tax number, and none of these exist. Add a minimal `customers` table and the smallest API/UI needed to create and edit them. Add an optional `receivable_account_id` that references `accounts`. It is not used in this ticket, but the future journal-entry ticket will need it, and adding it now avoids a migration on a populated table later. Payment terms are **not** added: the issue says to use them only "if the project already stores them". Due date therefore defaults to the invoice date.
2. **No product reference.** No catalog exists, so a nullable `product_id` with nothing to reference is speculative. Lines are manually described. The catalog ticket can add the column.
3. **Tax rate per line.** No tax settings exist. `tax_rate` is a required numeric field from 0 to 100 with up to 4 decimals, and zero is allowed. Mixed rates per invoice are supported naturally.
4. **Numbering.** Keep the project's `{PREFIX}-{YEAR}-{NNNNNN}` format: `INV-<year of invoice_date>-000001`, which yields a filename such as `INV-2026-000001.pdf`. Allocate from a new `document_sequences` row `(company_id, prefix, year, last_value)` locked with `lockForUpdate()` inside the issuance transaction. A DB unique index `(company_id, invoice_number)` acts as a backstop. The existing JE/RV/PV numbering is **not** migrated in this ticket (see Risks).
5. **Permissions.**
   - `viewAny`/`view`/`download`: any active member of the company.
   - `create`/`update`/`delete`/`issue`: roles `COMPANY_ADMIN`, `ADMIN` and `ACCOUNTANT`, plus `users.is_super_admin`.
   - `AUDITOR`/`VIEWER`: read-only.
   - `update`/`delete` additionally require `status = DRAFT`.
   - Cross-company access returns 404, because the record is not found within the company scope.
6. **PDF engine: `mpdf/mpdf`.**
   - It handles Arabic shaping and RTL, which dompdf does not handle reliably.
   - It repeats `<thead>` on every page by default, which meets the issue's "repeat table headers across pages" requirement.
   - It requires `ext-gd` and `ext-mbstring`, and both are already installed in the `Dockerfile` and CI.
   - It is the only new dependency.
7. **Server-side totals for the form preview.** Rather than duplicating the decimal/rounding logic in JS, which risks mismatches, the form calls a stateless `POST /api/v1/invoices/calculate` endpoint (debounced). This uses the same calculator as save and issue. The form, details view and PDF therefore always show identical server numbers.
8. **Status values** follow project convention (uppercase): `DRAFT`, `ISSUED`. The column is `string(20)` rather than `enum`, so later statuses (paid, void) do not need an enum alteration, which SQLite cannot do in place.

## Calculation rules (single implementation: `InvoiceCalculator`)

For each line, with `p` = currency `decimal_places` and rounding `HALF_UP`:

```
gross           = round(quantity × unit_price, p)
discount_amount = round(gross × discount_rate / 100, p)
net_amount      = gross − discount_amount            (amount before tax)
tax_amount      = round(net_amount × tax_rate / 100, p)
line_total      = net_amount + tax_amount
```

Invoice totals are the sums of rounded line values:
- `subtotal` = Σ gross
- `discount_total` = Σ discount
- `net_total` = Σ net
- `tax_total` = Σ tax
- `grand_total` = net_total + tax_total

Inputs are handled as strings into `BigDecimal`, never as floats. Example from the issue: 2 × 100.00, 10 %, 15 % gives 200.00 / 20.00 / 180.00 / 27.00 / 207.00.

## Data model

### Migration `database/migrations/2026_11_24_000001_create_customers_table.php`
`customers`:
- `id`
- `company_id` FK, cascade
- `name` string(255)
- `billing_name` string(255) nullable (falls back to `name`)
- `address` text nullable
- `tax_number` string(100) nullable
- `email` string(100) nullable
- `phone` string(50) nullable
- `receivable_account_id` FK `accounts`, nullable, nullOnDelete
- `is_active` bool, default true
- timestamps
- index `(company_id, name)`

### Migration `..._000002_create_document_sequences_table.php`
`document_sequences`:
- `id`
- `company_id` FK, cascade
- `prefix` string(10)
- `year` unsignedSmallInteger
- `last_value` unsignedBigInteger, default 0
- timestamps
- unique `(company_id, prefix, year)`

### Migration `..._000003_create_invoices_table.php`
`invoices`:
- `id`
- `company_id` FK, cascade
- `customer_id` FK `customers`, restrictOnDelete
- `status` string(20), default `DRAFT`
- `invoice_number` string(50) nullable
- `invoice_date` date
- `due_date` date
- `currency_id` FK `currencies`
- `currency_code` string(10)
- `currency_decimal_places` tinyint. This is snapshotted because `PUT /currencies/{id}` can change it.
- `customer_reference` string(100) nullable
- `notes` text nullable
- `customer_name` string(255). This is denormalized for list/search, refreshed on every draft save and frozen at issue.
- `subtotal`, `discount_total`, `net_total`, `tax_total`, `grand_total`: decimal(18,4)
- `seller_snapshot` json nullable
- `customer_snapshot` json nullable
- `created_by` FK users
- `issued_by` FK users nullable
- `issued_at` timestamp nullable
- timestamps
- **unique `(company_id, invoice_number)`**. Multiple NULLs are allowed for drafts on both MySQL and SQLite.
- index `(company_id, status, invoice_date)`

### Migration `..._000004_create_invoice_lines_table.php`
`invoice_lines`:
- `id`
- `invoice_id` FK, cascade
- `position` unsignedSmallInteger
- `description` string(500)
- `quantity` decimal(18,4)
- `unit_price` decimal(18,4)
- `discount_rate` decimal(7,4), default 0
- `discount_amount` decimal(18,4)
- `net_amount` decimal(18,4)
- `tax_rate` decimal(7,4)
- `tax_amount` decimal(18,4)
- `line_total` decimal(18,4)
- `gross_amount` decimal(18,4)
- timestamps

Snapshots, populated at issuance only:
- `seller_snapshot`: `name`, `legal_name`, `tax_number`, `commercial_registration`, `address`, `phone`, `email` from `companies`.
- `customer_snapshot`: `name`, `billing_name`, `address`, `tax_number`, `email`, `phone` from `customers`.

Details/PDF read snapshots for issued invoices and live relations for drafts, through a single model accessor (`sellerDetails()` / `customerDetails()`).

## Files to create

| File | Purpose |
| -- | -- |
| `app/Models/Customer.php` | `BelongsToCompany`, `HasFactory`, fillable, `invoices()` relation. |
| `app/Models/Invoice.php` | `BelongsToCompany`. Constants `STATUS_DRAFT`/`STATUS_ISSUED`. Casts: dates `date:Y-m-d`, totals `decimal:4`, snapshots `array`, `issued_at` datetime. Relations: `customer`, `currency`, `lines` (ordered by `position`), `createdBy`, `issuedBy`. Methods `isDraft()`, `sellerDetails()`, `customerDetails()`. Appends `display_number` (invoice number or `مسودة #<id>`). |
| `app/Models/InvoiceLine.php` | Fillable, decimal casts, `invoice()` relation. |
| `app/Models/DocumentSequence.php` | Plain model (no global scope; always queried by explicit `company_id`). |
| `app/Services/InvoiceCalculator.php` | Pure class: `calculate(array $lines, int $decimalPlaces): array` returns per-line amounts plus totals as strings, using `Brick\Math\BigDecimal` with `RoundingMode::HALF_UP`. No DB access. |
| `app/Services/InvoiceService.php` | `create(array $data, User $by)`, `update(Invoice, array, User)`, `delete(Invoice, User)`, `issue(Invoice, User)`. Every write runs in `DB::transaction`, re-fetches the invoice with `lockForUpdate()` and asserts `DRAFT` *inside* the transaction (throws `InvoiceNotEditableException`). Lines are replaced wholesale on update. Totals always come from `InvoiceCalculator`, and client-sent totals are ignored. Writes `AuditLog` entries (`CREATE_INVOICE`, `UPDATE_INVOICE`, `DELETE_INVOICE`, `ISSUE_INVOICE`). **`issue()`**: lock the row; if already `ISSUED`, return it unchanged (idempotent retry). Otherwise re-validate (≥1 line, `due_date ≥ invoice_date`, customer still in company), recalculate totals, allocate the number, write the snapshots, set `status`/`issued_by`/`issued_at`. |
| `app/Services/DocumentSequenceService.php` | `next(int $companyId, string $prefix, int $year): string`. Steps: `insertOrIgnore` the row; `lockForUpdate()` select; increment `last_value`; return `sprintf('%s-%d-%06d', …)`. Must be called inside the caller's transaction. |
| `app/Exceptions/InvoiceNotEditableException.php` | Rendered as HTTP 409 JSON `{success:false, message}`. |
| `app/Http/Requests/SaveInvoiceRequest.php` | Shared by `store`, `update` and `calculate`. The rules are listed below. |
| `app/Policies/InvoicePolicy.php` | `viewAny`, `view`, `create`, `update`, `delete`, `issue`, `download`, based on the active `CompanyUser` role for the current company (Decision 5). Auto-discovered by Laravel. |
| `app/Http/Controllers/API/InvoiceController.php` | Covers `index`, `show`, `store`, `update`, `destroy`, `issue`, `calculate` and `pdf`. Each action resolves the invoice with `Invoice::where('company_id', $companyId)->findOrFail($id)` and calls `$this->authorize(...)`. If the company context is null, it aborts with 403 so it never falls through to an unscoped query. |
| `app/Http/Controllers/API/CustomerController.php` | `index` (search, active, paginate), `store`, `update`, validated inline like other master-data controllers. Uses the same write roles as invoices. |
| `app/Services/InvoicePdfRenderer.php` | Renders `resources/views/invoices/pdf.blade.php` through `Mpdf\Mpdf` (`format A4`, `tempDir storage_path('app/mpdf')`, `autoScriptToLang`, `autoLangToFont`, `default_font dejavusans`, RTL). Returns the binary string. |
| `resources/views/invoices/pdf.blade.php` | Seller and customer blocks with tax numbers; invoice number, dates, currency, reference; line table with `<thead>` (repeats per page); totals table with `page-break-inside: avoid`; notes. Only stored invoice data is used. Amounts are formatted with `number_format($v, $invoice->currency_decimal_places)`. |
| `database/factories/CustomerFactory.php`, `InvoiceFactory.php` | Test fixtures. |
| `resources/js/views/InvoicesView.vue` | List view: number or draft label, customer, invoice date, due date, status badge, currency, total. It has a search box (number/customer), a status filter, a date-from/date-to filter, pagination (mirrors `ReceiptsView.vue`), and a **New invoice** button shown only to users with write roles. |
| `resources/js/views/InvoiceFormView.vue` | Create/edit form with these parts: customer select (shows billing name/address/tax number); invoice date (defaults to today in `currentCompany.timezone` via `Intl.DateTimeFormat`); due date; currency (defaults to `currentCompany.base_currency_id`); reference; notes; line editor (add/remove rows: description, qty, unit price, discount %, tax %). Totals come from a debounced `POST /invoices/calculate`. Laravel `errors` (e.g. `lines.0.quantity`) are mapped onto fields. The form saves as a draft. It redirects to details if the invoice is not a draft. |
| `resources/js/views/InvoiceDetailView.vue` | Shows seller/customer blocks, dates, status, lines, totals, reference and notes. Actions come from the `abilities` returned by `show`: Edit / Delete / **Issue invoice** (Bootstrap confirm modal) for drafts, **Download PDF** for issued invoices (axios `responseType: 'blob'`, filename from `Content-Disposition`). |
| `resources/js/views/CustomersView.vue` | Minimal list plus a create/edit modal (name, billing name, address, tax number, email, phone, receivable account). |
| `tests/Unit/InvoiceCalculatorTest.php` | See the Test plan below. |
| `tests/Feature/InvoiceTest.php` | See the Test plan below. |
| `tests/Feature/InvoicePdfTest.php` | See the Test plan below. |
| `tests/Feature/CustomerTest.php` | See the Test plan below. |

### Validation rules (`SaveInvoiceRequest`)

```
customer_id          required | integer | Rule::exists('customers','id')->where('company_id', $cid)->where('is_active', true)
invoice_date         required | date_format:Y-m-d
due_date             required | date_format:Y-m-d | after_or_equal:invoice_date
currency_id          required | Rule::exists('currencies','id')->where('is_active', true)
customer_reference   nullable | string | max:100
notes                nullable | string | max:2000
lines                required | array | min:1 | max:500
lines.*.description  required | string | max:500           (trimmed; empty → error)
lines.*.quantity     required | numeric | gt:0 | decimal:0,4
lines.*.unit_price   required | numeric | gte:0 | decimal:0,4
lines.*.discount_rate nullable | numeric | between:0,100 | decimal:0,4
lines.*.tax_rate     required | numeric | between:0,100 | decimal:0,4
```

Laravel returns field-level 422 errors (`errors["lines.0.quantity"]`), which meets the acceptance criterion directly.

## Files to modify

| File | Change |
| -- | -- |
| `routes/api.php` | Inside the `auth:sanctum` group add the following routes. Static `calculate` is declared before `{id}`. |
| `app/Models/Company.php` | Add `customers()` and `invoices()` HasMany, matching the existing relation list. |
| `bootstrap/app.php` | Render `InvoiceNotEditableException` as a 409 JSON response in `withExceptions`. Alternatively, give the exception its own `render()` method and leave this file untouched. |
| `composer.json` / `composer.lock` | `composer require mpdf/mpdf` and `composer require brick/math:^0.18` (already in the lock; this makes the direct dependency explicit). |
| `resources/js/router.js` | Routes `/invoices`, `/invoices/create`, `/invoices/:id`, `/invoices/:id/edit` and `/customers`, all with `meta: { requiresAuth: true }`. |
| `resources/js/App.vue` | New sidebar category **المبيعات** (Sales) with **الفواتير** (Invoices) and **العملاء** (Customers), styled like existing links. |
| `.gitignore` / `storage/app/.gitignore` | Ignore `storage/app/mpdf/`. |
| `README.md` | Add Sales Invoices to the feature list, if the README has one. |

Routes to add to `routes/api.php`:

```php
Route::get('customers', [CustomerController::class, 'index']);
Route::post('customers', [CustomerController::class, 'store']);
Route::put('customers/{id}', [CustomerController::class, 'update']);

Route::post('invoices/calculate', [InvoiceController::class, 'calculate']);
Route::get('invoices', [InvoiceController::class, 'index']);
Route::post('invoices', [InvoiceController::class, 'store']);
Route::get('invoices/{id}', [InvoiceController::class, 'show']);
Route::put('invoices/{id}', [InvoiceController::class, 'update']);
Route::delete('invoices/{id}', [InvoiceController::class, 'destroy']);
Route::post('invoices/{id}/issue', [InvoiceController::class, 'issue']);
Route::get('invoices/{id}/pdf', [InvoiceController::class, 'pdf']);
```

### Controller behaviour

- **`index`**: `with(['currency'])`, filtered by company. Query parameters:
  - `search`: `invoice_number LIKE` or `customer_name LIKE`
  - `status`
  - `date_from` / `date_to` on `invoice_date`
  - Ordered `invoice_date desc, id desc`, `paginate(15)`.
- **`show`**: loads `lines`, `customer`, `currency`, `createdBy`, `issuedBy`. Adds `seller`/`customer_details` from the accessor, plus `abilities` (`update`, `delete`, `issue`, `download`) from the policy so the UI shows only allowed actions.
- **`store` / `update`**: return 201/200 with server totals. A non-draft invoice returns 409.
- **`destroy`**: draft only, otherwise 409. Lines are removed by cascade.
- **`issue`**: returns 200 with the issued invoice. Calling it again on an already-issued invoice returns the same payload and number.
- **`calculate`**: stateless. Returns `{lines: [...], totals: {...}}` for the requested `currency_id` and does not persist anything.
- **`pdf`**: issued only (409 for drafts). Responds with `Content-Type: application/pdf` and `Content-Disposition: attachment; filename="{invoice_number}.pdf"`.

## Test plan

All tests use `RefreshDatabase` and `Sanctum::actingAs` with an `X-Company-ID` header, following the pattern in `tests/Feature/UserStatusTest.php`.

**`tests/Unit/InvoiceCalculatorTest.php`**
- The issue example (2 × 100.00, 10 % disc, 15 % tax) gives 200.00 / 20.00 / 180.00 / 27.00 / 207.00.
- A fractional quantity with a rounding boundary rounds HALF_UP per line before summing (e.g. 1.5 × 33.33 = 49.995 → 50.00).
- Mixed tax rates (15 % and 0 %) on two lines give totals equal to the sum of rounded line taxes.
- A 100 % discount gives a net of 0 and a tax of 0.
- 0-decimal and 3-decimal currencies (e.g. JPY, KWD) give the correct precision.

**`tests/Feature/InvoiceTest.php`**
- Draft lifecycle: an accountant can create a draft (201, `status DRAFT`, `invoice_number null`, server totals), reopen it (`show`), update it (lines replaced, totals recomputed) and delete it.
- Client-sent `grand_total` values are ignored.
- Validation: each invalid input is rejected with 422 and its field-level error key.

  | Invalid input | Error key |
  | -- | -- |
  | `quantity` 0 | `lines.0.quantity` |
  | negative `unit_price` | `lines.0.unit_price` |
  | `discount_rate` 101 | `lines.0.discount_rate` |
  | blank description | `lines.0.description` |
  | `due_date` before `invoice_date` | `due_date` |
  | empty `lines` | `lines` |
  | other company's `customer_id` | `customer_id` |
- Issue: first issue gives `INV-2026-000001`, with `issued_by` and `issued_at` set and snapshots populated. Issuing a second draft gives `…000002`.
- Idempotent retry: issuing the same invoice twice keeps the same number, and `document_sequences.last_value` is unchanged.
- Uniqueness backstop: inserting a duplicate `(company_id, invoice_number)` raises `UniqueConstraintViolationException`.
- Numbers are per company: company B's first invoice is also `…000001`.
- Locked after issue: `PUT` and `DELETE` on an issued invoice return 409 and the DB row is unchanged. Issuing a deleted draft returns 404.
- Immutability: after issuing, change the customer name/address/tax number, company `tax_number`/address, and currency `decimal_places`. `show` still returns the original snapshot values and totals.
- Permissions:
  - `VIEWER` and `AUDITOR` can list, show and download, but get 403 on store, update, delete and issue.
  - A user from another company gets 403 (header for a non-member company, via middleware) or 404 (own company header, foreign invoice id) on show, update, delete, issue and pdf.
- List: search by number and by customer name, filter by status and date range, newest-first ordering, and the pagination `per_page`/`total` are correct.

**`tests/Feature/InvoicePdfTest.php`**
- An issued invoice returns 200, `application/pdf`, and a body starting with `%PDF`. The filename header is `INV-2026-000001.pdf`.
- A draft PDF returns 409.
- A 120-line invoice renders successfully, as a multi-page smoke test.
- PDF after a master-data change: the renderer's view data comes from the snapshot. Assert on the rendered Blade HTML (`view('invoices.pdf', …)->render()`), which contains the old customer name and not the new one, so the test does not depend on parsing PDF text.

**`tests/Feature/CustomerTest.php`**
- Create, list and update are scoped to the company.
- Another company's customer is not visible.
- `VIEWER` cannot create.

Commands: `php artisan test` (or `composer test`), and `vendor/bin/pint --dirty` for formatting. CI requires `npm run build` to succeed, so the new Vue views must compile.

## Risks and open questions

1. **Customer entity (confirm).** The plan adds a `customers` table rather than reusing chart-of-accounts sub-accounts. These are two separate things to confirm with the maintainer:
   - whether this is the desired model;
   - whether a customer should be *required* to link a receivable account now. The plan makes it optional.
2. **No product catalog or tax settings.** Lines are manual and tax rates are typed per line. When those features land, add `product_id` / `tax_rate_id` with company-scoped validation.
3. **Numbering format.** The plan uses the existing year-based convention `INV-YYYY-NNNNNN` (resets yearly), per "reuse the project's numbering convention". If a single non-resetting `INV-000001` sequence is preferred, pass `year = 0` and drop the year from the format. Only the format string changes.
4. **Concurrency testing.** `lockForUpdate()` is a no-op on SQLite, which serializes writers at the DB level, so true concurrent allocation cannot be exercised in the in-memory test suite. Correctness on MySQL rests on three things:
   - the row lock;
   - `insertOrIgnore` on the unique `(company_id, prefix, year)` key for first-use races;
   - the unique `(company_id, invoice_number)` index as a backstop.

   Consider a manual or Docker MySQL check with parallel `curl` issuance before release.
5. **Existing JE/RV/PV numbering is racy.** `DocumentNumberingService` uses a `MAX+1` scan. This is out of scope here, but `document_sequences` is generic, so a follow-up ticket can migrate it.
6. **mPDF footprint and fonts.** mPDF adds about 20–30 MB to `vendor` (fonts). It writes temp files to `storage/app/mpdf`, which must be writable in Docker. `storage/` already is. Arabic text renders with the bundled `dejavusans` / `xbriyaz` fonts. If brand fonts are wanted later, they must be registered in `fontdata`.
7. **Company timezone vs app timezone.** `config/app.php` is UTC, and `companies.timezone` defaults to `Asia/Riyadh`. The "today" default is computed client-side from `currentCompany.timezone`. The server only validates the date, so there is no server-side default to drift.
8. **Pre-existing, out of scope:**
   - `company_users.role` is an enum of `SUPER_ADMIN|COMPANY_ADMIN|ACCOUNTANT|VIEWER`, but `UserController::store` accepts `ADMIN`/`AUDITOR`. On MySQL those inserts would fail. The policy treats `ADMIN` as a writer and `AUDITOR` as read-only, so it is correct either way.
   - `SetCompanyContext` checks `$user->role`, which does not exist on `User`; super admins are flagged by `is_super_admin`.

   Both should be tracked separately.
9. **Out of scope per the issue:** payments, credit notes, journal posting, ZATCA/e-invoicing, and customer payment terms.
