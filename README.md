# THRIVE — Business Management & Analytics System

A web-based business management system for small businesses, built with **PHP, MySQL and Bootstrap 5**. THRIVE lets an owner manage products, customers and sales, and turns that data into a **Business Analytics Dashboard** with KPIs, charts and automatic insights.

> Developed as a Database Management Systems (DBMS) course project.

---

## Screenshots

<!-- Add your screenshots here, for example: -->
<!-- ![Login](screenshots/login.png) -->
<!-- ![Dashboard](screenshots/dashboard.png) -->

| Page | Screenshot |
|------|-----------|
| Login / Register | _add image_ |
| Home | _add image_ |
| Products & Customers | _add image_ |
| Record Sale | _add image_ |
| Sales History | _add image_ |
| Analytics Dashboard | _add image_ |

---

## Features

### Core management
- User registration and login (passwords hashed with `password_hash()`)
- Create and manage one or more businesses per account
- Add products (category, cost price, selling price, stock)
- Add customers (name, gender, age, phone, city)
- Record sales with automatic stock deduction
- Sales history for the active business

### Business Analytics Dashboard (main feature)
- **KPI cards:** Revenue, Estimated Profit, Transactions, Units Sold, Products, Customers, Inventory Value, Low Stock Items
- **Revenue chart** (Chart.js line chart): last 7 days, 30 days, 3 months, this year
- **Product analytics:** top products by revenue and units sold, performance table (units, revenue, estimated profit, stock)
- **Customer analytics:** top customers by spending, customers by city and gender
- **Inventory analytics:** inventory cost value, potential sales value, low-stock and out-of-stock products (configurable threshold)
- **Profitability:** revenue, estimated COGS, estimated profit and margin
- **Automatic insights:** rule-based statements generated only when enough real data exists

> Profit is labelled **Estimated Profit** because the database does not store business expenses.

### Security
- Prepared statements for queries involving user input
- Session check before any protected page runs queries
- Active business stored in `$_SESSION['business_id']` and re-verified against the logged-in user on every request
- Every query is scoped to the current business, so one user can never see another business's data

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML, CSS, JavaScript, Bootstrap 5, Font Awesome |
| Charts | Chart.js |
| Backend | PHP |
| Database | MySQL (phpMyAdmin) |
| Environment | XAMPP (Apache + MySQL) |

---

## Database Design

```
users ──< business ──< product
                  ├──< customer
                  └──< sale >── customer
                           └── product
```

| Table | Purpose |
|-------|---------|
| `users` | Accounts and hashed passwords |
| `business` | Businesses owned by a user |
| `product` | Products of a business |
| `customer` | Customers of a business |
| `sale` | Transactions linking business, customer and product |

### DBMS concepts demonstrated
- Primary keys and foreign-key style relationships
- `JOIN` and `LEFT JOIN`
- `GROUP BY` with `SUM()` and `COUNT()`
- `CASE` expressions
- Date filtering and ordering
- Transactions (recording a sale and reducing stock together)
- Prepared statements

---

## Project Structure

```
thrive-business-analytics-system-using-sql
│   business_edit.php
│   customer_add.php
│   home.php
│   login.php
│   logout.php
│   product_add.php
│   profile.php
│   register.php
│   sales_history.php
│   sale_add.php
│   select_business.php
│   setup_business.php
│   summary.php
│
├───assets
│       summary.js
│       summary.css
│
├───css
│       home.css
│
├───includes
│       db.php
│       business.php
│       sidebar.php
│
└───SQL
        thrive.sql
```

---

## Installation (XAMPP)

1. Install and start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy the project folder into `C:\xampp\htdocs\`.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
4. Create a database named `thrive`, then **Import** `SQL/thrive.sql`.
5. Check the connection settings in `includes/db.php`:
   - host: `localhost`
   - user: `root`
   - password: *(empty)*
   - database: `thrive`
6. Open `http://localhost/<your-folder-name>/register.php` in your browser.

---

## Usage

1. Register and log in.
2. Create a business (or choose one if you own several).
3. Add products and customers.
4. Record sales.
5. Open **Business Summary** to view the analytics dashboard.

---

## Configuration

Low-stock warnings use a single constant in `includes/db.php`:

```php
define('LOW_STOCK_THRESHOLD', 5);
```

---

## Limitations

- Profit is an estimate (no expense tracking)
- One sale records one product
- No returns, refunds or discounts
- Rule-based insights only, no forecasting

## Future Improvements

- Expense tracking for net profit
- Staff accounts with roles
- PDF / Excel report export
- Sales forecasting
- Fully mobile-first interface

---

## Author

**Your Name** — Your University, Course Name
