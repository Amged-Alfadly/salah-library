# Salah Library & Stationery E-Commerce Platform (متجر ومكتبة صلاح)

<p align="center">
  <strong>A Production-Ready Bookstore & Stationery E-Commerce Platform Featuring Multi-Media Showcases, Customer Reviews, and Hardened Administrative Audit Security</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-Native%20PDO-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/Database-MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/Security-Brute%20Force%20Lockout%20%7C%20Audit%20Logs-red?style=for-the-badge" alt="Security" />
  <img src="https://img.shields.io/badge/E--Commerce-Multi--Image%20%26%20Video-009688?style=for-the-badge" alt="E-Commerce" />
  <img src="https://img.shields.io/badge/API-RESTful%20Endpoints-FF6F00?style=for-the-badge" alt="REST API" />
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License" />
</p>

---

## 📋 Table of Contents
- [Overview & Abstract](#-overview--abstract)
- [Key Features & Capabilities](#-key-features--capabilities)
- [Defensive Security Architecture](#-defensive-security-architecture)
- [System Architecture & Workflow](#-system-architecture--workflow)
- [Database Schema & ERD](#-database-schema--erd)
- [RESTful API Endpoints](#-restful-api-endpoints)
- [Project Directory Structure](#-project-directory-structure)
- [Installation & Local Deployment Guide](#-installation--local-deployment-guide)
- [Default Demo Credentials](#-default-demo-credentials)
- [Quality Assurance Checklist](#-quality-assurance-checklist)
- [License](#-license)

---

## 📚 Overview & Abstract

**Salah Library Platform** is a specialized e-commerce and retail inventory platform engineered for book publishers, educational stationery stores, and academic bookstores.

The system combines an intuitive, responsive customer storefront with an enterprise-grade administrative dashboard. Customers can search book catalogs, filter stationery by category, inspect products through multi-image galleries and embedded product video reviews, and submit verified feedback. 

On the administrative side, the platform enforces stringent cybersecurity safeguards—including automated brute-force IP throttling, tamper-evident audit logging (`admin_audit_logs`), and multi-layer file upload defense.

---

## ✨ Key Features & Capabilities

- **📖 Rich Multi-Media Product Showcases:**
  - Multi-image gallery with thumbnail selection and main image switching (`product_images`).
  - Native video preview support for book trailers and stationery demonstrations (`product_videos`).
- **⭐ Customer Feedback & Rating Engine:**
  - 5-star customer rating system with user reviews (`product_reviews`).
- **🔍 Fast Catalog Search & Filtering:**
  - Dynamic category navigation (Academic books, stationery, novels, etc.).
- **📊 Real-Time Analytics:**
  - Automated tracking of unique daily visitors and aggregate pageviews (`site_analytics`).
- **🎛 Full-Featured Admin CMS (`admin/`):**
  - Product management, category configuration, user role assignment (Admin, Editor, Customer), and password security.

---

## 🛡 Defensive Security Architecture

The platform was built with strict defensive controls to safeguard user data and administrative integrity:

```mermaid
graph TD
    REQ["🌐 Incoming HTTP Request"] --> WAF["🛡 Session & Cookie Security (HttpOnly, SameSite=Lax)"]
    WAF --> RATE["⏱ Rate Limiter (Max 6 Failed Logins per 15 Min)"]
    RATE -->|Exceeded| LOCK["⛔ Temporary IP Lockout (login_attempts)"]
    RATE -->|Authorized| CSRF["🔐 CSRF Token Validation (X-CSRF-Token)"]
    CSRF --> DB["🗄 Parameterized PDO Prepared Statements"]
    DB --> AUDIT["📝 Audit Trail Engine (admin_audit_logs: Action, IP, JSON Details)"]
```

1. **Automated Brute-Force Throttling:**
   - Any client IP exceeding 6 unsuccessful login attempts within a 15-minute window is temporarily blocked from authentication attempts (`login_attempts`).
2. **Administrative Audit Trail (`admin_audit_logs`):**
   - Every administrative modification (creating products, updating prices, modifying roles) records the operator ID, target resource, timestamp, and client IP in an immutable JSON audit ledger.
3. **Restricted Upload Security:**
   - The `uploads/` directory includes hardened `.htaccess` and `web.config` rules that explicitly prohibit the execution of server-side scripts (e.g., `.php`, `.phtml`, `.cgi`), neutralizing arbitrary file upload exploits.
4. **Zero Hardcoded Secrets:**
   - Database credentials read dynamically from environment variables (`DB_HOST`, `DB_USER`, `DB_PASS`) with clean local development fallbacks.

---

## 🔄 System Architecture & Workflow

```mermaid
sequenceDiagram
    autonumber
    actor C as Customer
    actor A as Store Administrator
    participant S as Storefront Engine
    participant ADM as Admin Control Center
    participant DB as MySQL Database
    participant SEC as Security & Audit Subsystem

    C->>S: Browse Products / View Product Details
    S->>DB: Query Product Images & Video Links
    DB-->>S: Return Catalog Data & Customer Reviews
    S-->>C: Render Product Page with Video Player
    
    A->>ADM: Admin Login Attempt
    ADM->>SEC: Check Rate Limits (login_attempts)
    alt Rate Limit Exceeded
        SEC-->>A: HTTP 429 - Access Locked (15 Minutes)
    else Credentials Valid
        SEC-->>ADM: Generate CSRF Token & Start Session
        A->>ADM: Add / Update Product
        ADM->>DB: Persist Changes (Prepared Statement)
        ADM->>SEC: Write to admin_audit_logs
    end
```

---

## 🗄 Database Schema & ERD

```mermaid
erDiagram
    ROLES ||--o{ USERS : "assigned_to"
    CATEGORIES ||--o{ PRODUCTS : "classifies"
    PRODUCTS ||--o{ PRODUCT_IMAGES : "has_photos"
    PRODUCTS ||--o{ PRODUCT_VIDEOS : "has_videos"
    PRODUCTS ||--o{ PRODUCT_REVIEWS : "reviewed_by"
    USERS ||--o{ PRODUCT_REVIEWS : "writes"
    USERS ||--o{ ADMIN_AUDIT_LOGS : "performed_by"

    ROLES {
        int id PK
        string name UK
    }

    USERS {
        int id PK
        int role_id FK
        string username UK
        string email UK
        string password
        timestamp created_at
    }

    CATEGORIES {
        int id PK
        string name UK
        string slug UK
    }

    PRODUCTS {
        int id PK
        int category_id FK
        string title
        string author
        decimal price
        int stock_quantity
        int view_count
    }

    PRODUCT_IMAGES {
        int id PK
        int product_id FK
        string image_path
        boolean is_main
    }

    PRODUCT_VIDEOS {
        int id PK
        int product_id FK
        string video_url
        enum video_type "link, file"
    }

    PRODUCT_REVIEWS {
        int id PK
        int product_id FK
        int user_id FK
        int rating
        text comment
    }

    LOGIN_ATTEMPTS {
        int id PK
        string ip_address
        string username
        datetime attempt_time
        boolean success
    }

    ADMIN_AUDIT_LOGS {
        bigint id PK
        int admin_id FK
        string action
        json details
        string ip_address
        datetime created_at
    }
```

---

## 📱 RESTful API Endpoints

The system provides lightweight JSON APIs in the `api/` directory:

| Endpoint | Method | Params | Description |
| :--- | :---: | :--- | :--- |
| `api/get_products.php` | `GET` | `?category_id={id}&page={n}` | Paginated catalog listing with main image and pricing. |
| `api/get_categories.php` | `GET` | - | Returns all active category departments. |
| `api/search.php` | `GET` | `?q={query}` | Instant live search for books, authors, and stationery items. |
| `api/get_stats.php` | `GET` | - | Aggregated traffic and daily pageview statistics. |
| `admin/api_handler.php` | `POST` | `action={action}` | Protected administrative backend handler (CSRF token required). |

---

## 📁 Project Directory Structure

```text
salah-library/
├── .gitignore                   # Excludes logs, environment configs, and temp files
├── LICENSE                      # MIT Open Source License
├── README.md                    # Comprehensive technical & academic documentation
├── QA_CHECKLIST.md              # Quality assurance & security test runbook
├── index.php                    # Storefront homepage & product catalog
├── product.php                  # Interactive single product details & video view
├── admin/                       # Administrative Control Center
│   ├── index.php                # Dashboard & real-time analytics
│   ├── products.php             # Product catalog manager
│   ├── add_product.php          # Product creation & media upload
│   ├── categories.php           # Category department management
│   ├── users.php                # Role administration
│   ├── login.php                # Hardened login with brute-force protection
│   └── api_handler.php          # Internal CSRF-verified administrative API
├── api/                         # Storefront RESTful JSON APIs
│   ├── db_config.php            # Active database connection (sanitized)
│   ├── db_config.example.php    # Clean environment configuration template
│   ├── get_products.php
│   ├── get_categories.php
│   └── search.php
├── database/                    # Unified SQL schema & seed records
│   └── schema.sql               # Complete 10-table relational schema
├── css/                         # Storefront styling
├── js/                          # Frontend scripts & dynamic interaction
├── uploads/                     # Product images & media storage (protected by .htaccess)
└── tests/                       # Automated API smoke tests
    └── api_smoke_test.php       # Verification test script
```

---

## 🚀 Installation & Local Deployment Guide

### Prerequisites
- **Web Server:** Apache (via XAMPP, WAMP, LAMP, or native Apache2).
- **PHP Version:** PHP 7.4 or 8.x (with `pdo`, `pdo_mysql`, `curl`, and `json` enabled).
- **Database:** MySQL 5.7+ or MariaDB 10.3+.

### Step-by-Step Installation

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/your-username/salah-library.git
   cd salah-library
   ```

2. **Configure Database Connection:**
   Copy the example template to `api/db_config.php`:
   ```bash
   cp api/db_config.example.php api/db_config.php
   ```
   Set your local MySQL credentials:
   ```php
   $host     = 'localhost';
   $dbname   = 'LibraryStore';
   $username = 'root';
   $password = '';
   ```

3. **Import Database Schema:**
   ```bash
   mysql -u root -p LibraryStore < database/schema.sql
   ```
   *(Or import `database/schema.sql` via phpMyAdmin).*

4. **Launch Application:**
   Access the storefront via your browser:
   ```text
   http://localhost/salah-library/
   ```
   Access the Admin Portal:
   ```text
   http://localhost/salah-library/admin/login.php
   ```

---

## 🔑 Default Demo Credentials

| Role | Username | Password | Notes |
| :--- | :--- | :--- | :--- |
| **Store Administrator** | `admin` | `admin123` | Full access to dashboard, products, and audit logs. |

---

## 📄 License

This project is licensed under the **MIT License** - see the [LICENSE](LICENSE) file for details.
