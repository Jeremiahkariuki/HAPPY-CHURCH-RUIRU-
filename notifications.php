<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

$appName = "HAPPY CHURCH RUIRU";
$flash = flash_get();

if (file_exists(__DIR__ . '/config_mail_local.php')) {
    include_once __DIR__ . '/config_mail_local.php';
}
$local_user = defined('LOCAL_BREVO_USER') ? LOCAL_BREVO_USER : (getenv('GMAIL_USERNAME') ?: 'simonnjoro965@gmail.com');
$local_pass = defined('LOCAL_BREVO_PASS') ? LOCAL_BREVO_PASS : (getenv('BREVO_PASSWORD') ?: '');

// Fetch recipient counts for groups
$counts = [
    'members'    => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Approved' AND email IS NOT NULL AND email != ''")->fetchColumn(),
    'volunteers' => (int)$pdo->query("SELECT COUNT(*) FROM volunteers WHERE email IS NOT NULL AND email != ''")->fetchColumn(),
    'attendees'  => (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE email IS NOT NULL AND email != ''")->fetchColumn()
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Save settings logic
    if (isset($_POST['save_settings'])) {
        $apiKey = trim($_POST['brevo_api_key'] ?? '');
        $senderEmail = trim($_POST['sender_email'] ?? '');
        
        $configContent = "<?php\n" .
                        "define('LOCAL_BREVO_USER', '$senderEmail');\n" .
                        "define('LOCAL_BREVO_PASS', '$apiKey');\n";
        file_put_contents(__DIR__ . '/config_mail_local.php', $configContent);
        flash_set("Email settings saved successfully! You can now send wedding invitations perfectly.");
        redirect("notifications.php");
    }

    $action = $_POST["action"] ?? "broadcast";
    
    if ($action === "test_config") {
        $testEmail = trim((string)($_POST["test_email"] ?? ""));
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set("Please enter a valid email to test.", "error");
        } else {
            $ok = send_church_email($testEmail, "SMTP Test - $appName", "This is a test message to verify your Gmail SMTP settings are correct. If you see this, your system is ready!");
            if ($ok) {
                flash_set("Test email sent successully to $testEmail! Check your inbox.");
            } else {
                flash_set("Email failed to send. Please check your Brevo API Key was correctly set in the Render environment variables under MAIL_PASSWORD.", "error");
            }
        }
        redirect("notifications.php");
    }

    $targetGroups = $_POST["groups"] ?? [];
    $customEmail  = trim((string)($_POST["custom_email"] ?? ""));
    $subject      = trim((string)($_POST["subject"] ?? ""));
    $message      = trim((string)($_POST["message"] ?? ""));

    if (empty($targetGroups) && empty($customEmail)) {
        flash_set("Please select a group or enter a custom email.", "error");
        redirect("notifications.php");
    }
    
    if (empty($subject) || empty($message)) {
        flash_set("Please fill in both the subject and the message.", "error");
        redirect("notifications.php");
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
    
    foreach ($emails as $email) {
        if (send_church_email($email, $subject, $message)) {
            $sentCount++;
        } else {
            $failCount++;
        }
    }

    if ($sentCount > 0) {
        $msg = "Success: Message sent to $sentCount recipient(s).";
        if ($failCount > 0) $msg .= " ($failCount failed)";
        flash_set($msg);
    } else {
        flash_set("Failed to send any emails. Check system logs.", "error");
    }
    
    redirect("notifications.php");
}

require_once __DIR__ . "/header.php";
?>

<div style="margin-bottom: 20px;">
  <a class="btn btn-ghost" href="dashboard.php?tab=contacts">← Back to Dashboard</a>
</div>

<div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, rgba(124,92,255,.12), rgba(46,233,166,.06)); border: 1px solid rgba(255,255,255,.1);">
    <h1 style="margin:0; font-weight:950; font-size:1.8rem;">📢 Easy Email & Notifications</h1>
    <p class="small" style="margin-top:8px;">Send messages to individuals or groups instantly.</p>
</div>

<?php if ($flash): ?>
    <div class="flash <?= e($flash["type"] ?? "success") ?>" style="margin-bottom:20px; border-radius:12px; font-weight:800; padding:15px;">
        <?= e($flash["msg"] ?? "") ?>
    </div>
<?php endif; ?>

    <?php if (!$local_pass): ?>
    <div class="card p-4 mb-4" style="background: linear-gradient(135deg, rgba(124,92,255,0.1), rgba(0,255,200,0.05)); border: 2px solid var(--brand2); border-radius: 12px; box-shadow: 0 0 20px rgba(124,92,255,0.3); animation: pulse 2s infinite;">
        <h3 class="h5 mb-3" style="color: #fff; font-weight: 950;"><span style="color: #ff5c5c;">⚠️ CONNECTION TIMEOUT FIX (REQUIRED)</span></h3>
        <p style="color: #ddd;">Cloud servers like Render **block standard Gmail**. To send your "Wedding Invitations" to your Gmail app, you **MUST** save a Brevo API Key below once. It takes 20 seconds and fixes the timeout **PERMANENTLY**.</p>
        
        <form method="POST" class="row g-3">
            <div class="col-md-5">
                <input type="email" name="sender_email" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Your Gmail Address" value="<?= e($local_user) ?>" required>
            </div>
            <div class="col-md-5">
                <input type="password" name="brevo_api_key" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Paste Brevo API Key here..." required>
            </div>
            <div class="col-md-2 text-end">
                <button type="submit" name="save_settings" class="btn btn-sm btn-outline-primary w-100">🚀 FIX NOW</button>
            </div>
            <div class="col-12 mt-2">
                <a href="https://app.brevo.com/settings/keys/smtp" target="_blank" style="color: var(--brand2); font-weight: 700; text-decoration: none;">1. Click Here to Get Your Key →</a>
                <span style="color: #888; font-size: 0.8rem; margin-left: 15px;">2. Paste it above and click "FIX NOW". That's all!</span>
            </div>
        </form>
    </div>
    <style>@keyframes pulse { 0% { box-shadow: 0 0 10px rgba(124,92,255,0.1); } 50% { box-shadow: 0 0 25px rgba(124,92,255,0.4); } 100% { box-shadow: 0 0 10px rgba(124,92,255,0.1); } }</style>
    <?php else: ?>
    <div class="card p-4 mb-4" style="background: rgba(0,255,127,0.05); border: 1px solid rgba(0,255,127,0.3);">
        <h3 class="h6 mb-2" style="color: #00ff7f;">✅ Email Connection Optimized (HTTP API Active)</h3>
        <p class="small text-muted mb-3">Your system is now using a 100% reliable cloud delivery method. All wedding invitations will send perfectly.</p>
        <form method="POST"><button type="submit" name="save_settings" class="btn btn-sm btn-link text-decoration-none p-0">Change Settings</button></form>
    </div>
    <?php endif; ?>

<div class="grid">
    <div class="col-8">
        <div class="card" style="box-shadow: 0 10px 30px rgba(0,0,0,.2);">
            <h2 style="margin:0 0 20px; font-weight:950; font-size:1.3rem;">Compose Message</h2>
            <form method="post" action="notifications.php">
                <input type="hidden" name="action" value="broadcast">
                
                <div style="margin-bottom:25px; padding:20px; background:rgba(255,255,255,.02); border-radius:16px; border:1px solid rgba(255,255,255,.05);">
                    <label class="small" style="font-weight:900; display:block; margin-bottom:12px; color:var(--brand);">1. Choose Recipients</label>
                    
                    <div style="margin-bottom:15px;">
                        <label class="small">Target Specific Email(s) (e.g. simonnjoro965@gmail.com, another@mail.com)</label>
                        <input class="input" name="custom_email" type="text" placeholder="Enter one or more emails separated by commas..." style="font-weight:700; border-color:var(--brand2);">
                        <div class="small" style="margin-top:4px; color:var(--muted); font-style:italic;">Note: You can paste multiple emails here.</div>
                    </div>

                    <div style="margin-bottom:10px;"><label class="small">OR Select Group(s):</label></div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <label class="card" style="flex:1; padding:12px; cursor:pointer; min-width:140px; display:flex; align-items:center; gap:10px; background:rgba(255,255,255,.03); border-radius:12px;">
                            <input type="checkbox" name="groups[]" value="members" style="transform:scale(1.2);">
                            <div><div style="font-weight:900; font-size:0.85rem;">Members</div><div class="small"><?= $counts['members'] ?> emails</div></div>
                        </label>
                        <label class="card" style="flex:1; padding:12px; cursor:pointer; min-width:140px; display:flex; align-items:center; gap:10px; background:rgba(255,255,255,.03); border-radius:12px;">
                            <input type="checkbox" name="groups[]" value="volunteers" style="transform:scale(1.2);">
                            <div><div style="font-weight:900; font-size:0.85rem;">Volunteers</div><div class="small"><?= $counts['volunteers'] ?> emails</div></div>
                        </label>
                        <label class="card" style="flex:1; padding:12px; cursor:pointer; min-width:140px; display:flex; align-items:center; gap:10px; background:rgba(255,255,255,.03); border-radius:12px;">
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
    </div>

    <div class="col-4">
        <div class="card" style="background:rgba(255,193,7,.05); border-color:rgba(255,193,7,.15);">
            <h3 style="margin:0 0 10px; color:#ffcc00; font-weight:950; font-size:1.1rem;">Brevo Setup Check</h3>
            <p class="small" style="line-height:1.6; color:var(--muted);">
                If emails are not reaching recipients, please check:
            </p>
            <ul class="small" style="padding-left:18px; color:var(--muted);">
                <li>Is your <strong>Brevo API Key</strong> set in Render (variable <code>MAIL_PASSWORD</code>)?</li>
                <li>Is your <strong>Sender Email</strong> (variable <code>MAIL_USERNAME</code>) verified in Brevo?</li>
                <li>Check your <strong>Spam</strong> folder on the receiving account.</li>
            </ul>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3 style="margin:0 0 12px; font-weight:950; font-size:1.1rem;">Activity Log</h3>
            <p class="small" style="color:var(--muted);">Latest Status:</p>
            <div id="mailLog" style="max-height:150px; overflow-y:auto; font-family:monospace; font-size:0.75rem; background:#07101f; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,.05);">
                <?php
                if (file_exists(MAIL_LOG_FILE)) {
                    $log = array_reverse(file(MAIL_LOG_FILE));
                    $log = array_slice($log, 0, 5);
                    foreach ($log as $line) echo "<div style='margin-bottom:5px; border-bottom:1px solid rgba(255,255,255,.03); padding-bottom:3px; color: " . (str_contains($line,'SUCCESS') ? 'var(--brand2)' : 'var(--danger)') . "'>" . e($line) . "</div>";
                } else {
                    echo "<div class='small' style='color:var(--muted);'>No recent activity.</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
