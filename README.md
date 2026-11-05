# CampusPark Parking Reservation System

**CIS 344 Group Project | Lehman College, Fall 2026**

A web app that lets Lehman College students and staff reserve a campus parking space before they arrive. Users create an account, register their car, pick a lot and time window, choose an open space from a drawing of the lot, pay, and get a printable parking pass. Built with PHP, MySQL, HTML, CSS and JavaScript.

**Skills demonstrated:** PHP server-side processing, relational database design, primary and foreign keys, SQL joins (INNER, LEFT, multi-table, UPDATE with JOIN, views), GROUP BY reporting, prepared statements, server-side validation, transactions, responsive front-end design.

## Features

- **Live lot overview:** each lot's price, hours and how many spaces are free right now
- **Accounts:** sign up, log in and log out with hashed passwords
- **Vehicles:** register up to 3 cars by license plate
- **Find a space:** search by lot, day, arrival time and hours; free and taken spaces are drawn as parking stalls
- **Checkout:** choose a car and pay; the booking and payment are saved together in one transaction
- **Parking pass:** printable receipt with space, car, times and payment
- **My bookings:** upcoming bookings and history; cancel a booking before it starts for a refund
- **Lot report:** revenue and usage by lot, by space type, and the most-booked spaces
- **Responsive:** works on phones, tablets and desktops

## Database design

The database (`campuspark`) has six tables and one view:

| Table | Purpose | Primary key |
|---|---|---|
| `members` | People with an account | `member_id` |
| `vehicles` | Cars registered by members | `vehicle_id` |
| `lots` | Parking areas, each with its own price and hours | `lot_id` |
| `spaces` | Individual spaces inside a lot | `space_id` |
| `bookings` | A car in a space for a time window | `booking_id` |
| `payments` | One payment per booking (only the card's last 4 digits are stored) | `payment_id` |
| `booking_details` (view) | Joins all six tables for the parking pass | |

**Relationships:**

| Relationship | Meaning | Foreign key |
|---|---|---|
| Member **owns** Vehicle | One member can register many cars | `vehicles.member_id` |
| Lot **contains** Space | One lot has many spaces | `spaces.lot_id` |
| Vehicle **makes** Booking | One car can have many bookings | `bookings.vehicle_id` |
| Space **is reserved by** Booking | One space is booked many times | `bookings.space_id` |
| Booking **has** Payment | Each booking has at most one payment | `payments.booking_id` (UNIQUE) |

**Rules in the schema:** UNIQUE email and plate number, UNIQUE space code per lot, CHECK that a lot closes after it opens and a booking ends after it starts, and an index on (`space_id`, `starts_at`, `ends_at`) for the availability search.

## Joins used

| Page | Join | What it gives us |
|---|---|---|
| `index.php` | `lots` INNER JOIN `spaces` LEFT JOIN `bookings` + GROUP BY | Total spaces and spaces in use right now, per lot |
| `find.php` | `spaces` LEFT JOIN `bookings` (overlapping only) + GROUP BY | Every space with a clash count; 0 means free |
| `checkout.php` | `spaces` INNER JOIN `lots` | Space details plus the lot's price and hours |
| `receipt.php` | `booking_details` view (5 INNER JOINs + 1 LEFT JOIN) | The whole parking pass in one SELECT |
| `my_bookings.php` | `bookings` JOIN `vehicles` JOIN `spaces` JOIN `lots` LEFT JOIN `payments` | All of a member's bookings, with amounts |
| `my_vehicles.php` | `vehicles` LEFT JOIN `bookings` + COUNT, MAX | Times each car was booked and the last date |
| `cancel_booking.php` | UPDATE `bookings` JOIN `vehicles` JOIN `payments` | Cancels and refunds in one statement, only for the owner |
| `report.php` | `lots` LEFT JOIN `spaces` LEFT JOIN `bookings` LEFT JOIN `payments` + GROUP BY | Revenue per lot, use by space type, top 5 spaces |

**Overlap rule:** an existing booking clashes with a new one when `existing.starts_at < new.ends_at AND existing.ends_at > new.starts_at`.

## Files in this repository

| File | What it is |
|---|---|
| [database/campuspark.sql](database/campuspark.sql) | **Run first.** Creates the database, tables, view and sample data |
| [inc/connect.php](inc/connect.php) | Database settings and PDO connection |
| [inc/helpers.php](inc/helpers.php) | Shared functions: safe output, CSRF form keys, login checks, validation |
| [inc/top.php](inc/top.php) / [inc/bottom.php](inc/bottom.php) | Header, menu and footer used on every page |
| [index.php](index.php) | Home page with live lot availability |
| [signup.php](signup.php) / [login.php](login.php) / [logout.php](logout.php) | Accounts |
| [my_vehicles.php](my_vehicles.php) | Add and list cars |
| [find.php](find.php) | Search form and lot drawing |
| [checkout.php](checkout.php) | Choose a car, pay, save booking and payment |
| [receipt.php](receipt.php) | Parking pass |
| [my_bookings.php](my_bookings.php) / [cancel_booking.php](cancel_booking.php) | Booking history and cancel |
| [report.php](report.php) | Lot report |
| [css/campuspark.css](css/campuspark.css) | Styles and responsive layout |
| [js/app.js](js/app.js) | Price estimate, card formatting, form checks |

## How to run

1. Install **XAMPP** (Windows/Mac) or **MAMP** (Mac), then start **Apache** and **MySQL**.
2. Download this repository and put the folder in the web root, renamed to `campuspark`:
   - XAMPP Windows: `C:\xampp\htdocs\campuspark`
   - XAMPP Mac: `/Applications/XAMPP/xamppfiles/htdocs/campuspark`
   - MAMP: `/Applications/MAMP/htdocs/campuspark`
3. Open `http://localhost/phpmyadmin`, click **Import**, choose `database/campuspark.sql` and click **Go**.
4. If your MySQL password isn't blank, edit `$dbUser` / `$dbPass` in `inc/connect.php` (MAMP uses `root` / `root`).
5. Open `http://localhost/campuspark/` (MAMP: `http://localhost:8888/campuspark/`).

**Demo account:** `demo@campuspark.test` / `Park1234!`

Importing the SQL file again resets the database to the original sample data.

## How to test

1. **Sign up** with your own email, or log in with the demo account.
2. On **My Vehicles**, add a car (for example `ABC1234`).
3. On **Find a Space**, choose *North Lot*, a day in the next two weeks, *10:00 am* and *3 hours*, then click **Show spaces**.
4. Click a green **Book** stall. Pay with `4242 4242 4242 4242`, any future month and year, and any 3-digit CVV.
5. Check the **parking pass**, then try **Print pass**.
6. Search the same lot and time again. The space you booked now shows as **Taken**.
7. On **My Bookings**, **Cancel** the booking. It moves to History as *Cancelled · Refunded* and the space opens up again.
8. Open **Lot Report**. Cancelled bookings are not counted in the totals.

**Inputs that should be rejected:** a time outside the lot's hours, in the past, more than 14 days ahead or longer than 8 hours; a card number that isn't 15–16 digits, an expired card or a bad CVV; a plate that is already registered or a 4th car; opening someone else's booking by changing `?b=` in the address bar.

## Security

| Risk | What we did |
|---|---|
| SQL injection | Every query with user input is a PDO prepared statement with named placeholders. Emulated prepares are off. |
| Bad input | All input is validated in PHP: name and plate patterns, email format, password strength, lot hours, dates, booking length, card number, expiry and CVV. JavaScript checks are only a convenience. |
| XSS | All output goes through `h()`, which wraps `htmlspecialchars()`. |
| CSRF | Every POST form carries a random session key checked with `hash_equals()`. |
| Passwords | Stored with `password_hash()`, checked with `password_verify()`. A new session ID is issued at login. |
| Other people's data | Receipt, booking and cancel queries all filter by the logged-in `member_id`. |
| Double booking | Checkout locks the space row (`SELECT … FOR UPDATE`) inside a transaction and re-checks for overlaps. |
| Card data | Only the last 4 digits are stored; the full number and CVV never are. |
| Error leaks | Database errors go to the PHP log; visitors see a plain message. `.htaccess` blocks direct access to `inc/` and `database/`. |

## Limitations

- Payments are simulated. Card details are validated but never charged.
- There is no admin page; lots and spaces are added through the SQL script.
- Times are fixed to New York time and to 30-minute start times.

## Team

Adonis Flores, [Teammate 2], [Teammate 3], [Teammate 4] | Lehman College, CIS 344

Licensed under MIT.
