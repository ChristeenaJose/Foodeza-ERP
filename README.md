# Foodeza ERP — Desktop

Cross-platform **offline-first** ERP for Foodeza. Bookkeeping is a module inside the app.

## Stack

| Layer | Technology |
|-------|------------|
| Desktop shell | **Tauri 2** (Windows, macOS, Linux) |
| Frontend | **Vue 3** + TypeScript + **Tailwind CSS 4** |
| API | **Laravel 12** REST API |
| Database | **SQLite** (local, no cloud required) |
| Offline | Dexie queue + local API sidecar |

## Architecture

```
┌─────────────────────────────────────┐
│  Tauri Desktop (Vue 3 + Tailwind)   │
│  Offline queue (Dexie)              │
└──────────────┬──────────────────────┘
               │ REST http://127.0.0.1:17654/api
┌──────────────▼──────────────────────┐
│  Laravel 12 API (local sidecar)     │
│  SQLite database                    │
└─────────────────────────────────────┘
```

All data stays on your machine. No internet required.

## Requirements

- PHP 8.2+
- Composer
- Node.js 20+
- Rust (for Tauri builds) — [install](https://tauri.app/start/prerequisites/)

## Setup

See **[INSTALL.md](INSTALL.md)** for full installation on a new computer (Windows, macOS, Linux), including all prerequisites.

Quick start (after prerequisites are installed):

```bash
npm run setup
```

## Development

**Web only** (browser + API):
```bash
npm run dev
```
Open http://localhost:1420

**Desktop app** (Tauri — starts API sidecar automatically):
```bash
npm run dev:desktop
```

## Build installers

```bash
npm run build:desktop
```

Outputs platform packages in `desktop/src-tauri/target/release/bundle/`.

## Modules

Each module is separate inside this ERP:

- **Bookkeeping** — income, expenses, invoices, payments, bank, and reports
- **Bookings** — catering, food truck, lunch, and meal box bookings
- **Inventory** — ingredients, packaging, and stock levels
- **Menu management** — products, menus, packages, pricing, and availability
- **Google Form orders** — event plans, CSV/XLSX form uploads, and WhatsApp confirmations

## Foodeza services

Catering · Food Truck · Corporate Lunch · Meal Box Delivery

## Configuration

`api/config/foodeza.php` — VAT rate, services, payment methods, currency.

Set `vat_rate` to `0` for small-business (KOR) rule.

## Project layout

The ERP is the framework. Each business area is a module with the same shape: models, HTTP, services, routes, and screens.

```
modules/
  bookkeeping/          income, expenses, invoices, payments, bank, reports
  bookings/             catering and event bookings
  inventory/            stock and movements
  menu/                 products, menus, packages, pricing
  google-form-orders/   orders from the Google Form
api/                    Laravel framework, shared customers, config, database
desktop/                Vue and Tauri shell, shared layout and API client
```

Inside a module:

```
modules/<name>/
  Models/
  Http/
  Services/             when the module has them
  routes.php
  frontend/             screens for that module
```

## Legacy

The previous plain-PHP web app has been replaced by this desktop architecture.
