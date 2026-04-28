# 🚀 FinBridge API

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

FinBridge is a comprehensive Fintech solution bridging the gap between Microfinance Institutions (MFIs) and Entrepreneurs. This repository contains the robust RESTful API built with Laravel, featuring role-based access control, subscription management, and integrated payment processing.

---

## 🛠 Tech Stack

- **Core:** Laravel 13.x
- **Database:** MySQL / PostgreSQL
- **Authentication:** Laravel Sanctum (Token-based)
- **Payment Gateway:** SSLCommerz Integration
- **Testing:** Pest PHP
- **Infrastructure:** Redis for Caching (Predis)

---

## ✨ Key Features

- **Multi-Role System:** Platform Admin, MFI Admin, and Entrepreneur.
- **Subscription Management:** Tiered plans (Trial, Pro) with feature limits.
- **Loan Lifecycle:** Product creation, application submission, and approval workflow.
- **Real-time Analytics:** Dashboard for both MFI and Platform administrators.
- **Automated Invoicing:** Professional invoice generation for subscriptions.
- **Email Notifications:** Automated alerts for loan status changes.

---

## 🚀 Getting Started

### Prerequisites

- PHP >= 8.3
- Composer
- PostgreSQL (Must be running in the background)
- Redis (Optional, for caching)

### Installation Steps

1. **Clone the Repository**

    ```bash
    git clone https://github.com/Sabuj-Chowdhury/finBridge-api.git
    cd finBridge-api
    ```

2. **Install Dependencies**

    ```bash
    composer install
    ```

3. **Environment Setup**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

    **Required Environment Keys (.env):**
    Ensure all the following keys are present in your `.env` file: especially database config

    | Category          | Keys                                                                                                              |
    | :---------------- | :---------------------------------------------------------------------------------------------------------------- |
    | **App**           | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_LOCALE`, `APP_MAINTENANCE_DRIVER`, `BCRYPT_ROUNDS` |
    | **Database**      | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`                                |
    | **Logging**       | `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL`                                                                           |
    | **Cache & Queue** | `QUEUE_CONNECTION`, `CACHE_STORE`, `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`                   |
    | **Mail**          | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS` |
    | **SSLCommerz**    | `SSL_STORE_ID`, `SSL_STORE_PASSWORD`, `SSL_MODE`                                                                  |

4. **Database Configuration**
   Configure your database credentials in `.env` and run migrations with seeders:

    ```bash
    php artisan migrate:fresh --seed
    ```

    **Default Admin Credentials:**
    - **Email:** `admin@finbridge.com`
    - **Password:** `password`

5. **SSLCommerz Setup**
   Add your SSLCommerz credentials to `.env`. (Check `SSL_MODE=sandbox` for testing).

6. **Run the Application**
    ```bash
    php -S localhost:9000 -t public
    ```

---

## 📑 API Documentation

### 🔐 Authentication

| Endpoint                             | Method | Payload                                                          | Description                 |
| :----------------------------------- | :----- | :--------------------------------------------------------------- | :-------------------------- |
| `/api/v1/auth/register/mfi`          | `POST` | `name, email, phone, password, mfi_name, mfi_email?, mfi_phone?` | Register as an MFI Admin    |
| `/api/v1/auth/register/entrepreneur` | `POST` | `name, email, phone, password`                                   | Register as an Entrepreneur |
| `/api/v1/auth/login`                 | `POST` | `email, password`                                                | Login to get API Token      |
| `/api/v1/auth/logout`                | `POST` | `Header: Authorization`                                          | Revoke current token        |

**Sample Login Response:**

```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": { "id": "...", "name": "John Doe", "role": "entrepreneur" },
        "token": "1|abcdef123456..."
    }
}
```

---

### 💰 Subscription & Payments

| Endpoint                         | Method | Description                               |
| :------------------------------- | :----- | :---------------------------------------- |
| `/api/v1/subscription-plans`     | `GET`  | List all available subscription plans     |
| `/api/v1/subscription/subscribe` | `POST` | Initiate payment for a plan (`plan_id`)   |
| `/api/v1/mfi/subscription`       | `GET`  | View current subscription status & limits |
| `/api/v1/mfi/payments`           | `GET`  | View payment history                      |
| `/api/v1/mfi/invoice/{id}`       | `GET`  | Get invoice details for a transaction     |

---

### 🏦 Loan Management

#### For Entrepreneurs

| Endpoint                            | Method | Payload                                                                    | Description                     |
| :---------------------------------- | :----- | :------------------------------------------------------------------------- | :------------------------------ |
| `/api/v1/loan-products`             | `GET`  | -                                                                          | Browse all active loan products |
| `/api/v1/loan/apply`                | `POST` | `mfi_id, loan_product_id, amount, duration_months, nid (file), tax?, tin?` | Apply for a loan (Multipart)    |
| `/api/v1/entrepreneur/applications` | `GET`  | -                                                                          | View my loan applications       |

#### For MFIs

| Endpoint                                | Method | Payload                                                          | Description                            |
| :-------------------------------------- | :----- | :--------------------------------------------------------------- | :------------------------------------- |
| `/api/v1/mfi/loan-products`             | `GET`  | -                                                                | List MFI's own products                |
| `/api/v1/mfi/loan-products`             | `POST` | `name, max_amount, interest_rate, duration_months, description?` | Create a new loan product              |
| `/api/v1/mfi/applications`              | `GET`  | `status?, search?`                                               | List applications received by this MFI |
| `/api/v1/mfi/applications/{id}/approve` | `POST` | -                                                                | Approve a pending application          |
| `/api/v1/mfi/applications/{id}/reject`  | `POST` | -                                                                | Reject a pending application           |

---

### 🛡 Platform Admin

| Endpoint                           | Method | Description                                     |
| :--------------------------------- | :----- | :---------------------------------------------- |
| `/api/v1/admin/dashboard`          | `GET`  | Global stats (Total revenue, active MFIs, etc.) |
| `/api/v1/admin/reports/revenue`    | `GET`  | Detailed revenue report with trends             |
| `/api/v1/admin/mfis`               | `GET`  | Manage MFI institutions                         |
| `/api/v1/admin/applications`       | `GET`  | View all system-wide applications               |
| `/api/v1/admin/subscription-plans` | `POST` | Create a new subscription plan                  |

---

## 🛑 Error Responses

The API uses standard HTTP status codes:

- `200/201`: Success
- `400`: Bad Request (Validation failure)
- `401`: Unauthorized (Missing/invalid token)
- `403`: Forbidden (Insufficient role or expired subscription)
- `404`: Not Found
- `500`: System Error

---

## 🧪 Testing

Run the test suite using Pest:

```bash
php artisan test
```

## 📄 License

The FinBridge API is open-sourced software licensed under the [MIT license](LICENSE).

---

<p align="center">Made with ❤️ by Sabuj Chowdhury</p>
