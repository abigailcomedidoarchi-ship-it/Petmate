# 🐾 PetMate — Veterinary Clinic Management System

A full-featured, role-based veterinary clinic management platform built with **PHP**, **MySQL**, and **vanilla JavaScript**. PetMate streamlines the entire clinical workflow — from pet registration and visit requests through assessment, treatment, monitoring, discharge, and billing — across multiple staff roles and a pet-owner self-service portal.

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

## 📂 Project Structure

```
Petmate/
├── index.php                    # Landing page (redirects if logged in)
├── login.php                    # Email/password login
├── register.php                 # User registration with OTP
├── verify_otp.php               # OTP email verification
├── google_auth.php              # Google OAuth 2.0 flow + role selection
├── change_password.php          # Password change utility
├── logout.php                   # Session destroy & redirect
├── composer.json                # PHP dependencies
├── .env                         # Mail configuration (not committed)
│
├── assets/
│   ├── css/
│   │   └── style.css            # Global design system & styles
│   └── js/
│       └── main.js              # Shared frontend utilities
│
├── includes/                    # Core backend modules
│   ├── auth.php                 # Session management & role redirects
│   ├── rbac.php                 # Role-based access control
│   ├── db.php                   # PDO database connection
│   ├── encrypt.php              # AES-256-CBC encryption/decryption
│   ├── dlp.php                  # Data Loss Prevention (session timeout, copy/print block)
│   ├── session_guard.php        # Active session tracking (single-session enforcement)
│   ├── logger.php               # Audit log & login attempt logger
│   ├── mailer.php               # PHPMailer configuration
│   ├── header.php               # Shared HTML header/navigation
│   ├── footer.php               # Shared HTML footer
│   ├── monitoring_logs_schema.php   # Monitoring log field definitions
│   └── treatment_workflow_schema.php # Treatment workflow state machine
│
├── database/                    # SQL schema & migrations
│   ├── schema.sql               # Core database schema (all tables)
│   ├── security_schema.sql      # Audit logs, login attempts, active sessions
│   ├── add_assessment_flow.sql  # Assessment sessions & rooms
│   ├── add_exam_rooms.sql       # Examination room tables
│   ├── add_is_verified.sql      # Email verification flag
│   ├── align_workflow_enums.sql # Workflow status enum alignment
│   ├── update_otp_schema.sql    # OTP table updates
│   ├── update_schema.sql        # Schema patches
│   ├── update_schema2.sql       # Additional schema patches
│   └── migrations/
│       ├── treatment_workflow_hospital.sql
│       └── vet_assistant_treatment_execution.sql
│
├── dashboards/
│   ├── admin/
│   │   └── index.php            # Admin dashboard
│   │
│   ├── csr/                     # Client Service Representative
│   │   ├── index.php            # CSR dashboard home
│   │   ├── billing.php          # Billing overview
│   │   ├── compute_bill.php     # Itemized bill computation
│   │   ├── print_receipt.php    # Receipt printing
│   │   ├── pet_info.php         # Pet information lookup
│   │   ├── pet_records.php      # Pet record management
│   │   ├── review_record.php    # Visit record review
│   │   ├── visit_summaries.php  # Visit summary management
│   │   ├── messages.php         # Messaging interface
│   │   └── settings.php         # CSR settings
│   │
│   ├── vet_technician/          # Veterinary Technician
│   │   ├── index.php            # Vet tech dashboard home
│   │   ├── exam_rooms.php       # Exam room management
│   │   ├── exam_room.php        # Individual room view
│   │   ├── assessments.php      # Assessment list
│   │   ├── assessment_form.php  # Assessment data entry
│   │   ├── assessment_queue.php # Pending assessment queue
│   │   ├── assessment_summary.php # Assessment results summary
│   │   ├── pet_overview.php     # Patient overview
│   │   ├── treatment_plan.php   # Treatment plan creation
│   │   ├── treatment_details.php # Treatment plan details
│   │   ├── view_treatment.php   # Treatment viewer
│   │   ├── record_results.php   # Lab result recording
│   │   ├── approve_discharge.php # Discharge approval
│   │   ├── view_monitoring.php  # Monitoring log viewer
│   │   ├── schedule_assignment.php # Staff schedule assignment
│   │   ├── acknowledge_room.php # Room readiness acknowledgment
│   │   ├── prescriptions.php    # Prescription management
│   │   └── settings.php         # Vet tech settings
│   │
│   ├── vet_assistant/           # Veterinary Assistant
│   │   ├── index.php            # Vet assistant dashboard home
│   │   ├── prepare_room.php     # Room preparation checklist
│   │   ├── room_status.php      # Room status overview
│   │   ├── administer.php       # Treatment administration
│   │   ├── monitor_patient.php  # Patient monitoring & observation logs
│   │   ├── monitoring_queue.php # Monitoring queue
│   │   ├── discharge.php        # Discharge workflow
│   │   ├── discharge_prep.php   # Discharge summary preparation
│   │   ├── instructions.php     # Post-care instructions
│   │   └── settings.php         # Vet assistant settings
│   │
│   ├── pet_owner/               # Pet Owner
│   │   ├── index.php            # Pet owner dashboard home
│   │   ├── register_pet.php     # Pet registration form
│   │   ├── my_pets.php          # My pets list
│   │   ├── submit_visit.php     # New visit request submission
│   │   ├── visit_records.php    # Visit history
│   │   ├── treatment_plans.php  # Treatment plans list
│   │   ├── view_treatment.php   # Treatment plan detail & consent
│   │   ├── bills.php            # Billing overview
│   │   ├── pay.php              # PayMongo checkout redirect
│   │   ├── payment_success.php  # Post-payment confirmation
│   │   ├── receipt.php          # Printable receipt
│   │   ├── book_sitter.php      # Pet sitter booking
│   │   ├── my_bookings.php      # Booking management
│   │   ├── messages.php         # Messaging
│   │   ├── mark_read.php        # Mark notifications as read
│   │   └── settings.php         # Pet owner settings
│   │
│   ├── vet/
│   │   └── index.php            # Veterinarian dashboard (legacy)
│   │
│   ├── admin.php                # Admin dashboard wrapper
│   ├── csr.php                  # CSR dashboard wrapper
│   ├── pet_owner.php            # Pet owner dashboard wrapper
│   ├── vet_technician.php       # Vet tech dashboard wrapper
│   ├── register_pet.php         # Shared pet registration
│   └── review_record.php        # Shared record review
│
└── vendor/                      # Composer dependencies (auto-generated)
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
git clone <repository-url> Petmate
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
