<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

$_cfg = require __DIR__ . "/config.php"; $appName = $_cfg["app"]["name"] ?? "HAPPY CHURCH"; unset($_cfg);
$flash = flash_get();

if (file_exists(__DIR__ . '/config_mail_local.php')) {
    include_once __DIR__ . '/config_mail_local.php';
}
$local_user = defined('GMAIL_USERNAME') ? GMAIL_USERNAME : (getenv('GMAIL_USERNAME') ?: 'simonnjoro965@gmail.com');
$local_pass = defined('GMAIL_PASSWORD') ? GMAIL_PASSWORD : (getenv('GMAIL_PASSWORD') ?: '');

function is_ajax_request(): bool {
    return (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (!empty($_POST['ajax']) && $_POST['ajax'] === '1')
    );
}

function json_response(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function send_response(bool $success, string $message, string $type = 'success', ?string $redirect = null): void {
    global $isAjax;
    if ($isAjax) {
        json_response([
            'status' => $success ? 'success' : 'error',
            'type' => $type,
            'message' => $message,
            'redirect' => $redirect,
        ]);
        // json_response calls exit, so we never reach here
    }
    if ($redirect) {
        redirect($redirect);
        // redirect calls exit, so we never reach here
    }
}

$isAjax = is_ajax_request();

function recent_log_lines(string $path, int $limit = 10): array {
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $lines = [];
    $file = new SplFileObject($path, 'r');
    $file->seek(PHP_INT_MAX);
    for ($line = $file->key(); $line >= 0 && count($lines) < $limit; $line--) {
        $file->seek($line);
        $entry = trim((string)$file->current());
        if ($entry !== '') {
            $lines[] = $entry;
        }
    }
    return $lines;
}

// Ensure database connection is active
if (!isset($pdo) || !$pdo) {
    require_once __DIR__ . "/header.php";
    echo "<div class='card p-4' style='border: 1px solid var(--danger); background: rgba(255, 77, 109, 0.05);'>";
    echo "<h2 style='color: var(--danger);'>⚠️ Database Connection Failed</h2>";
    echo "<p>Most notification features require a working database. Please check your setup in <b>db_setup.php</b>.</p>";
    echo "</div>";
    require_once __DIR__ . "/footer.php";
    exit;
}

// Ensure replies table exists
ensure_replies_table($pdo);

// Fetch recipient counts for groups with fallback
$counts = ['members' => 0, 'volunteers' => 0, 'attendees' => 0];
try {
    $counts['members']    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Approved' AND email IS NOT NULL AND email != ''")->fetchColumn();
} catch (Exception $e) { /* Table may not exist */ }
try {
    $counts['volunteers'] = (int)$pdo->query("SELECT COUNT(*) FROM volunteers WHERE email IS NOT NULL AND email != ''")->fetchColumn();
} catch (Exception $e) { /* Table may not exist */ }
try {
    $counts['attendees']  = (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE email IS NOT NULL AND email != ''")->fetchColumn();
} catch (Exception $e) { /* Table may not exist */ }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Save settings logic
    if (isset($_POST['save_settings'])) {
        $appPass = trim($_POST['gmail_app_pass'] ?? '');
        $senderEmail = trim($_POST['sender_email'] ?? '');
        
        // Only save if both values are provided
        if ($senderEmail && $appPass) {
            $configContent = "<?php\n" .
                            "// Auto-generated Gmail configuration\n" .
                            "define('GMAIL_USERNAME', " . var_export($senderEmail, true) . ");\n" .
                            "define('GMAIL_PASSWORD', " . var_export($appPass, true) . ");\n";
            file_put_contents(__DIR__ . '/config_mail_local.php', $configContent);
            flash_set("Gmail App Password saved successfully!");
            send_response(true, "Gmail App Password saved successfully!", 'success', 'notifications.php');
        } elseif (isset($_POST['reset_setup'])) {
            // User wants to change setup — just show the form again
            @unlink(__DIR__ . '/config_mail_local.php');
            flash_set("Gmail setup cleared. Please enter new credentials.", "info");
            send_response(true, "Gmail setup cleared. Please enter new credentials.", "info", 'notifications.php');
        } else {
            flash_set("Please provide both your Gmail address and App Password.", "error");
            send_response(false, "Please provide both your Gmail address and App Password.", "error");
        }
    }
    
    // Fetch replies action
    if (isset($_POST['fetch_replies'])) {
        $result = fetch_gmail_replies($pdo);
        if ($result['error']) {
            flash_set("Reply fetch error: " . $result['error'], "error");
            send_response(false, "Reply fetch error: " . $result['error'], "error");
        } elseif ($result['new'] > 0) {
            flash_set("Fetched {$result['new']} new reply(s) from your Gmail inbox!");
            send_response(true, "Fetched {$result['new']} new reply(s) from your Gmail inbox!", 'success', 'notifications.php');
        } else {
            flash_set("No new replies found. ({$result['total']} messages scanned)");
            send_response(true, "No new replies found. ({$result['total']} messages scanned)", 'success', 'notifications.php');
        }
    }
    
    // Mark reply as read
    if (isset($_POST['mark_read'])) {
        $replyId = (int)$_POST['reply_id'];
        $pdo->prepare("UPDATE email_replies SET is_read = 1 WHERE id = ?")->execute([$replyId]);
        send_response(true, "Reply marked as read.", 'success', 'notifications.php');
    }
    
    // Delete reply
    if (isset($_POST['delete_reply'])) {
        $replyId = (int)$_POST['reply_id'];
        $pdo->prepare("DELETE FROM email_replies WHERE id = ?")->execute([$replyId]);
        flash_set("Reply deleted.");
        send_response(true, "Reply deleted.", 'success', 'notifications.php');
    }

    $action = $_POST["action"] ?? "broadcast";
    
    if ($action === "test_config") { // Test email - before broadcast validation
        $testEmail = trim((string)($_POST["test_email"] ?? ""));
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $err = "Please enter a valid email to test.";
            flash_set($err, "error");
            send_response(false, $err, "error");
        } else {
            $ok = send_church_email($testEmail, "SMTP Test - $appName", "This is a test message to verify your Gmail SMTP settings are correct. If you see this, your system is ready!");
            if ($ok) {
                $msg = "Test email sent successfully to $testEmail! Check your inbox.";
                flash_set($msg);
                send_response(true, $msg, 'success');
            } else {
                $logFile = defined('MAIL_LOG_FILE') ? MAIL_LOG_FILE : __DIR__ . '/logs/mail.log';
                $lastErrors = '';
                $lines = recent_log_lines($logFile, 3);
                if ($lines) {
                    foreach ($lines as $line) {
                        if (str_contains($line, 'ERRORS:') || str_contains($line, 'FAILED')) {
                            $lastErrors .= trim($line) . ' ';
                        }
                    }
                }
                $errorMessage = "Email failed. " . ($lastErrors ? "Log: $lastErrors" : "Check the Activity Log below for details.");
                flash_set($errorMessage, "error");
                send_response(false, $errorMessage, "error");
            }
        }
    }

    $targetGroups = $_POST["groups"] ?? [];
    $customEmail  = trim((string)($_POST["custom_email"] ?? ""));
    $subject      = trim((string)($_POST["subject"] ?? ""));
    $message      = trim((string)($_POST["message"] ?? ""));

    if ($action === "broadcast") {
        if (empty($targetGroups) && empty($customEmail)) {
            flash_set("Please select a group or enter a custom email.", "error");
            send_response(false, "Please select a group or enter a custom email.", "error");
        }
        
        if (empty($subject) || empty($message)) {
            flash_set("Please fill in both the subject and the message.", "error");
            send_response(false, "Please fill in both the subject and the message.", "error");
        }

    $emails = [];
    
        // 1. Add Custom Emails if provided (supports comma-separated list)
        if ($customEmail !== "") {
            $customList = explode(",", $customEmail);
            foreach ($customList as $item) {
                $item = trim($item);
                if (filter_var($item, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $item;
                }
            }
        }
        // 2. Add Group Emails
        if (in_array("members", $targetGroups)) {
            $stmt = $pdo->query("SELECT email FROM users WHERE status = 'Approved' AND email IS NOT NULL AND email != ''");
            while ($e = $stmt->fetchColumn()) $emails[] = $e;
        }
        if (in_array("volunteers", $targetGroups)) {
            $stmt = $pdo->query("SELECT email FROM volunteers WHERE email IS NOT NULL AND email != ''");
            while ($e = $stmt->fetchColumn()) $emails[] = $e;
        }
        if (in_array("attendees", $targetGroups)) {
            $stmt = $pdo->query("SELECT email FROM attendees WHERE email IS NOT NULL AND email != ''");
            while ($e = $stmt->fetchColumn()) $emails[] = $e;
        }

            $emails = array_unique(array_filter($emails));
        $sentCount = 0;
        $failCount = 0;

        if ($isAjax) {
            // Return quickly to keep the UI responsive while the server continues sending emails.
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'success',
                'message' => 'Your message is being sent. Check the Activity Log for updates.',
                'redirect' => null,
            ]);
            if (function_exists('fastcgi_finish_request')) {
                session_write_close();
                fastcgi_finish_request();
            } else {
                session_write_close();
                ignore_user_abort(true);
                flush();
            }
        }
        
        foreach ($emails as $email) {
            if (send_church_email($email, $subject, $message)) {
                $sentCount++;
            } else {
                $failCount++;
            }
        }

        if (!$isAjax) {
            if ($sentCount > 0) {
                $msg = "Success: Message sent to $sentCount recipient(s).";
                if ($failCount > 0) $msg .= " ($failCount failed)";
                flash_set($msg);
            } else {
                flash_set("Failed to send any emails. Check system logs.", "error");
            }
            send_response(true, "Message processing complete.", "success");
        }
    }
}

// Fetch recent replies for display
$replies = [];
try {
    $replies = $pdo->query("SELECT id, from_email, from_name, subject, body, received_at, is_read FROM email_replies ORDER BY received_at DESC LIMIT 20")->fetchAll();
} catch (Exception $e) {
    // Table might not exist yet
}
$unreadCount = 0;
try {
    $unreadCount = (int)$pdo->query("SELECT COUNT(*) FROM email_replies WHERE is_read = 0")->fetchColumn();
} catch (Exception $e) {}

require_once __DIR__ . "/header.php";
?>

<div style="margin-bottom: 20px;">
  <a class="btn btn-ghost" href="dashboard.php?tab=contacts">← Back to Dashboard</a>
</div>

<div class="card notifications-hero">
    <h1 style="margin:0; font-weight:950; font-size:1.8rem;">📢 Easy Email & Notifications</h1>
    <p class="small" style="margin-top:8px;">Send messages to individuals or groups instantly.</p>
</div>

<?php if ($flash): ?>
    <div class="flash <?= e($flash["type"] ?? "success") ?>" style="margin-bottom:20px; border-radius:12px; font-weight:800; padding:15px;">
        <?= e($flash["msg"] ?? "") ?>
    </div>
<?php endif; ?>

    <?php if (!$local_pass): ?>
    <div class="card mail-setup-card">
        <h3 class="h5 mb-3" style="color: #fff; font-weight: 950;"><span style="color: #ff5c5c;">⚠️ GMAIL APP PASSWORD SETUP (REQUIRED)</span></h3>
        <p style="color: #ddd;">To ensure notifications are sent directly from your Gmail account (and visible in your Sent folder), you <strong>MUST</strong> provide a Google App Password.<br>Standard passwords will not work. See instructions below to generate a 16-character App Password.</p>
        
        <form method="POST" class="mail-setup-form" data-ajax="true">
            <div>
                <input type="email" name="sender_email" class="input" placeholder="Your Gmail Address" value="<?= e($local_user) ?>" required>
            </div>
            <div>
                <input type="password" name="gmail_app_pass" class="input" placeholder="Paste 16-char Gmail App Password here" required>
            </div>
            <div>
                <button type="submit" name="save_settings" class="btn btn-sm btn-outline-primary w-100">🚀 CONNECT</button>
            </div>
            <div class="mail-setup-help">
                <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color: var(--brand2); font-weight: 700; text-decoration: none;">1. Click Here to Get Google App Password →</a>
                <span style="color: #888; font-size: 0.8rem; margin-left: 15px;">2. Follow Google's prompts, generate it for "Mail" / "Windows Computer", and paste it above!</span>
            </div>
        </form>
    </div>
    <style>@keyframes pulse { 0% { box-shadow: 0 0 10px rgba(124,92,255,0.1); } 50% { box-shadow: 0 0 25px rgba(124,92,255,0.4); } 100% { box-shadow: 0 0 10px rgba(124,92,255,0.1); } }</style>
    <?php else: ?>
    <div class="card mail-status-card">
        <h3 class="h6 mb-2" style="color: #00ff7f;">✅ Connected to Gmail Successfully</h3>
        <p class="small text-muted mb-3">Your system is now actively sending notifications straight from your Gmail account. Messages will be visible in your Gmail 'Sent' folder.</p>
        <form method="POST" data-ajax="true">
            <input type="hidden" name="reset_setup" value="1">
            <button type="submit" name="save_settings" class="btn btn-sm btn-link text-decoration-none p-0">Change Setup</button>
        </form>
    </div>
    <?php endif; ?>

<div class="grid notifications-grid">
    <div class="col-8">
        <div class="card notification-card">
            <h2 style="margin:0 0 20px; font-weight:950; font-size:1.3rem;">Compose Message</h2>
            <form method="post" action="notifications.php" data-ajax="true">
                <input type="hidden" name="action" value="broadcast">
                
                <div class="recipient-section">
                    <label class="small" style="font-weight:900; display:block; margin-bottom:12px; color:var(--brand);">1. Choose Recipients</label>
                    
                    <div style="margin-bottom:15px;">
                        <label class="small">Target Specific Email(s) (e.g. simonnjoro965@gmail.com, another@mail.com)</label>
                        <input class="input" name="custom_email" type="text" placeholder="Enter one or more emails separated by commas..." style="font-weight:700; border-color:var(--brand2);">
                        <div class="small" style="margin-top:4px; color:var(--muted); font-style:italic;">Note: You can paste multiple emails here.</div>
                    </div>

                    <div style="margin-bottom:10px;"><label class="small">OR Select Group(s):</label></div>
                    <div class="recipient-options">
                        <label class="recipient-option">
                            <input type="checkbox" name="groups[]" value="members" style="transform:scale(1.2);">
                            <div><div style="font-weight:900; font-size:0.85rem;">Members</div><div class="small"><?= $counts['members'] ?> emails</div></div>
                        </label>
                        <label class="recipient-option">
                            <input type="checkbox" name="groups[]" value="volunteers" style="transform:scale(1.2);">
                            <div><div style="font-weight:900; font-size:0.85rem;">Volunteers</div><div class="small"><?= $counts['volunteers'] ?> emails</div></div>
                        </label>
                        <label class="recipient-option">
                            <input type="checkbox" name="groups[]" value="attendees" style="transform:scale(1.2);">
                            <div><div style="font-weight:900; font-size:0.85rem;">Attendees</div><div class="small"><?= $counts['attendees'] ?> emails</div></div>
                        </label>
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <label class="small" style="font-weight:900; color:var(--brand);">2. Message Content</label>
                    <div style="margin-top:10px;">
                        <label class="small">Subject</label>
                        <input class="input" name="subject" required placeholder="e.g. Special Invitation" style="font-weight:700;">
                    </div>
                </div>

                <div style="margin-bottom:25px;">
                    <label class="small">Message Body</label>
                    <textarea class="textarea" name="message" required rows="8" placeholder="Type your message here..." style="font-family:inherit; min-height:150px;"></textarea>
                </div>

                <button type="submit" class="btn" style="width:100%; padding:16px; background:linear-gradient(135deg, var(--brand), var(--brand2)); color:#07101f; font-weight:950; border:none; border-radius:14px; font-size:1rem; box-shadow:0 10px 30px rgba(124,92,255,.3);">
                    🚀 Send Message Now
                </button>
            </form>
        </div>

        <!-- =================== REPLY INBOX =================== -->
        <div class="card notification-card reply-inbox">
            <div class="reply-inbox-head">
                <h2 style="margin:0; font-weight:950; font-size:1.3rem;">
                    📥 Reply Inbox
                    <?php if ($unreadCount > 0): ?>
                        <span style="display:inline-block; background:var(--danger); color:#fff; font-size:0.75rem; padding:3px 10px; border-radius:999px; margin-left:8px; font-weight:900;"><?= $unreadCount ?> new</span>
                    <?php endif; ?>
                </h2>
                <form method="POST" style="margin:0;" data-ajax="true">
                    <button type="submit" name="fetch_replies" value="1" class="btn btn-sm" style="background:linear-gradient(135deg, rgba(46,233,166,.2), rgba(124,92,255,.2)); border:1px solid rgba(46,233,166,.3); color:var(--text); font-weight:800; border-radius:10px; padding:8px 18px;">
                        🔄 Fetch New Replies
                    </button>
                </form>
            </div>

            <?php if (empty($replies)): ?>
                <div style="text-align:center; padding:30px; color:var(--muted);">
                    <div style="font-size:2rem; margin-bottom:10px;">📭</div>
                    <p class="small">No replies yet. Click <strong>"Fetch New Replies"</strong> to check your Gmail inbox.</p>
                </div>
            <?php else: ?>
                <div class="reply-list">
                <?php foreach ($replies as $reply): ?>
                    <div class="reply-card" style="background:<?= $reply['is_read'] ? 'rgba(255,255,255,.02)' : 'rgba(124,92,255,.08)' ?>; border-color:<?= $reply['is_read'] ? 'rgba(255,255,255,.05)' : 'rgba(124,92,255,.2)' ?>;">
                        <div class="reply-card-inner">
                            <div class="reply-copy">
                                <div style="font-weight:900; font-size:0.95rem; color:<?= $reply['is_read'] ? 'var(--text)' : '#7c5cff' ?>;">
                                    <?php if (!$reply['is_read']): ?><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#7c5cff; margin-right:6px;"></span><?php endif; ?>
                                    <?= e($reply['from_name'] ?: $reply['from_email']) ?>
                                </div>
                                <div class="small" style="color:var(--muted); margin-top:2px;"><?= e($reply['from_email']) ?></div>
                                <div style="font-weight:800; margin-top:6px; font-size:0.9rem;"><?= e($reply['subject']) ?></div>
                                <div class="small" style="margin-top:6px; color:var(--muted); line-height:1.5; max-height:80px; overflow:hidden;">
                                    <?= e(substr($reply['body'], 0, 300)) ?><?= strlen($reply['body']) > 300 ? '...' : '' ?>
                                </div>
                            </div>
                            <div class="reply-meta">
                                <div class="small" style="color:var(--muted); font-weight:600;"><?= format_date($reply['received_at'], 'd M Y H:i') ?></div>
                                <div class="reply-actions">
                                    <?php if (!$reply['is_read']): ?>
                                    <form method="POST" style="margin:0;" data-ajax="true"><input type="hidden" name="reply_id" value="<?= $reply['id'] ?>"><button type="submit" name="mark_read" value="1" class="btn btn-sm" style="padding:4px 10px; font-size:0.7rem; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); color:var(--text); border-radius:8px;">✓ Read</button></form>
                                    <?php endif; ?>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this reply?');" data-ajax="true"><input type="hidden" name="reply_id" value="<?= $reply['id'] ?>"><button type="submit" name="delete_reply" value="1" class="btn btn-sm" style="padding:4px 10px; font-size:0.7rem; background:rgba(255,77,109,.1); border:1px solid rgba(255,77,109,.2); color:#ff4d6d; border-radius:8px;">✕</button></form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-4">
        <div class="card" style="background:rgba(255,193,7,.05); border-color:rgba(255,193,7,.15);">
            <h3 style="margin:0 0 10px; color:#ffcc00; font-weight:950; font-size:1.1rem;">Gmail Setup Check</h3>
            <p class="small" style="line-height:1.6; color:var(--muted);">
                If emails are not reaching recipients, please check:
            </p>
            <ul class="small" style="padding-left:18px; color:var(--muted);">
                <li>Did you generate and input a real <strong>Google App Password</strong>?</li>
                <li>Do you have <strong>2-Step Verification</strong> turned on for your Google Account?</li>
                <li>Check your <strong>Sent</strong> folder in Gmail to see your dispatch status.</li>
            </ul>
        </div>

        <!-- Test Email -->
        <div class="card" style="margin-top:20px; background:rgba(124,92,255,.05); border-color:rgba(124,92,255,.15);">
            <h3 style="margin:0 0 12px; font-weight:950; font-size:1.1rem;">🧪 Send Test Email</h3>
            <form method="POST" data-ajax="true">
                <input type="hidden" name="action" value="test_config">
                <input class="input" name="test_email" type="email" placeholder="Enter test email..." required style="margin-bottom:10px; font-size:0.85rem;">
                <button type="submit" class="btn btn-sm" style="width:100%; background:rgba(124,92,255,.15); border:1px solid rgba(124,92,255,.3); color:var(--text); font-weight:800; border-radius:10px;">Send Test</button>
            </form>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3 style="margin:0 0 12px; font-weight:950; font-size:1.1rem;">Activity Log</h3>
            <p class="small" style="color:var(--muted);">Latest Status:</p>
            <div id="mailLog" style="max-height:200px; overflow-y:auto; font-family:monospace; font-size:0.75rem; background:#07101f; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,.05);">
                <?php
                $logFile = defined('MAIL_LOG_FILE') ? MAIL_LOG_FILE : __DIR__ . '/logs/mail.log';
                $log = recent_log_lines($logFile, 10);
                if ($log) {
                    foreach ($log as $line) {
                        $isSuccess = str_contains($line, 'SUCCESS');
                        $isError = str_contains($line, 'ERRORS:');
                        $color = $isSuccess ? 'var(--brand2)' : ($isError ? '#ff8c00' : 'var(--danger)');
                        echo "<div style='margin-bottom:5px; border-bottom:1px solid rgba(255,255,255,.03); padding-bottom:3px; color: $color; word-break:break-all;'>" . e($line) . "</div>";
                    }
                } else {
                    echo "<div class='small' style='color:var(--muted);'>No recent activity.</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container';
    document.body.appendChild(toastContainer);

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.style.borderColor = type === 'error' ? 'rgba(255,77,109,.4)' : 'rgba(46,233,166,.35)';
        toast.textContent = message;
        toastContainer.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    function setButtonLoading(button, isLoading) {
        if (!button) return;
        if (isLoading) {
            button.classList.add('btn-loading');
            button.disabled = true;
        } else {
            button.classList.remove('btn-loading');
            button.disabled = false;
        }
    }

    document.querySelectorAll('form[data-ajax="true"]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (form.dataset.submitting === '1') return;
            form.dataset.submitting = '1';

            const submitButton = form.querySelector('button[type="submit"]');
            setButtonLoading(submitButton, true);

            const formData = new FormData(form); // @phpstan-ignore-line
            formData.set('ajax', '1');

            try {
                const response = await fetch(form.action || window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();
                if (data.status === 'success') {
                    showToast(data.message || 'Done', 'success');
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    showToast(data.message || 'Something went wrong.', 'error');
                }
            } catch (err) {
                showToast('Unable to process request. Please try again.', 'error');
            } finally {
                setButtonLoading(submitButton, false);
                form.dataset.submitting = '0';
            }
        });
    });
})();
</script>
<?php require_once __DIR__ . "/footer.php"; ?>
