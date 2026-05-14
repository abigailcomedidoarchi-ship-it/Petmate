# 🐾 PetMate 

A platform built with **PHP**, **MySQL**, and **vanilla JavaScript**. PetMate streamlines the entire clinical workflow — from pet registration and visit requests through assessment, treatment, monitoring, discharge, and billing — across multiple staff roles and a pet-owner self-service portal, connect pet owners with trusted pet sitters while providing an organized platform for managing pet profiles, bookings, schedules, and pet care services.


# Problem Statement
- Insufficient number of structured pet-care training workshops available for aspiring pet sitters.
- Inconsistent regulatory standards for professional pet-sitting services.
- Inconsistent quality of customer experiences.

# Objectives
- General Objective: Conducted structured pet-care workshops for aspiring pet sitters at least twice a month. 
- Specific Objective 1: Consistent regulatory standards for professional pet-sitting services achieving at least 90% compliance across registered providers
- Specific Objective 2: Consistent high-quality customer experiences achieving at least 90% positive satisfaction ratings

# Target Users
- Aspiring pet sitters
- Registered pet sitters
- Pet owners
- Veterinary clinic staff

---

## 📑 Table of Contents

- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [System Architecture](#-system-architecture)
- [User Roles](#-user-roles)
- [Clinical Workflow](#-clinical-workflow)
- [Project Structure](#-project-structure)
- [Prerequisites](#-prerequisites)
- [Installation & Setup](#-installation--setup)
- [Environment Variables](#-environment-variables)
- [Database Setup](#-database-setup)
- [Running the Application](#-running-the-application)
- [Security Features](#-security-features)
- [Payment Integration](#-payment-integration)
- [License](#-license)

---

## ✨ Features

| Category | Highlights |
|---|---|
| **Authentication** | Email/password login with bcrypt hashing, Google OAuth 2.0 with role selection, OTP email verification |
| **Role-Based Access** | Centralized RBAC middleware with per-role permissions and auto-redirect |
| **Pet Management** | Register pets, maintain species/breed/weight/age profiles, view medical history |
| **Visit Lifecycle** | Submit visit requests → CSR validation → exam room prep → assessment → treatment → monitoring → discharge → billing |
| **Clinical Assessments** | Vital signs capture (temp, HR, RR, weight), lab equipment selection (CBC, chemistry, microscopy, test kits), structured result recording |
| **Treatment Plans** | Vet technician creates plans with medications, procedures, and surgeries; pet owner consent workflow; vet assistant treatment execution |
| **Patient Monitoring** | Post-treatment observation logging with structured fields (appetite, energy, wound condition, pain, complications) |
| **Discharge & Billing** | Discharge summaries with home-care instructions, automated itemized billing from treatment plan metadata |
| **Online Payments** | PayMongo checkout integration (card, GCash, PayMaya) with printable receipt generation |
| **Security** | AES-256-CBC data encryption, session management, DLP protections, audit logging, login attempt tracking, account lockout |

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.x (vanilla, no framework) |
| **Database** | MySQL / MariaDB (via PDO) |
| **Frontend** | HTML5, vanilla CSS, vanilla JavaScript |
| **Icons** | [Boxicons](https://boxicons.com/) |
| **Fonts** | Google Fonts — Inter, Playfair Display |
| **Auth** | Google OAuth 2.0 (`google/apiclient`) |
| **Email** | PHPMailer (`phpmailer/phpmailer`) |
| **Env Config** | phpdotenv (`vlucas/phpdotenv`) |
| **Payments** | PayMongo REST API |
| **Server** | XAMPP (Apache + MySQL) |

---

## 🏗 System Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                     Landing Page (index.php)                 │
│                   Login / Register / Google OAuth             │
└──────────────────────┬───────────────────────────────────────┘
                       │ auth.php + rbac.php
          ┌────────────┼────────────┬──────────────┬───────────┐
          ▼            ▼            ▼              ▼           ▼
    ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌───────────┐ ┌────────┐
    │  Admin   │ │   CSR    │ │ Vet Tech │ │Vet Assist │ │Pet Owner│
    │Dashboard │ │Dashboard │ │Dashboard │ │ Dashboard │ │Dashboard│
    └──────────┘ └──────────┘ └──────────┘ └───────────┘ └────────┘
          │            │            │              │           │
          └────────────┴────────────┴──────────────┴───────────┘
                                    │
                            ┌───────┴───────┐
                            │  MySQL (PDO)  │
                            │   petmate DB  │
                            └───────────────┘
```

---

## 👥 User Roles

### 🔑 Admin
- Manage user accounts (activate, lock, suspend)
- View audit logs and system monitoring

### 📋 Client Service Representative (CSR)
- Validate incoming visit requests
- Manage itemized billing and compute bills
- View visit summaries and pet records
- Print receipts for paid bills

### 🔬 Veterinary Technician
- Manage examination rooms
- Perform clinical assessments (vitals, lab tests)
- Create and submit treatment plans (medications, procedures, surgeries)
- Approve discharge with follow-up instructions
- View patient monitoring logs

### 💉 Veterinary Assistant
- Prepare examination rooms (equipment, supplies, sanitation)
- Execute treatment plans (administer medications, perform procedures)
- Log patient monitoring observations
- Prepare discharge summaries

### 🐶 Pet Owner
- Register pets with full profile details
- Submit new visit requests with medical history
- Review and consent to treatment plans
- View treatment progress and monitoring updates
- Pay bills online via PayMongo (card / GCash / PayMaya)
- Download and print receipts

---

## 🔄 Clinical Workflow

```
Pet Owner submits visit request
        │
        ▼
CSR validates the visit record ──────── (rejected → owner notified)
        │
        ▼
Vet Assistant prepares exam room
        │
        ▼
Vet Technician performs assessment
  (vitals, lab tests, results)
        │
        ▼
Vet Technician creates treatment plan
  (medications, procedures, surgeries)
        │
        ▼
Pet Owner reviews & consents ────────── (declined → plan revised)
        │
        ▼
Vet Assistant administers treatment
        │
        ▼
Vet Assistant monitors patient
  (structured observation logging)
        │
        ▼
Vet Technician approves discharge
        │
        ▼
Vet Assistant prepares discharge summary
  (home care, follow-up, warnings)
        │
        ▼
CSR generates itemized bill
        │
        ▼
Pet Owner pays via PayMongo
        │
        ▼
Receipt generated & printable ✅
```

---


## 📋 Prerequisites

| Requirement | Version |
|---|---|
| **XAMPP** | 8.x+ (Apache + MySQL/MariaDB + PHP) |
| **PHP** | 8.0 or higher |
| **MySQL / MariaDB** | 5.7+ / 10.4+ |
| **Composer** | 2.x |
| **Web Browser** | Chrome, Firefox, Edge (modern) |

---

## 🚀 Installation & Setup

### 1. Clone the Repository

```bash
cd C:\xampp\htdocs
git clone https://github.com/abigailcomedidoarchi-ship-it/Petmate.git Petmate
```

Or download and extract the project into `C:\xampp\htdocs\Petmate\`.

### 2. Install PHP Dependencies

```bash
cd C:\xampp\htdocs\Petmate
composer install
```

This installs:
- `google/apiclient` — Google OAuth 2.0
- `phpmailer/phpmailer` — Email (OTP verification)
- `vlucas/phpdotenv` — Environment variable management

### 3. Configure Environment Variables

Copy the example file and fill in your real credentials:

```bash
cp .env.example .env
```

Then edit `.env` with your values:

```env
# Mail Configuration (SMTP)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM=your-email@gmail.com
MAIL_FROM_NAME="Petmate System"

# Google OAuth 2.0
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost/Petmate/google_auth.php

# PayMongo Payment Gateway
PAYMONGO_SECRET_KEY=sk_test_your_paymongo_secret_key

# Encryption Key (AES-256-CBC)
ENCRYPTION_KEY=change-this-to-a-random-32-char-key
```

> **Important:**
> - The `.env` file is listed in `.gitignore` and will **never** be committed to version control.
> - Only `.env.example` (with placeholder values) is tracked in Git.
> - For Gmail SMTP, use an [App Password](https://support.google.com/accounts/answer/185833) rather than your account password.
> - For Google OAuth, create credentials at [Google Cloud Console](https://console.cloud.google.com/apis/credentials).
> - For PayMongo, get your API keys at [PayMongo Dashboard](https://dashboard.paymongo.com/).

### 4. Set Up the Database

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open phpMyAdmin at [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
3. Run the SQL files **in order**:

```sql
-- 1. Core schema (creates the database and all primary tables)
SOURCE C:/xampp/htdocs/Petmate/database/schema.sql;

-- 2. Security tables (audit logs, login attempts, active sessions)
SOURCE C:/xampp/htdocs/Petmate/database/security_schema.sql;

-- 3. Additional schema patches (run in order)
SOURCE C:/xampp/htdocs/Petmate/database/add_assessment_flow.sql;
SOURCE C:/xampp/htdocs/Petmate/database/add_exam_rooms.sql;
SOURCE C:/xampp/htdocs/Petmate/database/add_is_verified.sql;
SOURCE C:/xampp/htdocs/Petmate/database/align_workflow_enums.sql;
SOURCE C:/xampp/htdocs/Petmate/database/update_otp_schema.sql;
SOURCE C:/xampp/htdocs/Petmate/database/update_schema.sql;
SOURCE C:/xampp/htdocs/Petmate/database/update_schema2.sql;

-- 4. Migrations
SOURCE C:/xampp/htdocs/Petmate/database/migrations/treatment_workflow_hospital.sql;
SOURCE C:/xampp/htdocs/Petmate/database/migrations/vet_assistant_treatment_execution.sql;
```

Or import them one-by-one via the phpMyAdmin **Import** tab.

### 5. Verify Database Connection

The default connection settings in `includes/db.php` are:

| Setting | Value |
|---|---|
| Host | `localhost` |
| Database | `petmate` |
| Username | `root` |
| Password | *(empty)* |

These are the XAMPP defaults. Update `includes/db.php` if your configuration differs.

---

## ▶ Running the Application

1. Ensure **Apache** and **MySQL** are running in the XAMPP Control Panel.
2. Open your browser and navigate to:

```
http://localhost/Petmate/
```

3. You will see the PetMate landing page. From there:
   - **Register** a new account (email + OTP verification), or
   - **Log in** with Google OAuth 2.0, or
   - **Log in** with existing credentials.

---

## 🔐 Security Features

| Feature | Implementation |
|---|---|
| **Password Hashing** | bcrypt via `password_hash()` / `password_verify()` |
| **Data Encryption** | AES-256-CBC for sensitive fields (`includes/encrypt.php`) |
| **Session Management** | Single active session per user, server-side tracking (`active_sessions` table) |
| **Session Timeout** | Auto-logout after 15 minutes of inactivity (`includes/dlp.php`) |
| **RBAC** | Centralized role-permission matrix (`includes/rbac.php`) |
| **Audit Logging** | All critical actions logged with user ID, IP, and timestamp (`audit_logs` table) |
| **Login Tracking** | Every login attempt logged with success/failure status (`login_attempts` table) |
| **Account Lockout** | Accounts can be locked/suspended after failed attempts |
| **DLP Protection** | Blocks copy, paste, print, and right-click on sensitive pages |
| **Google OAuth** | Secure third-party authentication with role selection for new users |
| **Prepared Statements** | All database queries use PDO prepared statements to prevent SQL injection |

---

## 💳 Payment Integration

PetMate integrates with [PayMongo](https://www.paymongo.com/) for online bill payment:

- **Supported methods:** Credit/Debit Card, GCash, PayMaya
- **Itemized checkout:** Each treatment item (medication, procedure, surgery) is listed as a line item
- **Flow:** CSR computes bill → Pet owner clicks Pay → Redirected to PayMongo checkout → Success callback updates bill status → Receipt is generated
- **Demo fallback:** If the PayMongo API key is invalid, a simulated payment link is provided for testing

> To configure PayMongo, set the `PAYMONGO_SECRET_KEY` variable in your `.env` file with your test or live API key.

---

## 📄 License

This project was built as an academic/capstone project. Please refer to institutional guidelines for usage and distribution terms.

---

<p align="center">
  Built with ❤️ by the PetMate Team
</p>
