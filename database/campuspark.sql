/* ==========================================================
   CampusPark - database setup script
   CIS 344 Group Project - Fall 2026 - Lehman College

   How to use: phpMyAdmin -> Import -> pick this file -> Go
   (or in a terminal:  mysql -u root -p < database/campuspark.sql)

   Running it again wipes the database and starts fresh.
   ========================================================== */

DROP DATABASE IF EXISTS campuspark;
CREATE DATABASE campuspark CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE campuspark;


/* ----------------------------------------------------------
   members - people with an account.
   Passwords are stored as a hash made by PHP's password_hash(),
   never as plain text.
   ---------------------------------------------------------- */
CREATE TABLE members (
    member_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name     VARCHAR(50)  NOT NULL,
    last_name      VARCHAR(50)  NOT NULL,
    email          VARCHAR(120) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    joined_on      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (member_id),
    UNIQUE KEY uq_members_email (email)
) ENGINE = InnoDB;


/* ----------------------------------------------------------
   vehicles - a member can register up to 3 cars.
   A booking is made for a vehicle, not just for a person.
   ---------------------------------------------------------- */
CREATE TABLE vehicles (
    vehicle_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id     INT UNSIGNED NOT NULL,
    plate_number  VARCHAR(8)   NOT NULL,
    make_model    VARCHAR(60)  NOT NULL,
    color         VARCHAR(20)  NOT NULL,
    PRIMARY KEY (vehicle_id),
    UNIQUE KEY uq_vehicles_plate (plate_number),
    CONSTRAINT fk_vehicles_member
        FOREIGN KEY (member_id) REFERENCES members (member_id)
) ENGINE = InnoDB;


/* ----------------------------------------------------------
   lots - the parking areas on campus.
   Each lot has its own hourly price and opening hours.
   ---------------------------------------------------------- */
CREATE TABLE lots (
    lot_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lot_name        VARCHAR(60)  NOT NULL,
    location_note   VARCHAR(120) NOT NULL,
    price_per_hour  DECIMAL(5,2) NOT NULL,
    opens_at        TIME         NOT NULL,
    closes_at       TIME         NOT NULL,
    PRIMARY KEY (lot_id),
    CONSTRAINT chk_lots_hours CHECK (closes_at > opens_at),
    CONSTRAINT chk_lots_price CHECK (price_per_hour > 0)
) ENGINE = InnoDB;


/* ----------------------------------------------------------
   spaces - the individual parking spaces inside each lot.
   space_code is what is painted on the ground, e.g. "N-04".
   ---------------------------------------------------------- */
CREATE TABLE spaces (
    space_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lot_id      INT UNSIGNED NOT NULL,
    space_code  VARCHAR(10)  NOT NULL,
    space_type  ENUM('Standard', 'Compact', 'Accessible', 'EV Charging') NOT NULL DEFAULT 'Standard',
    PRIMARY KEY (space_id),
    UNIQUE KEY uq_spaces_code (lot_id, space_code),
    CONSTRAINT fk_spaces_lot
        FOREIGN KEY (lot_id) REFERENCES lots (lot_id)
) ENGINE = InnoDB;


/* ----------------------------------------------------------
   bookings - a vehicle parked in a space for a time window.
   Cancelling does NOT delete the row; booking_status changes
   to 'Cancelled' so we keep a history.
   ---------------------------------------------------------- */
CREATE TABLE bookings (
    booking_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    space_id        INT UNSIGNED NOT NULL,
    vehicle_id      INT UNSIGNED NOT NULL,
    starts_at       DATETIME     NOT NULL,
    ends_at         DATETIME     NOT NULL,
    booking_status  ENUM('Active', 'Cancelled') NOT NULL DEFAULT 'Active',
    booked_on       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (booking_id),
    CONSTRAINT fk_bookings_space
        FOREIGN KEY (space_id) REFERENCES spaces (space_id),
    CONSTRAINT fk_bookings_vehicle
        FOREIGN KEY (vehicle_id) REFERENCES vehicles (vehicle_id),
    CONSTRAINT chk_bookings_window CHECK (ends_at > starts_at),
    -- used by the "is this space free?" queries
    KEY idx_bookings_space_time (space_id, starts_at, ends_at)
) ENGINE = InnoDB;


/* ----------------------------------------------------------
   payments - exactly one payment per booking.
   Only the last 4 digits of the card are saved.
   The full card number and CVV are never stored.
   ---------------------------------------------------------- */
CREATE TABLE payments (
    payment_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id      INT UNSIGNED NOT NULL,
    amount_paid     DECIMAL(7,2) NOT NULL,
    card_name       VARCHAR(100) NOT NULL,
    card_last4      CHAR(4)      NOT NULL,
    payment_status  ENUM('Paid', 'Refunded') NOT NULL DEFAULT 'Paid',
    paid_on         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    UNIQUE KEY uq_payments_booking (booking_id),
    CONSTRAINT fk_payments_booking
        FOREIGN KEY (booking_id) REFERENCES bookings (booking_id)
) ENGINE = InnoDB;


/* ----------------------------------------------------------
   booking_details - a VIEW that joins all 6 tables together.
   receipt.php reads from it so the parking pass can show
   everything in one simple SELECT.
   ---------------------------------------------------------- */
CREATE VIEW booking_details AS
SELECT b.booking_id,
       b.starts_at,
       b.ends_at,
       b.booking_status,
       b.booked_on,
       s.space_code,
       s.space_type,
       l.lot_name,
       l.location_note,
       v.plate_number,
       v.make_model,
       v.color,
       m.member_id,
       m.first_name,
       m.last_name,
       p.amount_paid,
       p.card_last4,
       p.payment_status,
       p.paid_on
FROM bookings b
INNER JOIN spaces   s ON s.space_id   = b.space_id
INNER JOIN lots     l ON l.lot_id     = s.lot_id
INNER JOIN vehicles v ON v.vehicle_id = b.vehicle_id
INNER JOIN members  m ON m.member_id  = v.member_id
LEFT  JOIN payments p ON p.booking_id = b.booking_id;


/* ==========================================================
   Sample data
   ========================================================== */

INSERT INTO lots (lot_name, location_note, price_per_hour, opens_at, closes_at) VALUES
('North Lot',      'Behind Carman Hall, Bedford Park Blvd entrance', 3.50, '06:00:00', '22:00:00'),
('Gym Garage',     'Under the APEX building, Goulden Ave',           4.75, '07:00:00', '23:00:00'),
('Library Lot',    'Next to the Leonard Lief Library',               2.25, '08:00:00', '20:00:00');

-- North Lot: 8 spaces
INSERT INTO spaces (lot_id, space_code, space_type) VALUES
(1, 'N-01', 'Accessible'),
(1, 'N-02', 'Standard'),
(1, 'N-03', 'Standard'),
(1, 'N-04', 'Standard'),
(1, 'N-05', 'Compact'),
(1, 'N-06', 'Compact'),
(1, 'N-07', 'EV Charging'),
(1, 'N-08', 'Standard');

-- Gym Garage: 6 spaces
INSERT INTO spaces (lot_id, space_code, space_type) VALUES
(2, 'G-01', 'Accessible'),
(2, 'G-02', 'EV Charging'),
(2, 'G-03', 'EV Charging'),
(2, 'G-04', 'Standard'),
(2, 'G-05', 'Standard'),
(2, 'G-06', 'Standard');

-- Library Lot: 6 spaces
INSERT INTO spaces (lot_id, space_code, space_type) VALUES
(3, 'L-01', 'Accessible'),
(3, 'L-02', 'Standard'),
(3, 'L-03', 'Standard'),
(3, 'L-04', 'Compact'),
(3, 'L-05', 'Compact'),
(3, 'L-06', 'Standard');

-- Demo account   email: demo@campuspark.test   password: Park1234!
INSERT INTO members (first_name, last_name, email, password_hash) VALUES
('Demo', 'Student', 'demo@campuspark.test', '$2y$10$GzU2TgEvxyopkjMyRgCo1O8akDfDsB50//RdU8I0Co/KAbbSxcR1K');

INSERT INTO vehicles (member_id, plate_number, make_model, color) VALUES
(1, 'LEH344', 'Honda Civic', 'Blue');

-- A few bookings so the report page has something to show.
-- Dates are relative to the day you import the file.
INSERT INTO bookings (space_id, vehicle_id, starts_at, ends_at, booking_status) VALUES
(2,  1, CONCAT(CURDATE() - INTERVAL 6 DAY, ' 09:00:00'), CONCAT(CURDATE() - INTERVAL 6 DAY, ' 12:00:00'), 'Active'),
(9,  1, CONCAT(CURDATE() - INTERVAL 4 DAY, ' 13:00:00'), CONCAT(CURDATE() - INTERVAL 4 DAY, ' 15:00:00'), 'Active'),
(16, 1, CONCAT(CURDATE() - INTERVAL 2 DAY, ' 10:00:00'), CONCAT(CURDATE() - INTERVAL 2 DAY, ' 11:00:00'), 'Cancelled'),
(2,  1, CONCAT(CURDATE() + INTERVAL 2 DAY, ' 10:00:00'), CONCAT(CURDATE() + INTERVAL 2 DAY, ' 14:00:00'), 'Active');

INSERT INTO payments (booking_id, amount_paid, card_name, card_last4, payment_status) VALUES
(1, 10.50, 'Demo Student', '4242', 'Paid'),
(2,  9.50, 'Demo Student', '4242', 'Paid'),
(3,  2.25, 'Demo Student', '4242', 'Refunded'),
(4, 14.00, 'Demo Student', '4242', 'Paid');
