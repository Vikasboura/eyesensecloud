# INDSAC License & Registration Server API Documentation

This directory contains the PHP-based API endpoints that run on the central **INDSAC Server**. These APIs handle the registration of new EyeSense client installations, generation of unique client IDs, distribution of public RSA keys, management of dynamic licensing plans, and issuance of cryptographically signed licenses.

---

## 🏗️ Setup & Installation

### Prerequisites
- A web server running PHP 7.4+ or 8.x (Apache, Nginx, or PHP Built-in Server for testing).
- MySQL or MariaDB Database Server.
- PHP Extensions: `pdo_mysql`, `openssl`, `json`.

### Database Configuration
The database connection expects standard environment variables. You can configure them via your web server (e.g., Apache SetEnv), or by creating a `.env` file in the parent folder (one level above this `api/` directory).

**.env Example:**
```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=eyesense_licenses
DB_USER=root
DB_PASSWORD=your_secure_password
LICENSE_API_SECRET=EYESENSE-INDSAC-2026-SECRET-KEY
```

### Initializing the Server
The APIs are completely self-initializing.
1. Run the server (e.g., `php -S localhost:8080 -t api`).
2. Make your first API call to `register_client.php` or `issue_license.php`.
3. The server will automatically:
   - Connect to MySQL and create the `eyesense_licenses` database (if it doesn't exist).
   - Create all necessary tables: `licenses`, `clients`, and `plans`.
   - Seed the default pricing plans into the `plans` table.
   - Generate a secure 4096-bit RSA Key Pair (`private_key.pem` and `public_key.pem`) for cryptographic license signing.

---

## 🛠️ API Endpoints

All endpoints accept and return `application/json` unless otherwise specified.

### 1. Register Client (`POST /register_client.php`)
Called by the EyeSense local `setup.py` script bounds the deployment to a registered INDSAC client.

**Request Body:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john.doe@company.com",
  "mobile": "+91XXXXXXXXXX",
  "sa_username": "SA001",
  "machine_id": "sha256-hash-of-local-hardware"
}
```

**Response (Success):**
```json
{
  "success": true,
  "client_id": "IND-2026-V8K3P"
}
```
*Note: If the email is already registered, the API is idempotent and will return the existing `client_id`.*

---

### 2. Fetch Active Plans (`GET /get_plans.php`)
Returns all globally active pricing plans available for EyeSense deployments.

**Request:** `GET /get_plans.php`

**Response:**
```json
{
  "success": true,
  "plans": [
    {
      "plan_code": "TRIAL",
      "name": "Free Trial",
      "days": 14,
      "price": "0.00",
      "camera_limit": 0
    },
    {
      "plan_code": "PRO_1M",
      "name": "Pro Monthly",
      "days": 30,
      "price": "2999.00",
      "camera_limit": 0
    }
  ]
}
```

---

### 3. Issue License (`POST /issue_license.php`)
Generates a cryptographically signed license tied to the specific hardware's machine ID.
**Security:** Requires an HMAC SHA-256 token generated using the `LICENSE_API_SECRET` to prevent direct unverified API calls.

**Request Body:**
```json
{
  "machine_id": "sha256-hash",
  "plan": "PRO_1M",
  "auto_renew": false,
  "sa_name": "John Doe",
  "sa_email": "john.doe@company.com",
  "sa_phone": "+91XXXXXXXXXX",
  "payment_id": "PAY-123456789",
  "token": "hmac_sha256_hash_here"
}
```

**Response:**
Returns the Base64-encoded JWT-style license key alongside the server's public key (to facilitate auto-downloading of the key to the client).
```json
{
  "success": true,
  "license_key": "eyJtYWNo...PAYLOAD.SIGNATURE",
  "public_key_pem": "LS0tLS1C...BASE64",
  "plan": "PRO_1M",
  "start_date": "2026-03-17",
  "expiry_date": "2026-04-16",
  "machine_id": "ab12cd34ef...",
  "db_status": "recorded"
}
```

---

### 4. Get Public Key (`GET /get_public_key.php`)
A simple utility endpoint that returns the INDSAC Server's public RSA key. Clients can fetch this to stay updated if keys ever rotate.

**Response:**
```json
{
  "success": true,
  "public_key": "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqhkiG9w0B...\n-----END PUBLIC KEY-----"
}
```

---

### 5. Verify License (Server-side) (`POST /verify_license.php`)
Validates an existing license key against the server's private/public keys and database records to ensure it hasn't been revoked.

**Request Body:**
```json
{
  "license_key": "eyJtYWNo..."
}
```

---

### 6. Payment Simulator (`POST /payment_simulator.php`)
A mock frontend and handler to simulate real-world payment flows (Razorpay, Stripe, etc.) during testing. Upon successful 'mock' payment, it generates the secure HMAC token and proxies the request to `issue_license.php`.

---

## 🔒 Admin Dashboard (`admin_dashboard.php`)

A protected, dark-themed UI for INDSAC staff to manage the centralized system securely from a browser.

- **URL:** `http://localhost:8080/admin_dashboard.php`
- **Password:** `Indsac#1914`

### Features:
1. **Stat Overview**: View total registered clients, active plans, and active licenses.
2. **Plan Management**: Create new custom plans, edit existing ones (name, price, duration, camera limits), and toggle active/disabled states. Changes instantly reflect across all local EyeSense installations via `get_plans.php`.
3. **Client Directory**: Browse a paginated list of all registered clients along with their `IND-YYYY-XXXXX` Client IDs, emails, and registration dates.

---

## 🗄️ Database Schema Summary

The API manages three core tables in `eyesense_licenses`:

1. **`clients`**: Stores the SA details linked to their generated memorable `client_id`.
2. **`plans`**: A dynamic table holding subscription durations, prices, and config limits.
3. **`licenses`**: An immutable ledger of all issued licenses, mapping `machine_id` + `payment_id` to its cryptographic signature hash. Includes `status` tracking (active, superseded, expired).

---

## ☁️ Cloud Backup & Web Portal

The INDSAC server also acts as the centralized cloud backup repository for all connected EyeSense clients. It dynamically ingests local database records and base64 media payloads, providing a mirrored web-based portal for staff to access their security data remotely.

### Architecture

1. **`backup_agent.py` (Local Client)**
   - Runs continuously in the background on the local EyeSense hardware.
   - Monitors 23 SQL tables (e.g., `face_logs`, `video_logs`, `system_settings`) using a **three-tier sync strategy**:
     - **Numeric PK tables** (`id`-based): Incremental sync tracking the last-synced `id` value. Each cycle fetches rows with `id > last_sync_id`.
     - **Timestamp tables** (e.g. `system_settings`): Tracks `updated_at` to send only recently changed rows.
     - **Tiny static tables** (e.g. `roles`): Full-table sync every cycle (no pagination) since these tables contain only a handful of rows.
   - Automatically serializes the data, converts local media files into Base64 strings, and securely injects the authorized `IND-YYYY-XXXXX` `client_id` before transmitting JSON payloads to the cloud.

2. **`backup_receive.php` (Cloud Ingestion)**
   - Receives encrypted JSON payloads from the local backup agent.
   - Authenticates the `X-Client-Id` and dynamically parses the incoming JSON columns.
   - Executes `INSERT ON DUPLICATE KEY UPDATE` securely across all 23 mirrored database tables on the Indsac server.

3. **`/portal/` Directory (Web Dashboard)**
   - A fully independent, Tailwind-styled web dashboard accessible at `/portal/login.php`.
   - **Authentication:** Users log in using their synced EyeSense credentials (from the `employees` table) rather than separate portal accounts.
   - **Dynamic Roles:** SuperAdmins can intuitively assign granular access permissions to customized string roles (from the `roles` table) via the `manage_roles.php` dashboard.
   - **Live Gallery:** Reconstructs base64 payloads to seamlessly stream images and videos (`gallery.php`).

4. **Data Export Tools**
   - **`backup_download.php` & `backup_export.php`**: Allows SuperAdmins to generate full `.sql` database dumps of their entire cloud registry for local disaster recovery (`INSERT IGNORE`).
   - **`media_export.php`**: A robust, zero-dependency zip encoder that extracts Base64 imagery and video chunks from the database, converts them back to raw binaries (`.jpg`, `.mp4`), and compiles them into a downloadable `.zip` archive. Falls back to OS-level `Powershell` or `zip` if PHP's ZipArchive extension is disabled.
