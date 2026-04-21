# ParkOS — Setup Guide (PostgreSQL + PHP built-in server)

## Requirements
- PHP 8.0+ with `pdo_pgsql` extension enabled
- PostgreSQL 13+

---

## Step 1 — Set up the PostgreSQL database

```bash
# Create the database
psql -U postgres -c "CREATE DATABASE parking_app;"

# Run the schema + seed data
psql -U postgres -d parking_app -f parking_app.sql
```

### Migrating an existing database (if upgrading)
If you already have the database set up from a previous version, run these ALTER statements:

```sql
ALTER TABLE Zone    ADD COLUMN IF NOT EXISTS price_per_hour DECIMAL(10,2) NOT NULL DEFAULT 30.00;
ALTER TABLE Vehicle ADD COLUMN IF NOT EXISTS vehicle_type   VARCHAR(20)   NOT NULL DEFAULT 'Car';
```

---

## Step 2 — Configure database credentials

Edit `config/db.php` and update:

```php
define('DB_USER', 'postgres');   // your PostgreSQL username
define('DB_PASS', '');           // your PostgreSQL password
```

---

## Step 3 — Enable pdo_pgsql in php.ini

Find your `php.ini` (run `php --ini` to locate it), then uncomment:

```
extension=pdo_pgsql
```

On Windows, also make sure this line is present (not commented):
```
extension=pgsql
```

---

## Step 4 — Run the PHP built-in server

```bash
cd parking-app-pg
php -S localhost:8000
```

Then open: http://localhost:8000

---

## Changes in this version

### 1. Admin — Zone Pricing
- `Zone` table has a new `price_per_hour DECIMAL(10,2)` column
- Admin can set price when **creating** a zone (Add New Zone form)
- Admin can update price via **Edit Zone** modal (all fields) or the dedicated **💰 Price** quick-action button
- Price shown in zone overview table on admin dashboard and manage zones page
- Price shown on each zone card on the user Book Parking page
- All fee calculations now use `price_per_hour` from the zone instead of hardcoded slot-type rates

### 2. Vehicle Type Selection (User Side)
- `Vehicle` table has a new `vehicle_type VARCHAR(20)` column (Car / Bike / Truck)
- Dropdown added to the **Add Vehicle** modal
- Vehicle type badge shown on vehicle cards (My Vehicles page)
- Vehicle type shown in booking dropdown, session history, dashboard recent sessions
- Vehicle type visible in all admin views: Sessions, Payments, User Detail

### 3. Payment Message
- The "Simulated Payment — No real transaction. For demo purposes only." message replaced with:
  **"We have to add additional security functions for payment"**

### 4. Demo Credentials Removed
- Removed the "Demo Admin Login" box from `auth/login.php` left panel
- Removed hardcoded admin credentials footer from `index.php`

---

## File Change Summary

| File | Changes |
|------|---------|
| `parking_app.sql` | Added `price_per_hour` to Zone, `vehicle_type` to Vehicle |
| `config/db.php` | Updated `calcFee()` to accept `price_per_hour` parameter |
| `auth/login.php` | Removed demo admin credentials block |
| `index.php` | Removed hardcoded admin credentials footer |
| `user/vehicles.php` | Added `vehicle_type` dropdown, display badge |
| `user/book.php` | Shows price/hr per zone, vehicle type in dropdown |
| `user/dashboard.php` | Uses `price_per_hour` for fee calc, shows vehicle type |
| `user/history.php` | Uses `price_per_hour`, shows rate/hr column, vehicle type |
| `user/payments.php` | Payment message updated |
| `admin/manage_zones.php` | Price field in Add/Edit modals, dedicated Price button |
| `admin/dashboard.php` | Price/hr column in zone overview table |
| `admin/view_sessions.php` | Shows vehicle type and rate/hr columns |
| `admin/view_payments.php` | Shows vehicle type column |
| `admin/user_detail.php` | Shows vehicle type in vehicles list and sessions table |
