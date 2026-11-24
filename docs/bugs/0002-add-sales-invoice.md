# 0002 — Add sales invoices (draft → issue → PDF)

Issue: #2 "add sales invoice". Branch: `fix/2-add-sales-invoice`.

## 1. Summary

Users need to create sales invoices for customers. Each invoice starts as an editable draft. When issued, it gets a unique number and is locked. Users can then download it as a PDF. Payments, credit notes, automated journal entries and ZATCA are out of scope.

### What the codebase has today (and what it lacks)

| Issue asks to reuse | Exists? | Finding |
|---|---|---|
| Customers | **No entity** | Customers exist only as receivable **accounts** (seeder `DatabaseSeeder.php:239-263`, group "العملاء"). Accounts have no billing name, address or tax number. |
| Customer payment terms | No | "Use if the project already stores them" → skip; due date defaults to invoice date. |
| Product / service catalog | No | "Where available" → skip; manual lines only. |
| Tax settings / tax rates | No | Tax rate is entered per line as a percentage. Because issued lines are snapshots, later tax setting changes cannot affect them. |
| Currency support | Yes | `currencies` table (global, `decimal_places`), `companies.base_currency_id`. |
| Company time zone | Yes | `companies.timezone` (default `Asia/Riyadh`), exposed to the SPA via `authStore.currentCompany`. |
| Company scoping | Yes | `BelongsToCompany` trait + `CompanyScope` + `SetCompanyContext` middleware (`X-Company-ID`, membership check → 403). Controllers also add an explicit `->where('company_id', $companyId)` (e.g. `ReceiptController::show`). |
| Authorization | Partial | No Gates/Policies. The only convention is the inline role check `UserController::isCompanyAdmin()` (`app/Http/Controllers/API/UserController.php:105`) returning a 403 JSON. Roles live in `company_users.role` (`COMPANY_ADMIN`, `ADMIN`, `ACCOUNTANT`, `AUDITOR`, `VIEWER`, `SUPER_ADMIN`) plus `users.is_super_admin`. `roles.permissions` JSON exists but is never read. |
| Document numbering | Yes, but racy | `DocumentNumberingService::generate()` does `max(existing)+1` with no lock. Concurrent calls can return the same number, and only the unique index catches it, as a 500 error. That is not good enough for the "concurrent issuance" criterion. |
| Draft/post lifecycle | Yes | `Receipt`/`Payment`: `status` DRAFT→POSTED, service in `DB::transaction`, `AuditLog::create` per action, controller catches `Exception` → 422 `{success:false,message}`. |
| Decimal math | Partial | Uses PHP floats + `round()`. `bcmath` is installed in both CI (`tests.yml`) and the Docker image. |
| PDF generation | **No** | No PDF dependency. The UI is Arabic/RTL. |

### Approach

1. **Add a minimal `customers` entity**, company-scoped, with name, billing address, tax number, email and phone. Expose a small CRUD API and a page so invoices have something to select. I considered adding billing columns to `accounts`. I rejected it because it would mix chart-of-accounts data with party data. A future AR/journal ticket can add a nullable `customers.account_id`. That link is not added here because nothing in this ticket needs it.
2. **Add the `sales_invoices` and `sales_invoice_lines` tables.** Totals are stored, server-calculated and rounded per line to the currency's `decimal_places`, using `bcmath`.
3. **Use one calculator (`SalesInvoiceCalculator`)** for everything: saving drafts, the form's live totals (`POST /sales-invoices/calculate`), the details view and the PDF. Form, details and PDF therefore always match.
4. **Issue in a transaction** that locks the invoice row (`lockForUpdate`). If the invoice is already `ISSUED`, the original invoice is returned unchanged (idempotent). Otherwise the service reserves a number from a new row-locked `document_sequences` counter. It then freezes customer, seller and currency details into JSON snapshot columns and records `issued_by`/`issued_at`. A `unique(company_id, invoice_number)` index acts as a backstop.
5. **Make issued invoices immutable** at two layers: a service/controller status check (422), and a model `updating`/`deleting` guard on `SalesInvoice` and `SalesInvoiceLine`. The guard also blocks direct requests and any future code path.
6. **Generate the PDF with `mpdf/mpdf`.** It handles Arabic shaping and RTL, unlike dompdf, and repeats `<thead>` on multi-page tables. The PDF is built from a Blade view that reads **only stored invoice/line/snapshot fields**.
7. **Authorization** follows the existing inline-role convention, pulled into one private helper per controller:
   - **read** (list, view, PDF): any active member of the company (the middleware already enforces membership).
   - **manage** (create, update, delete, issue, calculate, customer writes): `COMPANY_ADMIN`, `ADMIN`, `ACCOUNTANT`, or `users.is_super_admin`.
   - `VIEWER`/`AUDITOR` are read-only.
   - Cross-company IDs return 404 through scoped queries, matching `test_cannot_toggle_member_of_another_company`.

## 2. Data model (new migrations)

All new migrations go in `database/migrations/`, dated after `2026_09_17_085302`.

### `2026_11_24_000001_create_customers_table.php`
- `id`, `company_id` FK→companies cascadeOnDelete
- `name` string(255)
- `address` text null, `tax_number` string(100) null, `email` string(100) null, `phone` string(50) null
- `is_active` bool default true, timestamps
- index `(company_id, name)`

### `2026_11_24_000002_create_document_sequences_table.php`
- `id`, `company_id` FK cascade, `prefix` string(10), `year` unsignedSmallInteger, `last_number` unsignedInteger default 0, timestamps
- unique `(company_id, prefix, year)`

### `2026_11_24_000003_create_sales_invoices_table.php`
- `id`, `company_id` FK cascade
- `customer_id` FK→customers **restrictOnDelete**
- `invoice_number` string(50) **nullable**, null while draft
- `status` enum `DRAFT`,`ISSUED` default `DRAFT`
- `invoice_date` date, `due_date` date
- `currency_id` FK→currencies restrictOnDelete
- `customer_reference` string(100) null, `notes` text null
- `subtotal`, `discount_total`, `taxable_total`, `tax_total`, `grand_total`: decimal(18,4) default 0
- `customer_snapshot` json null, `seller_snapshot` json null, `currency_snapshot` json null (`code`, `symbol`, `decimal_places`)
- `issued_at` timestamp null, `issued_by` FK→users null nullOnDelete, `created_by` FK→users
- timestamps
- unique `(company_id, invoice_number)`. Multiple NULLs are allowed in both MySQL and SQLite.
- index `(company_id, status, invoice_date)`, index `(company_id, customer_id)`

### `2026_11_24_000004_create_sales_invoice_lines_table.php`
- `id`, `sales_invoice_id` FK cascadeOnDelete, `position` unsignedSmallInteger
- `description` string(500)
- `quantity` decimal(18,4), `unit_price` decimal(18,4), `discount_amount` decimal(18,4) default 0, `tax_rate` decimal(5,2) default 0
- `line_subtotal`, `taxable_amount`, `tax_amount`, `line_total`: decimal(18,4)
- timestamps; index `(sales_invoice_id, position)`

Lines store everything needed to reproduce them, including the description and rate. They have no foreign keys to products or taxes, so later changes to product or tax settings cannot affect them.

## 3. Files to create / modify

### Backend — create

| File | Purpose |
|---|---|
| `app/Models/Customer.php` | `BelongsToCompany`, `HasFactory`; fillable as above; `salesInvoices()` hasMany. |
| `app/Models/SalesInvoice.php` | `BelongsToCompany`. Casts: dates `date:Y-m-d`, totals `decimal:4`, snapshots `array`, `issued_at` datetime. Relations: `customer`, `currency`, `lines` (ordered by `position`), `issuedBy`, `createdBy`. `isIssued()`. In `booted()`, `updating` throws if `getOriginal('status') === 'ISSUED'` and `deleting` throws if `status === 'ISSUED'`. The `updating` guard must still allow the DRAFT→ISSUED save, which it does because the original status is DRAFT. |
| `app/Models/SalesInvoiceLine.php` | Casts decimals. `booted()` `saving`/`deleting` guard: throw if the parent invoice is `ISSUED`. |
| `app/Models/DocumentSequence.php` | Plain model (not company-global-scoped; always queried by explicit `company_id`). |
| `app/Services/SalesInvoiceCalculator.php` | Pure, stateless class with no database access. `calculate(array $lines, int $decimals): array` returns per-line `line_subtotal = round(qty × price)`, `taxable = subtotal − discount`, `tax = round(taxable × rate / 100)`, `line_total = taxable + tax`, plus document totals (sums of the line values). It uses `bcmath` with a private half-up `round()` helper (`bcadd($v, ±0.5×10^-d, d)`). Inputs and outputs are numeric strings. |
| `app/Services/SalesInvoiceService.php` | Runs in `DB::transaction` and writes `AuditLog` entries like `ReceiptService`. Methods:<br>• `create(array $data, int $userId)`<br>• `update(SalesInvoice, array $data, int $userId)`: re-check `DRAFT` while holding `lockForUpdate`, replace lines (delete + insert), recalculate.<br>• `delete(SalesInvoice, int $userId)`: drafts only.<br>• `issue(SalesInvoice, int $userId)`: lock the row. If already `ISSUED`, return it unchanged. Re-validate that the invoice has ≥1 line, the customer still belongs to the company, and `due_date ≥ invoice_date`. Recalculate. Number = `DocumentNumberingService::reserve($companyId, 'INV', year(invoice_date))`. Write `customer_snapshot` (`name`, `address`, `tax_number`, `email`, `phone`), `seller_snapshot` (company `name`, `legal_name`, `tax_number`, `commercial_registration`, `address`, `phone`, `email`), `currency_snapshot`, `issued_by`, `issued_at = now()`.<br>Business-rule violations throw `Exception` with an Arabic message, matching the existing services. |
| `app/Services/SalesInvoicePdfService.php` | `render(SalesInvoice): string`. Builds an `Mpdf\Mpdf` with `mode => 'utf-8'`, `format => 'A4'`, `autoScriptToLang`/`autoLangToFont => true`, `directionality => 'rtl'`, and `tempDir => storage_path('framework/cache/mpdf')`. Writes `view('pdf.sales-invoice', …)->render()` and returns `Output('', 'S')`. A separate `html(SalesInvoice): string` method keeps the snapshot assertions testable. |
| `app/Http/Controllers/API/CustomerController.php` | `index` (search, `?active=1`, paginate 15 or `?all=1` for the picker), `store`, `show`, `update`. Validation is inline `$request->validate` as in other controllers. Writes require the manage role. |
| `app/Http/Controllers/API/SalesInvoiceController.php` | The endpoints below. It loads the invoice with `SalesInvoice::where('company_id', $companyId)->findOrFail($id)`, so another company's ID returns 404. Response shape is `{success, message?, data}` as elsewhere. A private `canManage(User, ?int $companyId)` mirrors `UserController::isCompanyAdmin` but allows `ACCOUNTANT`. |
| `resources/views/pdf/sales-invoice.blade.php` | RTL A4 layout with seller and customer blocks, invoice number/dates, currency, customer reference, and a lines table with a repeating `<thead>`. Columns: #, description, qty, unit price, discount, taxable, tax %, tax, total. Then a totals block (subtotal, total discount, amount before tax, tax total, grand total) and notes. For issued invoices it reads **only** snapshot fields and stored line/total columns, never `$invoice->customer` or `$invoice->company`. Draft PDFs are not offered (see endpoints). Footer shows page X of Y (`{PAGENO}/{nbpg}`). |

### Backend — modify

| File | Change |
|---|---|
| `composer.json` / `composer.lock` | Add `"mpdf/mpdf": "^8.2"` to `require` (`composer require mpdf/mpdf`). Its required extensions (gd, mbstring) are already present in CI and the Dockerfile. |
| `app/Services/DocumentNumberingService.php` | Add `public static function reserve(int $companyId, string $prefix, int $year): string`. It must run inside the caller's transaction. Steps: `DocumentSequence::query()->insertOrIgnore([...last_number => 0])`, then `->where(...)->lockForUpdate()->first()`, `increment('last_number')`, and return `sprintf('%s-%d-%06d', …)`. Leave the existing `generate()` and its JE/RV/PV callers unchanged (see Risks). |
| `app/Models/Company.php` | Add `customers()` and `salesInvoices()` hasMany relations, matching `receipts()`/`payments()`. |
| `routes/api.php` | Inside the `auth:sanctum` group:<br>`Route::apiResource('customers', CustomerController::class)->except('destroy');`<br>`Route::post('sales-invoices/calculate', …'calculate');` (declared **before** `{id}` routes, like `receipts/next-number`)<br>`Route::get/post('sales-invoices', …)`<br>`Route::get/put/delete('sales-invoices/{id}', …)`<br>`Route::post('sales-invoices/{id}/issue', …)`<br>`Route::get('sales-invoices/{id}/pdf', …)` |

### SalesInvoiceController endpoints

| Method | Route | Role | Notes |
|---|---|---|---|
| GET | `/sales-invoices` | read | Filters: `status`, `customer_id`, `date_from`, `date_to`. `search` matches `invoice_number`, `customer_reference`, live `customer.name`, and `customer_snapshot->name`. Ordered by `invoice_date desc, id desc`, `paginate(15)`, eager loads `customer`, `currency`. |
| POST | `/sales-invoices/calculate` | manage | Same line validation as store. Returns calculator output only, with no writes. |
| POST | `/sales-invoices` | manage | Creates a draft → 201. |
| GET | `/sales-invoices/{id}` | read | Loads `lines`, `customer`, `currency`, `issuedBy:id,name`, `createdBy:id,name`. |
| PUT | `/sales-invoices/{id}` | manage | Draft only, else 422. |
| DELETE | `/sales-invoices/{id}` | manage | Draft only, else 422. |
| POST | `/sales-invoices/{id}/issue` | manage | Idempotent. |
| GET | `/sales-invoices/{id}/pdf` | read | Issued only (422 for drafts). Response is `application/pdf` with `Content-Disposition: attachment; filename="INV-2026-000001.pdf"`. |

**Validation for store, update and calculate** (inline `$request->validate`, giving Laravel's standard 422 with `errors.{field}`):

```php
$companyId = CompanyContextService::getCompanyId();
[
  'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
  'invoice_date' => ['required', 'date_format:Y-m-d'],
  'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
  'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
  'customer_reference' => ['nullable', 'string', 'max:100'],
  'notes' => ['nullable', 'string', 'max:2000'],
  'lines' => ['required', 'array', 'min:1', 'max:500'],
  'lines.*.description' => ['required', 'string', 'max:500'],
  'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999999'],
  'lines.*.unit_price' => ['required', 'numeric', 'gte:0', 'decimal:0,4', 'max:9999999999'],
  'lines.*.discount_amount' => ['nullable', 'numeric', 'gte:0', 'decimal:0,4'],
  'lines.*.tax_rate' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
]
```

`calculate` uses only the `currency_id` and `lines.*` rules.

A `Validator::after()` hook adds `lines.{i}.discount_amount` "must not exceed quantity × unit price". This keeps the error field-level. Because `customer_id` is scoped by `company_id`, another company's customer returns the field error `errors.customer_id`, which covers the "cross-company references" criterion.

### Frontend — create

| File | Purpose |
|---|---|
| `resources/js/views/SalesInvoicesView.vue` | Sales → Invoices list. Search box, status select, customer select, date range, table (number or "مسودة", customer, date, due, grand total + currency, status badge), and **pagination controls** driven by Laravel paginator `current_page/last_page`. Today's `ReceiptsView` paginates server-side but shows no pager, so this view needs its own. Includes a "New invoice" button (manage roles only) and a click-through to details. |
| `resources/js/views/SalesInvoiceFormView.vue` | Used for both create (`/sales-invoices/create`) and edit (`/sales-invoices/:id/edit`). Fields:<br>• customer select, which shows the selected customer's name, address and tax number<br>• invoice date, defaulting to today in `authStore.currentCompany.timezone` via `new Intl.DateTimeFormat('en-CA', { timeZone }).format(new Date())`<br>• due date (defaults to invoice date) and currency (defaults to `currentCompany.base_currency_id`)<br>• customer reference, notes<br>• editable line rows (add/remove; description, qty, price, discount, tax %)<br>A debounced (~300 ms) `POST /sales-invoices/calculate` fills per-line and document totals, so the form shows **server** numbers. Field errors from `err.response.data.errors` (including `lines.N.field`) appear under each input. Save → redirect to details. Layout follows `ReceiptCreateView.vue`. |
| `resources/js/views/SalesInvoiceShowView.vue` | Shows header, customer (snapshot if issued, live if draft), lines and stored totals, and issued by/at in the company time zone.<br>Draft + manage role: **Edit**, **Delete** (confirm), **Issue invoice** (Bootstrap confirm modal or `confirm()`, as used elsewhere).<br>Issued: **Download PDF** via `axios.get(url, { responseType: 'blob' })` → object URL → `<a download>`. A plain link would not carry the Bearer token or `X-Company-ID` header. |
| `resources/js/views/CustomersView.vue` | Table with search plus a create/edit modal (name, address, tax number, email, phone, active), following `BranchesAndCashBoxesView.vue`/`CurrenciesView.vue`. |

### Frontend — modify

| File | Change |
|---|---|
| `resources/js/router.js` | Add `/sales-invoices`, `/sales-invoices/create`, `/sales-invoices/:id`, `/sales-invoices/:id/edit`, `/customers`, all with `meta: { requiresAuth: true }`. Declare `create` before `:id`. |
| `resources/js/App.vue` | Add a sidebar "المبيعات" (Sales) group with "فواتير المبيعات" (Invoices) and "العملاء" (Customers) links, next to receipts/payments (~line 73). |
| `resources/js/stores/authStore.js` | Add a `currentRole` getter (`userCompanies.find(c => c.company.id === currentCompany?.id)?.role`) and `canManageSales`. This only hides buttons; the server enforces access. |

### Docs
- `README.md`: add Sales invoices and Customers to the feature list, if that section exists.

## 4. Test plan

Tests are PHPUnit, run with `php artisan test`, using in-memory SQLite. Setup follows `UserStatusTest`/`AccountingEngineTest`: inline `Currency`/`Company`/`CompanyUser` creation, `Sanctum::actingAs`, the `X-Company-ID` header, and `CompanyContextService::clear()` in `setUp`.

### `tests/Unit/SalesInvoiceCalculatorTest.php` (pure)
- Fractional quantity: `2.5 × 19.99` with 2 decimals → line subtotal `49.98` (half-up from 49.975).
- A discount reduces the taxable amount; the tax is computed on the discounted amount.
- Mixed rates (15 %, 5 %, 0 %) across lines: tax total equals the sum of rounded line taxes, and grand total = taxable + tax.
- Currency with 3 decimals (e.g. KWD), and with 0 decimals.
- Rounding of negative values is never reached, because validation forbids negatives. Assert the helper with a direct 0.005 → 0.01 case.

### `tests/Feature/SalesInvoiceTest.php`
- **CRUD:** an accountant creates a draft with 2 lines → 201. Stored totals equal the calculator output. Then show, update (replace lines; totals change), and delete → 404 afterwards.
- **Validation (field-level `errors` keys):**
  - `lines` empty → `errors.lines`
  - `lines.0.quantity` = 0 / -1 / "abc" / 5 decimal places
  - `lines.0.unit_price` = -1
  - `lines.0.discount_amount` > qty × price
  - `lines.0.tax_rate` = 101
  - `due_date` before `invoice_date`
  - `customer_id` from another company → `errors.customer_id`
  - inactive/unknown `currency_id`
- **Calculate endpoint** returns the same totals as a saved invoice for the same payload: fractional qty + discount + mixed rates. This is the form = details check.
- **Issue:**
  - Issuing sets `status=ISSUED`, `invoice_number=INV-2026-000001`, `issued_by=user`, and `issued_at` not null.
  - Issuing a second draft → `INV-2026-000002`.
  - Issuing the same invoice again → 200 with the same number, `issued_at` unchanged, and the sequence `last_number` not incremented.
  - Sequences are per company and per year: company B starts at 000001, and a 2027 invoice date starts at 000001.
- **Concurrency guard:** call `DocumentNumberingService::reserve` N times in separate transactions → N distinct numbers. Also assert that the `unique(company_id, invoice_number)` index rejects a manual duplicate insert. True parallel issuance can't be exercised on in-memory SQLite (see Risks).
- **Immutability:** after issue, `PUT` → 422 and `DELETE` → 422, with the DB unchanged. Directly calling `$invoice->update([...])` or `$invoice->lines()->first()->update([...])` or `->delete()` throws.
- **Snapshot:** issue the invoice, then change the customer name/address/tax number, the company name/tax number, and the currency symbol. `GET /sales-invoices/{id}` still returns the original values in the snapshot fields, and the totals are unchanged.
- **Authorization:**
  - A `VIEWER` can list/show/download but gets 403 on store/update/delete/issue/calculate.
  - A user from company B, sending company A's `X-Company-ID`, gets 403 from the middleware.
  - A user from company B, sending their own header and company A's invoice ID, gets 404 on show/update/delete/issue/pdf.
  - An unauthenticated request gets 401.
- **List:** `search` by number, customer name and customer reference; `status`/`customer_id`/date filters; `per page` 15 with `last_page` > 1 when 16 invoices exist; company B's invoices never appear.

### `tests/Feature/SalesInvoicePdfTest.php`
- `GET /sales-invoices/{id}/pdf` on an issued invoice → 200, `Content-Type: application/pdf`, body starts with `%PDF`, and the `Content-Disposition` filename contains the invoice number.
- The PDF endpoint on a draft → 422.
- **Multi-page:** an invoice with 120 lines yields `$mpdf->page > 1` (assert through the service, or by counting `/Type /Page` objects).
- **Snapshot in PDF:** after mutating the customer and company post-issue, `SalesInvoicePdfService::html($invoice)` contains the original customer and seller names and tax numbers and the stored grand total, and does not contain the new values.
- **Cross-company:** the PDF endpoint for another company's invoice → 404.

### `tests/Feature/CustomerTest.php`
- Create, update and list are scoped to the company. A `VIEWER` gets 403 on writes. Another company's customer → 404.

### Manual check
- `npm run build`, then in the browser: create a draft → see live totals → edit → issue → download the PDF. Check that Arabic text is shaped and RTL, and that a long invoice repeats its header row on page 2.
- Optional: in Docker/MySQL, fire two `issue` requests in parallel on different drafts (e.g. `xargs -P2 curl`) and confirm the numbers differ.

## 5. Risks and open questions

1. **Customers entity is new.** The issue assumes customers exist, but in this codebase they are only receivable accounts. The plan adds a small `customers` table and page. **Question:** should customers instead be modeled as `accounts` with extra billing fields, or should a customer be linked to an AR account now? The plan defers that link to the journal-entry ticket.
2. **Discount semantics.** The plan uses an absolute per-line discount amount. **Question:** do users expect a percentage? If so, swap to `discount_percent` (0–100) with the same calculator structure.
3. **Tax rates are free-entry per line** because no tax configuration exists. A tax-rates master can come later without changing stored lines.
4. **Permission model.** The plan uses role strings (`COMPANY_ADMIN`/`ADMIN`/`ACCOUNTANT` manage; `VIEWER`/`AUDITOR` read), since `roles.permissions` JSON is not enforced anywhere. **Question:** should issuing be restricted further, e.g. to admins only?
5. **Concurrency testing limits.** In-memory SQLite serializes writes, so the test can't reproduce a real race. Correctness relies on `lockForUpdate` on `document_sequences` (MySQL InnoDB row lock), `insertOrIgnore` for the first-row race, and the unique index as a backstop. `DB::transaction(..., attempts: 3)` absorbs deadlock retries.
6. **Existing `DocumentNumberingService::generate()` is racy** for JE/RV/PV. It is deliberately left unchanged to keep scope tight. Migrating those callers to `reserve()` should be a follow-up ticket.
7. **mPDF fonts and size.** mPDF ships large font files (vendor grows ~40 MB). Arabic relies on bundled fonts (e.g. XB Riyaz / DejaVu Sans). The temp dir must be writable: `storage/framework/cache/mpdf` is already `chown`ed in the Dockerfile.
8. **PDF regeneration vs storage.** The PDF is regenerated on demand from immutable snapshot data and is not stored. A change to the Blade template would alter the look, but not the content, of old invoices. **Question:** is byte-identical archival required? That is likely only for ZATCA, which is out of scope.
9. **Existing middleware quirk (not fixed here).** `SetCompanyContext` checks `$user->role !== 'SUPER_ADMIN'`, but `User` has no `role` attribute (`is_super_admin` is the field). So super admins without membership are blocked. Invoice tests should not rely on super-admin bypass through that path.
10. **No company-wide null-context guard.** If a user has no active membership and sends no header, `CompanyContextService::getCompanyId()` is null and `CompanyScope` becomes a no-op. The invoice controllers always add `where('company_id', $companyId)`, so the query matches nothing (404/empty). Keep that explicit filter, as `ReceiptController` does.
