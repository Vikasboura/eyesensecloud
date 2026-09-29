# 🏨 Enterprise Core PHP Hostel Management System (Super Admin Portal)

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL PDO](https://img.shields.io/badge/Database-MySQL%20PDO-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap 5](https://img.shields.io/badge/UI%20Framework-Bootstrap%205.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Security](https://img.shields.io/badge/Security-CSRF%20%7C%20PDO%20%7C%20Bcrypt-22c55e?style=for-the-badge&logo=springsecurity&logoColor=white)](#-security--best-practices)
[![License](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)](#)

A state-of-the-art, production-ready **Hostel Management System Super Admin Control Panel** built using **Core PHP (PDO)**, **MySQL**, **Bootstrap 5**, **DataTables**, **SweetAlert2**, and **jQuery AJAX**. 

Designed with modern UI/UX aesthetics (glassmorphism, clean badges, smooth hover animations, and dark/light accents), this portal provides complete administrative oversight across campus hostel blocks, room inventory, student admissions, resident wardens, executive occupancy analytics, and security settings.

---

## ✨ Key Modules & Features

### 🏢 1. Hostel Block Management (`hostel/`)
- **Multi-Type Block Registry**: Register **Boys**, **Girls**, and **Co-Ed** hostel blocks.
- **Smart Auto-Code Generation**: Automatically generates block codes (`BH001`, `GH001`, `CH001`) based on the selected hostel type via dynamic AJAX.
- **Image Upload & Quota Tracking**: Upload block images (`uploads/hostel/`), track total stated bed capacity vs. actual room allocation, and toggle operational status (`Active` / `Inactive`).

### 🛏️ 2. Room Inventory & Allocation (`rooms/`)
- **Room Category Control**: Manage `Single`, `Double`, `Triple`, and `Dormitory` occupancy rooms with synchronized bed capacities.
- **Financial Rent Setup**: Assign exact monthly rental fees (`₹`) per room type for automatic revenue calculation.
- **Real-Time Availability Tracking**: Toggle and filter room statuses (`Available`, `Occupied`, `Under Maintenance`).

### 🎓 3. Student Resident Management (`students/`)
- **AJAX Dynamic Room Loading**: When admitting a new student, selecting a **Hostel Block** automatically queries and loads available rooms (`rooms/room-action.php?action=get_rooms`) via asynchronous JSON without refreshing the page!
- **Auto-Admission Code**: Automatically generates sequential student admission IDs (`STU001`, `STU002`).
- **Emergency & Guardian Records**: Store complete parent/guardian full names and 10-digit verified mobile numbers (`+91`) for emergency communication.

### 👮 4. Resident Wardens Directory (`wardens/`)
- **Staff Assignment**: Assign resident wardens and supervisory staff to specific hostel blocks.
- **One-Click Communication**: Clickable `mailto:` and `tel:+91` direct communication triggers inside the table.
- **Leave & Status Management**: Monitor and toggle staff availability (`Active`, `On Leave`, `Inactive`).

### 📊 5. Executive Analytics & Reports (`reports/`)
- **Live Occupancy Dashboard**: Visual progress bars showing bed occupancy percentage per block (`<60% Green`, `60-90% Yellow`, `>90% Red`).
- **Revenue Yield Tracking**: Automatically calculates **Actual Monthly Yield** (rent collected from active residents) vs. **Max Potential Yield** (100% capacity yield).
- **Export & Print**: Clean `window.print()` media query formatting for instant executive PDF/paper summary export.

### ⚙️ 6. System Settings & Security (`settings/`)
- **Super Admin Profile Security**: Update administrator full name and email address with live duplicate checking.
- **Bcrypt Password Upgrades**: Change login password securely using PHP's native `password_verify` + `password_hash` (`PASSWORD_BCRYPT`, cost `12`).
- **Global Preferences**: Customize the portal application title (`APP_NAME`), default currency symbol (`₹`), official alert email, and system maintenance mode (`Normal` vs `Maintenance Active`).

---

## 🏗️ Project Architecture & Directory Structure

```text
hostel-admin/
│
├── assets/                  # Static UI Assets
│   ├── css/                 # Custom styling (glassmorphism, tables, variables)
│   ├── js/                  # Client-side validation & UI triggers
│   └── images/              # Logos and placeholders
│
├── config/                  # Core Application & Database Config
│   ├── config.php           # Global constants (BASE_URL, APP_NAME, sanitization helpers)
│   └── db.php               # PDO Database Connection Singleton (getDB())
│
├── includes/                # Layout Templates & Security Middleware
│   ├── auth.php             # Session & Role verification (Super Admin protection + db check)
│   ├── header.php           # HTML head, Bootstrap 5, FontAwesome, DataTables CSS
│   ├── navbar.php           # Top glassmorphic navbar with user avatar & logout
│   ├── sidebar.php          # Collapsible sidebar with active link state detection
│   └── footer.php           # Footer, DataTables JS, SweetAlert2, CSRF token helper
│
├── hostel/                  # Hostel Block Operations
│   ├── hostel-list.php      # DataTables listing of hostel blocks
│   ├── add-hostel.php       # New hostel registration form + AJAX auto-code
│   ├── edit-hostel.php      # Pre-populated block modification page
│   ├── hostel-view.php      # Detailed block card & allocated rooms summary
│   └── hostel-action.php    # CRUD controller handling uploads & database execution
│
├── rooms/                   # Room Inventory Operations
│   ├── room-list.php        # Room directory with hostel block filter
│   ├── add-room.php         # Room creation with synchronized capacity selection
│   ├── edit-room.php        # Room fee and status modification
│   └── room-action.php      # Controller + AJAX JSON endpoint (action=get_rooms)
│
├── students/                # Student Resident Operations
│   ├── student-list.php     # Directory of admitted students with status badges
│   ├── add-student.php      # Admission form featuring AJAX dynamic room loader
│   ├── edit-student.php     # Student profile and room relocation form
│   ├── student-view.php     # Complete student profile & guardian emergency card
│   └── student-action.php   # Controller handling admissions and status cycling
│
├── wardens/                 # Resident Staff & Warden Operations
│   ├── warden-list.php      # Directory of assigned wardens with contact badges
│   ├── add-warden.php       # Staff registration and block assignment form
│   ├── edit-warden.php      # Staff modification form
│   └── warden-action.php    # Controller handling warden assignments and deletions
│
├── reports/                 # Executive Analytics
│   └── index.php            # Visual occupancy charts, revenue estimates & print export
│
├── settings/                # System Configuration & Security Profile
│   ├── index.php            # Multi-tab UI (Super Admin Profile vs. General Settings)
│   └── settings-action.php  # Bcrypt password hasher and system preference saver
│
├── sql/                     # Database Schema & Seed Migrations
│   └── database.sql         # Full SQL schema for all tables + verified admin seed
│
├── uploads/hostel/          # Uploaded hostel block images
│
├── dashboard.php            # Main Super Admin dashboard with KPIs & quick actions
├── login.php                # Secure login portal with bcrypt verification & flash alerts
├── logout.php               # Session destruction & clean sign-out
└── index.php                # Root redirect to login or dashboard
```

---

## 🚀 Quick Start Guide

### 1. Prerequisites
- **Web Server**: Apache / Nginx (via **XAMPP**, **WAMP**, **Laragon**, or **MAMP**).
- **PHP Version**: PHP 8.0 or higher (with `pdo_mysql` extension enabled).
- **Database**: MySQL 5.7+ or MariaDB 10.3+.

### 2. Database Installation & Seeding
1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) or your preferred MySQL client.
2. Create a new database named:
   ```sql
   CREATE DATABASE IF NOT EXISTS `hostel_admin_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the SQL migration file located at:
   `hostel-admin/sql/database.sql`
   *(Or copy the contents and run them directly in the SQL query window).*

### 3. Server Setup
- **If using XAMPP/WAMP**: Place the project folder inside your `htdocs` or `www` directory:
  `C:\xampp\htdocs\super admin panel anti IDE\`
- **Or run via PHP Built-in Server** (from your terminal):
  ```bash
  cd "super admin panel anti IDE"
  php -S localhost:8000
  ```

### 4. Default Super Admin Credentials
Once your server is running, navigate to `login.php` or `index.php` in your browser:
- **URL**: `http://localhost:8000/hostel-admin/login.php` *(or `http://localhost/super admin panel anti IDE/hostel-admin/login.php`)*
- **Login Email:** `admin@hostel.com`
- **Login Password:** `admin123`

---

## 🔒 Security & Best Practices

1. **CSRF Protection (`csrf_token()`)**: Every form submission (`POST`) across all modules (`add`, `edit`, `delete`, `toggle_status`) generates and verifies a cryptographically secure token stored in `$_SESSION['csrf_token']` to prevent Cross-Site Request Forgery.
2. **Prepared Statements (PDO)**: All SQL queries utilize PDO prepared queries with bound parameters (`?`), providing 100% immunity against SQL Injection attacks.
3. **Bcrypt Password Hashing**: Passwords are never stored in plain text. The system uses PHP's native `password_hash()` and `password_verify()` with a work factor cost of `12`.
4. **Authentication Middleware (`auth.php`)**: Every protected controller and view requires `includes/auth.php` at the very top, automatically verifying session validity (`admin_logged_in`) and exact role authorization (`Super Admin`) before executing code or accessing database connections.
5. **Output Sanitization (`htmlspecialchars`)**: All user inputs displayed on screen are properly escaped via custom `sanitize()` helpers to prevent Cross-Site Scripting (XSS).

---

## 🤝 Contributing & Extension Notes
This project is built using **Core PHP** so that any company or development team can easily adapt, customize, or later migrate the logic to frameworks like **Laravel** or **Symfony** without complex dependencies or proprietary wrappers.

- To add new database tables or settings, simply update `sql/database.sql` and add your module folder inside `hostel-admin/`.
- Ensure new pages require `auth.php` at the top and include `header.php`, `sidebar.php`, and `navbar.php` for seamless visual integration.

---

### 🌟 Built with Precision & Excellence
*Designed to deliver a stunning, responsive, and secure hostel management experience.*
