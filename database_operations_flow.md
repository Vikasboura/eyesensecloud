# Database Operations Flow Diagram

This document provides visual flowcharts mapping user interactions to the underlying MySQL database tables within the EyeSense Cloud Portal.

---

## 1. User Login Flow

When a user attempts to log in via the portal login page:

```mermaid
sequenceDiagram
    autonumber
    actor User as Portal User
    participant Login as login.php
    participant DB as MySQL Database

    User->>Login: Submit Login (Email/Username) & Password
    
    rect rgb(240, 240, 255)
        note right of Login: 1. Scan Employees Table
        Login->>DB: SELECT * FROM employees WHERE employee_id = ? OR email = ?
        DB-->>Login: Match record(s) with password hash
    end

    rect rgb(240, 255, 240)
        note right of Login: 2. Scan Tenants Table (if no active employee or to find all profiles)
        Login->>DB: SELECT * FROM society_tenants WHERE portal_username = ? OR email = ?
        DB-->>Login: Match record(s) with password hash
    end

    alt Match Found & Verified
        Login->>DB: SELECT society_name FROM maintenance_settings WHERE client_id = ? (Resolve friendly names)
        DB-->>Login: Society Name
        
        Login->>DB: INSERT INTO portal_login_sessions (Record session activity)
        Login->>DB: INSERT INTO portal_audit_logs (Audit trail entry)
        Login->>DB: SELECT theme FROM user_preferences (Fetch user settings)
        DB-->>Login: Active theme

        Login-->>User: Redirect to Dashboard (dashboard.php / tenant_dashboard.php)
    else Invalid Credentials
        Login-->>User: Display "Invalid credentials" error
    end
```

---

## 2. Client Signup / Registration Flow

When a new Client (Society Subscriber) signup or registration is triggered:

```mermaid
sequenceDiagram
    autonumber
    actor Admin as System/INDSAC Admin
    participant Reg as admin_register_client.php
    participant DB as MySQL Database

    Admin->>Reg: Submit Registration Form (First Name, Last Name, Email, Password, Plan)
    
    Reg->>DB: SELECT client_id FROM clients WHERE email = ? (Check duplicate check)
    DB-->>Reg: Exist check result

    alt Email is Unique
        Reg->>DB: INSERT INTO clients (Store subscriber details & generated Client ID)
        Reg->>DB: INSERT INTO employees (Create subscriber's Super Admin account: ADMIN access level)
        
        opt Plan & Machine ID Provided
            Reg->>DB: SELECT * FROM plans WHERE plan_code = ? (Verify active plan details)
            DB-->>Reg: Plan duration & pricing
            Reg->>DB: INSERT INTO licenses (Generate sha256 license key & map duration)
        end
        
        Reg-->>Admin: Success Response (Client registered + Welcome Email sent)
    else Email Exists
        Reg-->>Admin: Return "Email already registered" error
    end
```

---

## 3. Tenant Addition Flow

When a new tenant is added to the system (by a member/self or an administrator):

```mermaid
sequenceDiagram
    autonumber
    actor User as Portal User / Admin
    participant TenantAPI as tenant_api.php
    participant DB as MySQL Database

    User->>TenantAPI: Submit Tenant Form (Name, Mobile, Govt ID, Flat #, Dates)
    
    TenantAPI->>DB: SELECT society_name, tenant_doc_required FROM maintenance_settings WHERE client_id = ?
    DB-->>TenantAPI: Configuration settings
    
    rect rgb(240, 255, 240)
        note right of TenantAPI: Write tenant record
        TenantAPI->>DB: INSERT INTO society_tenants (Insert portal credentials & document path)
        DB-->>TenantAPI: Tenant ID
    end

    TenantAPI-->>User: Redirect/Success JSON response
```

---

## 4. Super Admin Adding Society Member Flow

When a Society Super Admin registers a new society member (flat owner/resident):

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Society Super Admin
    participant MemAPI as maintenance_members_api.php
    participant DB as MySQL Database

    Admin->>MemAPI: Submit Member Details (Name, Flat Number, Email, Monthly Amount)
    
    DB->>DB: Start Transaction
    
    MemAPI->>DB: SELECT MAX(member_code) FROM society_members (Auto-increment logic)
    DB-->>MemAPI: Current maximum code
    
    rect rgb(240, 240, 255)
        note right of MemAPI: 1. Create Portal Login Employee Account
        MemAPI->>DB: INSERT INTO employees (role='MEMBER', access_level='L0')
    end

    rect rgb(240, 255, 240)
        note right of MemAPI: 2. Create Society Member Profile
        MemAPI->>DB: INSERT INTO society_members (Map member_code, monthly_amount, employee_id)
    end

    rect rgb(255, 240, 240)
        note right of MemAPI: 3. Set Permissions & Log Action
        MemAPI->>DB: INSERT IGNORE INTO portal_role_permissions (Ensure member has VIEW_MAINTENANCE)
        MemAPI->>DB: INSERT INTO portal_audit_logs (Audit log action)
    end

    DB->>DB: Commit Transaction
    
    opt Has Email
        Note over MemAPI, DB: Triggers sendNewMemberWelcome() to send credentials
    end
    
    MemAPI-->>Admin: Return success message
```
