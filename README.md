# Employee Leave Request & Balance Tracker API

A plain PHP 8+ / MySQL REST API (no framework) for managing employee leave requests and balances.

---

## Project Structure

```
employee-leave-api/
├── config.php        # DB credentials & PDO connection helper
├── employee.php      # GET /employee.php?id={id}
├── leaves.php        # GET /leaves.php?employee_id={id}
├── apply-leave.php   # POST /apply-leave.php
├── database.sql      # MySQL schema + sample data
└── README.md
```

---

## Prerequisites

| Software | Version |
|----------|---------|
| XAMPP    | 8.x (includes PHP 8+ and MySQL 8) |
| Postman  | Any recent version |

---

## Setup

### 1. Copy project files

Place the `employee-leave-api/` folder inside XAMPP's web root:

```
C:\xampp\htdocs\employee-leave-api\
```

### 2. Create the MySQL database

**Option A — phpMyAdmin (GUI)**

1. Open your browser: `http://localhost/phpmyadmin`
2. Click **Import** in the top menu.
3. Click **Choose File** → select `database.sql`.
4. Click **Go**.

**Option B — MySQL CLI**

```bash
mysql -u root -p < C:\xampp\htdocs\employee-leave-api\database.sql
```

This creates `employee_leave_db`, both tables, and inserts three sample employees.

### 3. Configure database credentials

Open `config.php` and update:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'employee_leave_db');
define('DB_USER', 'root');   // your MySQL username
define('DB_PASS', '');       // your MySQL password (empty by default in XAMPP)
```

### 4. Start XAMPP

Open the XAMPP Control Panel and click **Start** for both **Apache** and **MySQL**.

---

## Sample Employees (seeded by database.sql)

| id | name            | annual | sick | casual |
|----|-----------------|--------|------|--------|
| 1  | Alice Johnson   | 15     | 10   | 7      |
| 2  | Bob Smith       | 10     | 8    | 5      |
| 3  | Carol Williams  | 5      | 6    | 3      |

---

## API Endpoints

Base URL: `http://localhost/employee-leave-api`

---

### GET /employee.php?id={id}

Returns employee details and current leave balances.

**Query Parameters**

| Parameter | Type    | Required | Description |
|-----------|---------|----------|-------------|
| id        | integer | Yes      | Employee ID |

**Example Request**

```
GET http://localhost/employee-leave-api/employee.php?id=1
```

**Success Response (200)**

```json
{
  "success": true,
  "employee": {
    "id": 1,
    "name": "Alice Johnson",
    "annual_leave_balance": 15,
    "sick_leave_balance": 10,
    "casual_leave_balance": 7
  }
}
```

**Error — Not Found (404)**

```json
{
  "success": false,
  "message": "Employee with id 99 not found."
}
```

**Error — Invalid id (400)**

```json
{
  "success": false,
  "message": "Parameter id must be a positive integer."
}
```

---

### GET /leaves.php?employee_id={id}

Returns all leave requests for an employee, newest first.

**Query Parameters**

| Parameter   | Type    | Required | Description |
|-------------|---------|----------|-------------|
| employee_id | integer | Yes      | Employee ID |

**Example Request**

```
GET http://localhost/employee-leave-api/leaves.php?employee_id=1
```

**Success Response (200)**

```json
{
  "success": true,
  "employee_id": 1,
  "employee_name": "Alice Johnson",
  "total": 1,
  "leaves": [
    {
      "id": 1,
      "employee_id": 1,
      "leave_type": "annual",
      "start_date": "2026-08-20",
      "end_date": "2026-08-22",
      "status": "pending",
      "created_at": "2026-08-18 12:00:00"
    }
  ]
}
```

**Empty Result (200)**

```json
{
  "success": true,
  "employee_id": 2,
  "employee_name": "Bob Smith",
  "total": 0,
  "leaves": []
}
```

---

### POST /apply-leave.php

Submits a new leave request.

**Request Headers**

```
Content-Type: application/json
```

**Request Body**

```json
{
  "employee_id": 1,
  "leave_type": "annual",
  "start_date": "2026-08-20",
  "end_date": "2026-08-22"
}
```

| Field       | Type    | Required | Allowed values             |
|-------------|---------|----------|----------------------------|
| employee_id | integer | Yes      | Any existing employee ID   |
| leave_type  | string  | Yes      | `annual`, `sick`, `casual` |
| start_date  | string  | Yes      | `YYYY-MM-DD`               |
| end_date    | string  | Yes      | `YYYY-MM-DD`               |

**Success Response (201)**

```json
{
  "success": true,
  "message": "Leave request submitted successfully.",
  "data": {
    "leave_request_id": 1,
    "employee_id": 1,
    "employee_name": "Alice Johnson",
    "leave_type": "annual",
    "start_date": "2026-08-20",
    "end_date": "2026-08-22",
    "days_requested": 3,
    "status": "pending",
    "remaining_balance": 12
  }
}
```

**Error — Missing Field (400)**

```json
{
  "success": false,
  "message": "Missing required field: leave_type"
}
```

**Error — Invalid leave_type (400)**

```json
{
  "success": false,
  "message": "leave_type must be one of: annual, sick, casual"
}
```

**Error — Invalid Date Format (400)**

```json
{
  "success": false,
  "message": "start_date must be in YYYY-MM-DD format."
}
```

**Error — start_date after end_date (400)**

```json
{
  "success": false,
  "message": "start_date cannot be after end_date."
}
```

**Error — Employee Not Found (404)**

```json
{
  "success": false,
  "message": "Employee with id 99 not found."
}
```

**Error — Insufficient Balance (400)**

```json
{
  "success": false,
  "message": "Insufficient annual leave balance.",
  "requested_days": 20,
  "available_balance": 15
}
```

**Error — Overlapping Leave (409)**

```json
{
  "success": false,
  "message": "Leave request overlaps with an existing active leave request."
}
```

---

## HTTP Status Code Reference

| Code | Meaning               | When used                                |
|------|-----------------------|------------------------------------------|
| 200  | OK                    | Successful GET                           |
| 201  | Created               | Leave request submitted                  |
| 400  | Bad Request           | Validation failure, insufficient balance |
| 404  | Not Found             | Employee does not exist                  |
| 405  | Method Not Allowed    | Wrong HTTP method                        |
| 409  | Conflict              | Overlapping leave request                |
| 500  | Internal Server Error | Database / server error                  |

---

## Postman Quick-Start

1. Open Postman and click **New Request**.
2. Select the HTTP method and paste the URL.
3. For POST: go to **Body → raw → JSON** and paste the body.

### Scenario Tests

**Normal apply (should return 201)**

```
POST http://localhost/employee-leave-api/apply-leave.php
Body: { "employee_id": 1, "leave_type": "annual", "start_date": "2026-08-20", "end_date": "2026-08-22" }
```

**Overlap test — send the request above twice; second returns 409**

**Insufficient balance (Carol only has 5 annual days)**

```json
{ "employee_id": 3, "leave_type": "annual", "start_date": "2026-09-01", "end_date": "2026-09-30" }
```

**Employee not found**

```
GET http://localhost/employee-leave-api/employee.php?id=999
```

---

## Design Decisions (for interview explanation)

| Decision | Reason |
|----------|--------|
| `PDO::ERRMODE_EXCEPTION` | Throws exceptions on DB errors so we can catch them cleanly |
| `ATTR_EMULATE_PREPARES = false` | Forces real prepared statements in the MySQL driver — strongest SQL injection defence |
| Column name white-listed, not parameterised | PDO cannot bind column names; we validate `$leaveType` against `$allowedTypes` first, then embed the column name safely |
| `beginTransaction()` with `rollBack()`/`commit()` | Guarantees the INSERT and the balance UPDATE are atomic — either both succeed or neither does |
| Overlap condition `start <= end2 AND end >= start2` | Standard interval overlap test; catches partial, full, and identical overlaps |
| `diff->days + 1` for day count | `DateInterval::$days` is the difference; `+1` makes it inclusive of both the first and last day |
| `filter_var(..., FILTER_VALIDATE_INT)` | Rejects inputs like `"2abc"` that a plain `(int)` cast would silently truncate to `2` |
| `status != 'rejected'` in overlap check | Rejected leaves are treated as cancelled and no longer block a date range |
| CORS headers on every response | Lets Postman and browser-based clients on other origins call the API |

---

## Notes

- Leave `status` defaults to **`pending`**. There is no approve/reject endpoint by design — the test focuses on the apply flow.
- All dates are stored as MySQL `DATE` (`YYYY-MM-DD`).
- The API is stateless — no sessions or authentication required.
