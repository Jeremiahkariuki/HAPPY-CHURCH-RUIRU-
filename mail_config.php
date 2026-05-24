<?php
/**
 * Mail Configuration — Gmail + Brevo Relay
 * 
 * Gmail credentials can be set via:
 *   1. config_mail_local.php (dashboard UI)
 *   2. Environment variables (Render)
 * 
 * All define() calls are guarded to prevent conflicts.
 */

// --- Gmail credentials (may already be set by config_mail_local.php) ---
if (!defined('GMAIL_USERNAME')) {
    define('GMAIL_USERNAME', getenv('GMAIL_USERNAME') ?: '');
}
if (!defined('GMAIL_PASSWORD')) {
    define('GMAIL_PASSWORD', getenv('GMAIL_PASSWORD') ?: '');
}

// --- Brevo / Generic SMTP Relay ---
if (!defined('MAIL_HOST')) {
    define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp-relay.brevo.com');
}
if (!defined('MAIL_PORT')) {
    define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 2525));
}
if (!defined('MAIL_USERNAME')) {
    define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
}
if (!defined('MAIL_PASSWORD')) {
    define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
}
if (!defined('MAIL_ENCRYPTION')) {
    define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
}

// --- Church display name (from config.php) ---
if (!defined('MAIL_FROM_NAME')) {
    $_mailCfg = require __DIR__ . '/config.php';
    define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: ($_mailCfg['app']['name'] ?? 'HAPPY CHURCH'));
    unset($_mailCfg);
}

// --- Reply-To: recipients reply to this address ---
if (!defined('MAIL_REPLY_TO')) {
    define('MAIL_REPLY_TO', getenv('MAIL_REPLY_TO') ?: (GMAIL_USERNAME ?: 'simonnjoro965@gmail.com'));
}

// --- Log file path ---
if (!defined('MAIL_LOG_FILE')) {
    define('MAIL_LOG_FILE', __DIR__ . '/logs/mail.log');
}
