-- ============================================================
-- Vehicle Parking Management System - PostgreSQL Database Setup
-- Run with: psql -U postgres -f parking_app.sql
-- ============================================================

-- Create database (run this separately if needed):
-- CREATE DATABASE parking_app;
-- \c parking_app

-- Drop tables in correct order (to avoid FK conflicts on re-run)
DROP TABLE IF EXISTS Payment CASCADE;
DROP TABLE IF EXISTS ParkingSession CASCADE;
DROP TABLE IF EXISTS Vehicle CASCADE;
DROP TABLE IF EXISTS Slot CASCADE;
DROP TABLE IF EXISTS Users CASCADE;
DROP TABLE IF EXISTS Zone CASCADE;

-- Zone table (price_per_hour added)
CREATE TABLE Zone (
    zone_id       SERIAL PRIMARY KEY,
    name          VARCHAR(100)   NOT NULL,
    location      VARCHAR(50)    NOT NULL,
    type          VARCHAR(50),
    total_slots   INT CHECK (total_slots > 0),
    price_per_hour DECIMAL(10,2) NOT NULL DEFAULT 30.00
);

-- Slot table
CREATE TABLE Slot (
    zone_id     INT,
    slot_number VARCHAR(10),
    slot_type   VARCHAR(20),
    status      VARCHAR(20) DEFAULT 'available',
    PRIMARY KEY (zone_id, slot_number),
    FOREIGN KEY (zone_id) REFERENCES Zone(zone_id) ON DELETE CASCADE
);

-- Users table
CREATE TABLE Users (
    user_id  SERIAL PRIMARY KEY,
    name     VARCHAR(100) NOT NULL,
    phone    VARCHAR(10)  UNIQUE,
    email    VARCHAR(100) UNIQUE,
    password VARCHAR(100) NOT NULL,
    role     VARCHAR(10)  DEFAULT 'user'
);

-- Vehicle table (vehicle_type added)
CREATE TABLE Vehicle (
    vehicle_number VARCHAR(20) PRIMARY KEY,
    user_id        INT         NOT NULL,
    vehicle_type   VARCHAR(20) NOT NULL DEFAULT 'Car',
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- ParkingSession table
CREATE TABLE ParkingSession (
    session_id     SERIAL PRIMARY KEY,
    vehicle_number VARCHAR(20)  NOT NULL,
    zone_id        INT          NOT NULL,
    slot_number    VARCHAR(10)  NOT NULL,
    start_time     TIMESTAMP    NOT NULL DEFAULT NOW(),
    end_time       TIMESTAMP    NULL,
    FOREIGN KEY (vehicle_number) REFERENCES Vehicle(vehicle_number) ON DELETE CASCADE,
    FOREIGN KEY (zone_id, slot_number) REFERENCES Slot(zone_id, slot_number) ON DELETE CASCADE
);

-- Payment table
CREATE TABLE Payment (
    payment_id   SERIAL PRIMARY KEY,
    session_id   INT UNIQUE NOT NULL,
    payment_time TIMESTAMP  DEFAULT NOW(),
    amount       DECIMAL(10,2),
    mode         VARCHAR(20),
    status       VARCHAR(20),
    FOREIGN KEY (session_id) REFERENCES ParkingSession(session_id) ON DELETE CASCADE
);

-- ============================================================
-- Seed Data: Admin User
-- Password: admin123 (stored as md5 hash)
-- ============================================================
INSERT INTO Users (name, phone, email, password, role)
VALUES ('Super Admin', '9999999999', 'admin@parking.com', md5('admin123'), 'admin');

-- ============================================================
-- Seed Data: Sample Zone with Slots
-- ============================================================
INSERT INTO Zone (name, location, type, total_slots, price_per_hour)
VALUES ('Zone Alpha', 'Block A - Ground Floor', 'Covered', 10, 30.00);

INSERT INTO Slot (zone_id, slot_number, slot_type, status) VALUES
(1, 'A-01', 'Car',   'available'),
(1, 'A-02', 'Car',   'available'),
(1, 'A-03', 'Car',   'available'),
(1, 'A-04', 'Bike',  'available'),
(1, 'A-05', 'Bike',  'available'),
(1, 'A-06', 'Car',   'available'),
(1, 'A-07', 'Car',   'available'),
(1, 'A-08', 'Car',   'available'),
(1, 'A-09', 'Truck', 'available'),
(1, 'A-10', 'Truck', 'available');

-- ============================================================
-- Migration script (if upgrading an existing database):
-- ALTER TABLE Zone ADD COLUMN IF NOT EXISTS price_per_hour DECIMAL(10,2) NOT NULL DEFAULT 30.00;
-- ALTER TABLE Vehicle ADD COLUMN IF NOT EXISTS vehicle_type VARCHAR(20) NOT NULL DEFAULT 'Car';
-- ============================================================
