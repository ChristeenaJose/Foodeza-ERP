# Foodeza ERP — Installation Guide

This guide explains how to install Foodeza ERP on a **new computer** (Windows, macOS, or Linux).

There are two ways to install:

| Method | Who it is for | Internet needed? |
|--------|---------------|------------------|
| **A. Pre-built installer** | End users (accountant, office staff) | Only to download the installer |
| **B. Install from source** | Developers or IT setting up from Git | Yes, during setup |

After installation, the app works **offline**. All data is stored locally in SQLite.

---

## What the app needs to run

Foodeza ERP is a **desktop app** made of two parts:

1. **Desktop UI** — Vue 3 inside Tauri (the window you see)
2. **Local API** — Laravel 12 on your computer, talking to SQLite

The desktop app starts the API automatically on:

```
http://127.0.0.1:17654/api
```

No cloud server. No subscription. Data stays on the machine.

---

## System requirements (all platforms)

### Minimum hardware

| Item | Requirement |
|------|-------------|
| RAM | 4 GB (8 GB recommended) |
| Disk space | 500 MB free (more for receipts/invoices) |
| Display | 1024×700 or larger |
| Internet | Not required after install |

### Software (for running from source or dev builds)

| Software | Version | Required? | Why |
|----------|---------|-----------|-----|
| **PHP** | 8.2 or newer | **Yes** | Laravel API |
| **Composer** | 2.x | **Yes** | PHP dependencies |
| **Node.js** | 20 LTS or newer | **Yes** (dev/build) | Vue frontend & Tauri |
| **npm** | 10+ (comes with Node) | **Yes** (dev/build) | JavaScript packages |
| **Rust** | Latest stable | **Yes** (dev/build desktop) | Tauri desktop shell |
| **Git** | Any recent version | Recommended | Clone the repository |

### PHP extensions (required by Laravel)

PHP must include these extensions (most installers include them by default):

- `ctype`
- `curl`
- `dom`
- `fileinfo`
- `filter`
- `hash`
- `mbstring`
- `openssl`
- `pdo`
- `pdo_sqlite` ← **important** (SQLite database)
- `session`
- `tokenizer`
- `xml`

Check on any computer:

```bash
php -m
```

You should see `pdo_sqlite` in the list.

### Ports used (must be free)

| Port | Service |
|------|---------|
| `17654` | Laravel API (local only) |
| `1420` | Vue dev server (development only) |

If port `17654` is already in use, change it in:

- `desktop/src/config.ts` → `API_PORT`
- `desktop/src-tauri/src/lib.rs` → `--port=17654`
- `package.json` → `dev:api` script

---

## Method A — Install from pre-built installer (easiest)

Use this for staff who only need to **use** the app, not develop it.

### What you still need on the computer

Even with a built `.exe` / `.dmg` / `.AppImage`, the app starts a **local PHP API**. The target machine must have:

| Requirement | Windows | macOS | Linux |
|-------------|---------|-------|-------|
| PHP 8.2+ on PATH | **Yes** | **Yes** | **Yes** |
| PHP SQLite extension | **Yes** | **Yes** | **Yes** |
| Node.js / Rust | No | No | No |

> **Important:** The current Foodeza build expects `php` to be available in the system PATH. Install PHP before running the desktop app.

### Steps

1. **Install PHP 8.2+** on the target computer (see platform sections below).
2. Copy or download the installer from `desktop/src-tauri/target/release/bundle/`:
   - **Windows:** `.msi` or `.exe`
   - **macOS:** `.dmg` or `.app`
   - **Linux:** `.deb`, `.AppImage`, or `.rpm`
3. Run the installer and open **Foodeza ERP**.
4. On first launch, the app creates the SQLite database automatically.

### Where data is stored

| Data | Location |
|------|----------|
| SQLite database | `api/database/database.sqlite` (next to the app install, or project folder) |
| Receipts & invoices | `api/storage/app/receipts/` and `api/storage/app/invoices/` |
| Offline request queue | Browser IndexedDB (inside the desktop app) |

Back up `api/database/` and `api/storage/app/` regularly.

---

## Method B — Install from source (developers / IT)

Use this when cloning the project from Git onto a new machine.

### 1. Get the code

```bash
git clone <your-repo-url> erp
cd erp
```

Or copy the project folder (USB, zip, etc.).

### 2. Install platform prerequisites

Follow the section for your operating system below.

### 3. Run project setup

From the project root:

```bash
npm install          # root scripts (concurrently)
npm run setup        # composer install, migrate DB, npm install desktop
```

This will:

- Install Laravel PHP packages
- Create the SQLite database
- Seed Foodeza categories and services
- Install Vue + Tauri frontend packages

The engine is SQLite. `npm run setup` migrates, then seeds categories. For an empty database with no rows, run `cd api && php artisan migrate`. That creates `api/database/database.sqlite`. `database/schema.sql` is the older bookkeeping schema only. `database/migrations/002_bookkeeping.sql` upgrades that older database and is not a fresh setup.

### 4. Verify installation

```bash
# Terminal 1 — start API
npm run dev:api

# Terminal 2 — check health (new terminal)
curl http://127.0.0.1:17654/api/health
```

Expected response:

```json
{"status":"ok","offline":true}
```

### 5. Run the app

**Option 1 — Browser (development):**

```bash
npm run dev
```

Open: http://localhost:1420

**Option 2 — Desktop window (Tauri):**

```bash
npm run dev:desktop
```

### 6. Build installers (optional)

```bash
npm run build:desktop
```

Output:

```
desktop/src-tauri/target/release/bundle/
```

---

## Platform-specific prerequisites

### macOS (Apple Silicon or Intel)

#### Required for development / building

1. **Xcode Command Line Tools**
   ```bash
   xcode-select --install
   ```

2. **Homebrew** (recommended) — https://brew.sh
   ```bash
   /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
   ```

3. **PHP 8.2+**
   ```bash
   brew install php@8.2
   brew link php@8.2 --force --overwrite
   php -v
   ```

4. **Composer**
   ```bash
   brew install composer
   composer --version
   ```

5. **Node.js 20+**
   ```bash
   brew install node@20
   node -v
   npm -v
   ```

6. **Rust** (for Tauri desktop builds)
   ```bash
   curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
   source "$HOME/.cargo/env"
   rustc --version
   ```

7. **Verify Tauri environment**
   ```bash
   cd desktop
   npx tauri info
   ```

> **Note:** Full Xcode app is optional for local macOS builds. Command Line Tools are enough for development.

---

### Windows 10 / 11

#### Required

1. **Microsoft Visual Studio C++ Build Tools**  
   Download: https://visualstudio.microsoft.com/visual-cpp-build-tools/  
   Install workload: **"Desktop development with C++"**

2. **WebView2**  
   Usually pre-installed on Windows 11.  
   Windows 10: https://developer.microsoft.com/en-us/microsoft-edge/webview2/

3. **PHP 8.2+**  
   Download: https://windows.php.net/download/  
   - Choose **VS16 x64 Non Thread Safe** zip  
   - Extract to `C:\php`  
   - Add `C:\php` to system **PATH**  
   - Copy `php.ini-development` → `php.ini`  
   - Enable in `php.ini`:
     ```ini
     extension_dir = "ext"
     extension=curl
     extension=fileinfo
     extension=mbstring
     extension=openssl
     extension=pdo_sqlite
     extension=sqlite3
     ```

4. **Composer**  
   https://getcomposer.org/download/

5. **Node.js 20 LTS**  
   https://nodejs.org/ (LTS installer)

6. **Rust** (for Tauri)
   ```powershell
   # Download and run rustup-init.exe from https://rustup.rs/
   rustc --version
   ```

7. **Git** (recommended)  
   https://git-scm.com/download/win

#### Windows setup commands

```powershell
cd erp
npm install
npm run setup
npm run dev:desktop
```

---

### Linux (Ubuntu / Debian)

#### Required packages

```bash
sudo apt update
sudo apt install -y \
  build-essential \
  curl \
  wget \
  file \
  libssl-dev \
  libgtk-3-dev \
  libayatana-appindicator3-dev \
  librsvg2-dev \
  libwebkit2gtk-4.1-dev \
  php8.2-cli \
  php8.2-sqlite3 \
  php8.2-mbstring \
  php8.2-xml \
  php8.2-curl \
  php8.2-zip \
  php8.2-bcmath \
  unzip
```

#### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

#### Node.js 20

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
```

#### Rust

```bash
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source "$HOME/.cargo/env"
rustc --version
```

#### Linux setup commands

```bash
cd erp
npm install
npm run setup
npm run dev:desktop
```

### Linux (Fedora)

```bash
sudo dnf install \
  webkit2gtk4.1-devel \
  openssl-devel \
  curl \
  wget \
  file \
  libappindicator-gtk3-devel \
  librsvg2-devel \
  gcc \
  gcc-c++ \
  make \
  php-cli \
  php-sqlite3 \
  php-mbstring \
  php-xml \
  php-curl \
  php-zip

# Then install Composer, Node 20, and Rust as above
```

---

## Quick checklist (new computer)

Copy this checklist when setting up a machine:

```
[ ] PHP 8.2+ installed (`php -v`)
[ ] pdo_sqlite extension enabled (`php -m | grep pdo_sqlite`)
[ ] Composer installed (`composer --version`)
[ ] Node.js 20+ installed (`node -v`) — dev/build only
[ ] npm installed (`npm -v`) — dev/build only
[ ] Rust installed (`rustc --version`) — dev/build only
[ ] Platform build tools installed (see OS section)
[ ] Git installed (optional)
[ ] Project cloned or copied
[ ] `npm install` run in project root
[ ] `npm run setup` completed without errors
[ ] API health check returns {"status":"ok"}
[ ] App opens via `npm run dev` or `npm run dev:desktop`
[ ] Port 17654 is free
```

---

## Configuration after install

Edit `api/config/foodeza.php`:

| Setting | Description | Example |
|---------|-------------|---------|
| `company` | Business name | `Foodeza` |
| `currency` | Currency code | `EUR` |
| `currency_symbol` | Display symbol | `€` |
| `timezone` | Local timezone | `Europe/Amsterdam` |
| `vat_rate` | VAT rate (`0` = KOR/small business) | `0.21` |
| `services` | Income service lines | Catering, Food Truck, etc. |
| `payment_methods` | Payment types | Cash, Bank Transfer, Card, etc. |

After changing config, restart the API.

---

## Troubleshooting

### `php: command not found`

PHP is not installed or not on PATH. Install PHP and restart the terminal.

### `could not find driver` / SQLite errors

Enable the SQLite extension in `php.ini`:

```ini
extension=pdo_sqlite
extension=sqlite3
```

### `Port 17654 already in use`

Another program (or a stuck API process) is using the port.

```bash
# macOS / Linux
lsof -i :17654
kill <PID>

# Windows
netstat -ano | findstr 17654
taskkill /PID <PID> /F
```

### Desktop app opens but shows no data / API offline

1. Confirm PHP works: `php -v`
2. Start API manually: `npm run dev:api`
3. Open http://127.0.0.1:17654/api/health in a browser
4. Check the sidebar status: **Local API online** vs **Offline — queue active**

### `rustc: command not found` (Tauri build)

Install Rust:

```bash
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source "$HOME/.cargo/env"
```

Restart the terminal.

### Tauri build fails on Linux

Install WebKit GTK dev packages:

```bash
sudo apt install libwebkit2gtk-4.1-dev
```

### Composer memory errors

```bash
COMPOSER_MEMORY_LIMIT=-1 composer install
```

### Permission errors on `api/storage`

```bash
cd api
chmod -R 775 storage bootstrap/cache
```

---

## Network & security notes

- The API listens only on **127.0.0.1** (localhost). It is not exposed to the internet.
- No account login is required in the current version (single-user local app).
- Receipts and invoices are stored on disk under `api/storage/app/`.
- Back up `api/database/database.sqlite` before OS upgrades or machine migration.

---

## Moving to another computer

1. Install prerequisites on the new machine (PHP is mandatory).
2. Copy the entire project folder **or** install from Git.
3. Copy these if you want existing data:
   - `api/database/database.sqlite`
   - `api/storage/app/receipts/`
   - `api/storage/app/invoices/`
4. Run `npm run setup` on the new machine (if from source).
5. Start the app.

---

## Support commands reference

| Task | Command |
|------|---------|
| Full setup | `npm run setup` |
| Dev (browser) | `npm run dev` |
| Dev (desktop) | `npm run dev:desktop` |
| API only | `npm run dev:api` |
| Build installers | `npm run build:desktop` |
| Migrate database | `cd api && php artisan migrate` |
| Reset & seed categories | `cd api && php artisan db:seed` |
| Check Tauri env | `cd desktop && npx tauri info` |

---

## Official documentation links

- Tauri prerequisites: https://tauri.app/start/prerequisites/
- Laravel requirements: https://laravel.com/docs/12/deployment#server-requirements
- PHP downloads: https://www.php.net/downloads
- Composer: https://getcomposer.org/
- Node.js: https://nodejs.org/
- Rust: https://rustup.rs/

---

**Foodeza ERP** — offline-first desktop ERP for Catering, Food Truck, Corporate Lunch, and Meal Box Delivery. Bookkeeping is included as a module.
