# LOVE CHURCH Church Management System

A premium, role-based church management system built with PHP and MySQL.

## Features
- **Dashboard**: Advanced analytics and real-time trends using Chart.js.
- **Role-Based Access (RBAC)**:
    - **Admins/Receptionists**: Full management of Events, Volunteers, and Attendees.
    - **Members**: Can register for Events/Volunteers and view details.
- **Event Management**: Create, update, and track events with attendance stats.
- **Gallery**: Image uploads with captions.
- **Reporting**: Print-ready lists and CSV exports.
- **Email Notifications**: Broadcast messages via Gmail SMTP.

## Installation & Setup

### 1. Requirements
- **XAMPP** (or any LAMP stack with PHP 8.0+)
- **MySQL / MariaDB**

### 2. Database Initialization
1. Start XAMPP and open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Create a new database named `church_events_system`.
3. Select the database and import the `setup.sql` file provided in this directory.
4. Alternatively, visit `http://localhost/church_events_system/db_setup.php` in your browser to run the auto-migration script.

### 3. Application Configuration
- **Database Connection**: Update `db.php` or `config.php` if your database credentials differ from the XAMPP default (root/no password).
- **Email Configuration**: 
    1. Open `mail_config.php`.
    2. Enter your Gmail address in `MAIL_USERNAME`.
    3. Generate a **Google App Password** (Search "App Password" in your Google Account security settings) and enter it in `MAIL_PASSWORD`.

### 4. Running the System
1. Copy the project folder to `C:\xampp\htdocs\church_events_system`.
2. Start Apache and MySQL in XAMPP Control Panel.
3. Visit `http://localhost/church_events_system` in your browser.
4. **Default Admin Login**:
   - **Username**: `admin`
   - **Password**: `123`

## Technical Details
- **Architecture**: Modular PHP with a custom frontend for high performance.
- **Styling**: Premium Dark Theme with Glassmorphism using vanilla CSS.
- **Dependencies**: 
    - PHPMailer (included in `vendor/`)
    - Chart.js (CDN)
    - FPDF (included in `vendor/`)

## License
Confidential - For use by LOVE CHURCH.
