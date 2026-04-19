# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**ERPSAAS** — A multi-tenant ERP/accounting platform built on Laravel 12 + Filament 3.2. Features double-entry accrual accounting, invoicing, billing, budgets, banking integration (Plaid), PDF generation, and multi-currency support.

## Common Commands

```bash
# Start development (server + queue + logs + vite in parallel)
composer run dev

# Run tests
composer run test
# or directly:
php84 artisan config:clear && ./vendor/bin/pest

# Run a single test file
./vendor/bin/pest tests/Feature/SomeTest.php

# Build frontend assets
npm run build

# Check Super Admin permissions across all companies
php84 check_permissions.php

# Lint PHP code
./vendor/bin/pint
```

## Architecture

### Filament-First UI
All admin UI is built as Filament resources, pages, and widgets — not traditional Laravel controllers/views. The main panel is company-scoped (multi-tenant via `filament-shield` with `tenant_model = Company`).

- `app/Filament/Company/` — All company-scoped resources (invoices, bills, accounts, clients, vendors, etc.)
- `app/Filament/User/` — User account pages
- `app/Filament/Pages/` — Custom pages (reports, settings, auth)
- `app/Filament/Clusters/` — Grouped resource clusters

### Multi-Tenancy
Companies are the tenant boundary. `setPermissionsTeamId($company->id)` must be called before any permission/role queries to scope them to the correct company. Roles have a `company_id` column.

### Accounting Core
- Double-entry: every financial event creates `journal_entries` linked to `transactions`
- `accounts` table holds the chart of accounts (type, subtype, with `inverse_cash_flow` flag)
- `documents` is the polymorphic base for invoices, bills, estimates, variation orders
- `adjustments` / `adjustmentables` handle discounts and taxes (polymorphic)

### Service Layer
Business logic lives in `app/Services/`, not controllers. Key services:
- `AccountService` — Chart of accounts operations
- `TransactionService` — Journal entry creation
- `ReportService` — Financial report generation (Trial Balance, Balance Sheet)
- `DocumentService` — Invoice/bill/estimate lifecycle
- `CurrencyService` — Exchange rate fetching and conversion

### Models Domain Structure
`app/Models/` is organized by domain:
- `Accounting/` — Account, Transaction, JournalEntry, Budget, Adjustment
- `Banking/` — BankAccount, ConnectedBankAccount, Institution
- `Common/` — Offering, OfferingCategory, Contact, Address
- `Core/` — Company, Department, User
- `Locale/` — Currency, Localization
- `Setting/` — CompanyProfile, DocumentDefault, CompanyDefault
- `Sales/` — Invoice, Estimate, RecurringInvoice, Contract, VariationOrder
- `Purchases/` — Bill, Vendor

### Permissions (Filament Shield)
- Role: `Super Admin` — must be explicitly synced to all permissions via `ShieldSeeder`
- `define_via_gate = false` means Super Admin is **not** auto-bypassed; it requires actual permission assignment
- Permission naming: `{prefix}_{domain}::{resource}` e.g. `view_any_sales::invoice`
- Page permissions: `page_PageName`
- Run `php artisan db:seed --class=ShieldSeeder` after adding new resources/pages to keep Super Admin in sync

### PDF Generation
Multiple drivers available: Snappy (wkhtmltopdf), `spatie/laravel-pdf`, Browsershot (Puppeteer). Document printing uses a dedicated route `/documents/{type}/{id}/print` handled by `DocumentPrintController`.

### Scheduled Jobs
Defined in `routes/console.php`:

Queue, cache, and session all use the **database** driver.

## Key Config Files

- `config/chart-of-accounts.php` — Default chart of accounts structure
- `config/filament-shield.php` — Permission system configuration
- `config/money.php` — Currency formatting
- `config/erp.php` — Custom ERP settings

## Testing

- Framework: Pest PHP
- Test database: `erpsaas_test` (configure in `.env`)
- Custom helpers in `tests/Helpers/` and `app/Testing/`
- Default test user seeded: `admin@erpsaas.com` / `password`

# Important Notes

- Use php84 when need to run php in console
- Try to avoid highly duplicated code, reuse the code
- Act as a Senior Software Architect specializing in strict MVC (Model-View-Controller) design pattern
- Filament Action should be treated as controller, so it should not contain any business logic
- Keep agent knowledge whenever necessary
- Check if the code work as expected after implemented it
- Ask question whenever there is any additional information needed