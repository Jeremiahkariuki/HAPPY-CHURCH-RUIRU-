<?php
/**
 * Gmail SMTP Configuration
 * 
 * To send real emails via Gmail:
 * 1. Go to your Google Account > Security.
 * 2. Enable 2-Step Verification.
 * 3. Search for "App Passwords".
 * 4. Generate a new password for "Mail" on your "Windows Computer".
 * 5. Paste that 16-character password below.
 */

/**
 * Brevo SMTP Configuration (Professional Relay)
 */
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp-relay.brevo.com');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 2525)); // Port 2525 is often more stable on cloud platforms like Render
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'simonnjoro965@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'Sy.123456789.'); // Replace with Brevo API Key on Render
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'HAPPY CHURCH RUIRU');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');

// Log setting
define('MAIL_LOG_FILE', __DIR__ . '/logs/mail.log');
