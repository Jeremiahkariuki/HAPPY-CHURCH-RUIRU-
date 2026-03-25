<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/helpers.php";

$action = $_GET["action"] ?? "";
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

// Self-healing: Ensure event_id column exists in volunteers table
try {
    $pdo->query("SELECT event_id FROM volunteers LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE `volunteers` ADD COLUMN `event_id` int(11) DEFAULT NULL AFTER `email` ");
        $pdo->exec("ALTER TABLE `volunteers` ADD CONSTRAINT `fk_vol_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ");
    } catch (Exception $e2) {
        // Silently fail if events table doesn't exist yet, but it should.
    }
}

/* -----------------------
   CLEANUP PAST DATA
------------------------ */
if ($action === "cleanup" && in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])) {
    $stmt = $pdo->prepare("DELETE FROM events WHERE event_date < CURRENT_DATE()");
    $stmt->execute();
    $count = $stmt->rowCount();
    flash_set("Cleanup complete! Removed $count past events and their associated records.");
    redirect("volunteers.php");
}

$events = $pdo->query("SELECT id, title, event_date FROM events ORDER BY event_date DESC")->fetchAll();

/* -----------------------
   CREATE / UPDATE
------------------------ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();
  $userRole = $_SESSION["user"]["role"] ?? "";

  $mode = $_POST["mode"] ?? "create";
  
  $full_name = trim((string)($_POST["full_name"] ?? ""));
  $email = trim((string)($_POST["email"] ?? ""));
  $phone = trim((string)($_POST["phone"] ?? ""));
  $ministry = trim((string)($_POST["ministry"] ?? ""));
  $event_id = ($_POST["event_id"] ?? "") !== "" ? (int)$_POST["event_id"] : null;
  $availability = (string)($_POST["availability"] ?? "Both");
  $notes = trim((string)($_POST["notes"] ?? ""));

  if ($full_name === "" || $ministry === "") {
    flash_set("Please fill Full Name and Ministry.", "error");
    redirect("volunteers.php");
  }

  if ($mode === "update") {
    // Access control removed per open-directory requirement
    $vid = (int)($_POST["id"] ?? 0);
    $stmt = $pdo->prepare("UPDATE volunteers SET full_name=?, phone=?, email=?, event_id=?, ministry=?, availability=?, notes=? WHERE id=?");
    $stmt->execute([$full_name, $phone ?: null, $email ?: null, $event_id, $ministry, $availability, $notes, $vid]);
    flash_set("Volunteer updated.");
  } else {
    $stmt = $pdo->prepare("INSERT INTO volunteers (full_name, phone, email, event_id, ministry, availability, notes) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$full_name, $phone ?: null, $email ?: null, $event_id, $ministry, $availability, $notes]);
    
    // Auto-Notification: Send confirmation email if email is provided
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $eventTitle = "General Ministry";
        if ($event_id > 0) {
            $eStmt = $pdo->prepare("SELECT title, event_date, location FROM events WHERE id=?");
            $eStmt->execute([$event_id]);
            $evData = $eStmt->fetch();
            if ($evData) {
                $eventTitle = $evData["title"] . " (" . format_date($evData["event_date"]) . ")";
            }
        }
        
        $subj = "Volunteer Registration Successful - HAPPY CHURCH RUIRU";
        $msg = "Dear <strong>$full_name</strong>,<br><br>" .
               "Thank you for registering to serve as a volunteer at <strong>HAPPY CHURCH RUIRU</strong>!<br><br>" .
               "<strong>Serving Area:</strong> $ministry<br>" .
               "<strong>Event/Assignment:</strong> $eventTitle<br>" .
               "<strong>Availability:</strong> $availability<br><br>" .
               "We are excited to have you on the team. God bless you as you serve!";
        
        send_church_email($email, $subj, $msg);
    }

    flash_set("Volunteer added successfully! " . ($email ? "A confirmation has been sent to " . e($email) : ""));

  }

  redirect("volunteers.php");
}

/* -----------------------
   DELETE
------------------------ */
if ($action === "delete" && $id > 0) {
  $pdo->prepare("DELETE FROM volunteers WHERE id=?")->execute([$id]);
  flash_set("Volunteer deleted.");
  redirect("volunteers.php");
}

/* -----------------------
   EDIT LOAD
------------------------ */
$edit = null;
if ($action === "edit" && $id > 0) {
  $stmt = $pdo->prepare("SELECT * FROM volunteers WHERE id=?");
  $stmt->execute([$id]);
  $edit = $stmt->fetch();
}

// Get logged-in member's email for pre-filling
$myEmail = "";
if (!in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])) {
    $myEmailStmt = $pdo->prepare("SELECT email FROM users WHERE id=?");
    $myEmailStmt->execute([(int)$_SESSION["user"]["id"]]);
    $myEmail = $myEmailStmt->fetchColumn() ?: "";
}


/* -----------------------
   FILTERS + PAGINATION
------------------------ */
$q = trim((string)($_GET["q"] ?? ""));
$ministryF = trim((string)($_GET["ministry"] ?? ""));
$availF = trim((string)($_GET["availability"] ?? ""));

$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== "") {
  $where[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($ministryF !== "") {
  $where[] = "ministry LIKE ?";
  $params[] = "%$ministryF%";
}
if ($availF !== "") {
  $where[] = "availability = ?";
  $params[] = $availF;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM volunteers $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// list rows
$stmt = $pdo->prepare("
  SELECT v.*, e.title as event_title, e.event_date
  FROM volunteers v
  LEFT JOIN events e ON v.event_id = e.id
  $whereSql ORDER BY e.event_date DESC, v.id DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Fetch "My Selections" for Members
$mySelections = [];
if (!in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])) {
    $stmtApp = $pdo->prepare("SELECT v.*, e.title as event_title, e.event_date
                              FROM volunteers v
                              LEFT JOIN events e ON v.event_id = e.id
                              WHERE v.full_name=? OR v.email=?
                              ORDER BY e.event_date DESC, v.id DESC");
    $stmtApp->execute([$_SESSION["user"]["username"], $myEmail ?: 'N/A']);
    $mySelections = $stmtApp->fetchAll();
}

/* -----------------------
   UI
------------------------ */
require_once __DIR__ . "/header.php";

?>

<div style="margin-bottom: 20px;">
  <a class="btn btn-ghost" href="dashboard.php">← Back to Dashboard</a>
</div>

<div class="grid">
  <?php 
    $isAdmin = true; // Overridden to grant Members identical Admin UI experience
    $showForm = true;
  ?>
  
  <?php if ($showForm): ?>
    <!-- Top: Form (Full Width) -->
    <div class="col-12">
      <div class="card">
        <div style="font-weight:950; font-size:1.4rem;">
          <?php if (!$isAdmin): ?>
            Volunteer Registration
          <?php else: ?>
            Edit Volunteer
          <?php endif; ?>
        </div>
        <div class="small" style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:10px;">
          <span><?= !$isAdmin ? "Serve the church community by registering your availability." : "Update the selected volunteer's details." ?></span>

          <?php if (!$edit && $isAdmin): ?>
            <?php
              $pastCount = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date < CURRENT_DATE()")->fetchColumn();
            ?>
            <?php if ($pastCount > 0): ?>
              <a href="volunteers.php?action=cleanup" class="btn btn-danger" style="font-size:0.7rem; padding:6px 12px;" 
                 onclick="return confirm('Note: This will delete ALL past events (<?= (int)$pastCount ?>) and linked participants. Proceed?');">
                🧹 Cleanup <?= (int)$pastCount ?> Past Events
              </a>
            <?php endif; ?>
          <?php endif; ?>

        </div>

        <form method="post" style="margin-top:20px; display:grid; gap:20px;">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="mode" value="<?= $edit ? "update" : "create" ?>">
          <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit["id"] ?>"><?php endif; ?>

          <div class="grid">
            <div class="col-6">
              <label class="small">Full Name</label>
              <?php
                if ($edit) { $defName = $edit["full_name"]; }
                elseif (!in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])) { $defName = $_SESSION["user"]["username"]; }
                else { $defName = ""; }
              ?>
              <input class="input" name="full_name" required value="<?= e($defName) ?>" placeholder="e.g. John Mwangi">
            </div>
            
            <div class="col-6">
              <label class="small">Ministry / Department</label>
              <input class="input" name="ministry" required value="<?= e($edit["ministry"] ?? "") ?>" placeholder="Choir, Media, Ushering...">
            </div>

            <div class="col-4">
              <label class="small">Phone</label>
              <input class="input" name="phone" value="<?= e($edit["phone"] ?? "") ?>" placeholder="e.g. 0712...">
            </div>
            
            <div class="col-4">
              <label class="small">Email</label>
              <?php
                if ($edit) { $defEmail = $edit["email"]; }
                elseif (!in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])) { $defEmail = $myEmail; }
                else { $defEmail = ""; }
              ?>
              <input class="input" type="email" name="email" value="<?= e($defEmail) ?>" placeholder="e.g. name@email.com">
            </div>
         
            <div class="col-4">
              <label class="small">Availability</label>
              <select class="select" name="availability">
                <?php
                  foreach (["Weekdays","Weekends","Both"] as $o) {
                    $sel = ($o === ($edit["availability"] ?? "Both")) ? "selected" : "";
                    echo "<option $sel>".e($o)."</option>";
                  }
                ?>
              </select>
            </div>
            <div class="col-12">
              <label class="small">Assign to Event</label>
              <select class="select" name="event_id">
                <option value="">(General Volunteer - No specific event)</option>
                <?php
                  $curEv = $edit["event_id"] ?? "";
                  foreach ($events as $ev) {
                    $sel = ((string)$ev["id"] === (string)$curEv) ? "selected" : "";
                    echo "<option value='".(int)$ev["id"]."' $sel>".e($ev["title"]." • ".$ev["event_date"])."</option>";
                  }
                ?>
              </select>
            </div>

            <div class="col-12">
              <label class="small">Notes</label>
              <textarea class="textarea" name="notes" placeholder="Extra notes..." style="min-height:46px;"><?= e($edit["notes"] ?? "") ?></textarea>
            </div>
          </div>

          <div style="display:flex; gap:12px;">
            <button class="btn" type="submit" style="min-width:180px; padding:12px;">
                <?php if (!$isAdmin): ?>
                  Register to Serve
                <?php else: ?>
                  Save Changes
                <?php endif; ?>
            </button>
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="volunteers.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
    
  <?php if (!$isAdmin): ?>
    <!-- Top: My Selections (Full Width) -->

    <?php if (count($mySelections) > 0): ?>
    <div class="col-12" style="margin-top: 20px;">
      <div class="card">
        <div style="font-weight:950; font-size:1.4rem; color:var(--brand2);">My Volunteer Selections</div>
        <div class="small" style="margin-bottom:20px;">You have registered to serve in the following areas.</div>
        <div style="overflow-x:auto;">
          <table class="table">
            <thead>
              <tr>
                <th>Ministry</th><th>Phone / Email</th><th>Event</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($mySelections as $r): ?>
                <tr>
                  <td><span class="pill" style="font-size:0.75rem; margin:0;"><?= e($r["ministry"]) ?></span></td>
                  <td class="small">
                    <?php if ($r["phone"]): ?>📞 <?= e($r["phone"]) ?><br><?php endif; ?>
                    <span style="opacity:0.8;"><?= e($r["email"] ?: "-") ?></span>
                  </td>
                  <td class="small">
                    <span style="font-weight:700; color:var(--brand);"><?= e($r["event_title"] ?: "General Ministry") ?> <?= $r["event_date"] ? "• ".e(format_date($r["event_date"])) : "" ?></span>
                  </td>
                  <td>
                    <span style="font-weight:800; font-size:0.85rem; color:var(--brand); border-radius: 6px; padding: 4px 8px; background: rgba(46,233,166,0.1);">✓ Registered Successfully</span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($isAdmin): ?>
  <!-- Bottom: List (Full Width) -->
  <div class="col-12">
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:flex-end;">
          <div>
              <div style="font-weight:950; font-size:1.4rem;">Volunteers List</div>
              <div class="small">Search, filter, and manage church serving teams.</div>
          </div>
          <?php
            $pastCount = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date < CURRENT_DATE()")->fetchColumn();
          ?>
          <?php if ($pastCount > 0): ?>
            <a href="volunteers.php?action=cleanup" class="btn btn-danger" style="font-size:0.75rem; padding:8px 16px;" 
               onclick="return confirm('Note: This will delete ALL past events (<?= (int)$pastCount ?>) and linked participants. Proceed?');">
              🧹 Cleanup <?= (int)$pastCount ?> Past Events
            </a>
          <?php endif; ?>
      </div>


      <div style="margin-top:20px;">
        <form method="get" class="card" style="background:rgba(255,255,255,.03); border-color:rgba(255,255,255,.05); margin-bottom:20px; padding:20px;">
          <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1; min-width:200px;">
              <label class="small" style="display:block; margin-bottom:8px;">Search</label>
              <input class="input" name="q" value="<?= e($q) ?>" placeholder="Name, phone, email...">
            </div>
            <div style="width:180px;">
              <label class="small" style="display:block; margin-bottom:8px;">Availability</label>
              <select class="select" name="availability">
                <option value="">All Availabilities</option>
                <?php foreach (["Weekdays","Weekends","Both"] as $a): ?>
                  <option value="<?= e($a) ?>" <?= $availF===$a?'selected':'' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:nowrap;">
              <button class="btn" type="submit" style="padding: 10px 20px;">Search</button>
              <a class="btn btn-ghost" href="volunteers.php" style="padding: 10px 15px;">Reset</a>
              <a class="btn btn-ghost" href="volunteers_export.php?<?= e(http_build_query($_GET)) ?>" style="padding: 10px 15px;">CSV</a>
              <a class="btn btn-ghost" target="_blank" href="volunteers_report.php?<?= e(http_build_query($_GET)) ?>" style="padding: 10px 15px; white-space:nowrap;">Print List</a>
            </div>
          </div>
        </form>

        <div style="overflow-x:auto;">
          <table class="table">
            <thead>
              <tr>
                <th>Name</th><th>Ministry</th><th>Phone / Email</th><th>Event</th><th>Availability</th>
                <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
                  <th>Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td style="font-weight:700;"><?= e($r["full_name"]) ?></td>
                  <td><span class="pill" style="font-size:0.75rem; margin:0;"><?= e($r["ministry"]) ?></span></td>
                  <td class="small">
                    <?php if ($r["phone"]): ?>
                      <a href="tel:<?= e($r["phone"]) ?>" style="color:var(--brand2); font-weight:700; display:flex; align-items:center; gap:6px;">
                        📞 <?= e($r["phone"]) ?>
                      </a>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                    <div style="margin-top:4px; opacity:0.8;"><?= e($r["email"] ?: "-") ?></div>
                  </td>
                  <td class="small">
                    <span style="font-weight:700; color:var(--brand);"><?= e($r["event_title"] ?: "General") ?> <?= $r["event_date"] ? "• ".e(format_date($r["event_date"])) : "" ?></span>
                  </td>
                  <td>
                    <span style="font-weight:800; font-size:0.85rem; color:var(--brand); border-radius: 6px; padding: 4px 8px; background: rgba(46,233,166,0.1);">✓ Registered Successfully</span>
                  </td>
                  <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
                    <td class="actions">
                      <a class="btn btn-ghost" href="volunteers.php?action=edit&id=<?= (int)$r["id"] ?>">Edit</a>
                      <a class="btn btn-danger" href="volunteers.php?action=delete&id=<?= (int)$r["id"] ?>"
                         onclick="return confirm('Delete this volunteer?');">Delete</a>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--muted);">No volunteers found matching your criteria.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;">
          <div class="small">Page <?= $page ?>/<?= $totalPages ?> (Total: <?= $total ?>)</div>
          <div style="display:flex; gap:10px;">
            <?php
              $base = $_GET;
              if ($page > 1) {
                $base["page"] = $page - 1;
                echo '<a class="btn btn-ghost" href="volunteers.php?' . e(http_build_query($base)) . '">← Prev</a>';
              }
              if ($page < $totalPages) {
                $base["page"] = $page + 1;
                echo '<a class="btn btn-ghost" href="volunteers.php?' . e(http_build_query($base)) . '">Next →</a>';
              }
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>


<?php require_once __DIR__ . "/footer.php"; ?>
