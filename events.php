<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/helpers.php";

$action = $_GET["action"] ?? "";
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$userRole = $_SESSION["user"]["role"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();

  /* -----------------------
     MEMBER APPLY LOGIC
  ------------------------ */
  if ($action === "apply" && $id > 0) {
      $stmtUser = $pdo->prepare("SELECT email FROM users WHERE id=?");
      $stmtUser->execute([(int)$_SESSION["user"]["id"]]);
      $uEmail = $stmtUser->fetchColumn();
      $uName = $_SESSION["user"]["username"];
      
      $stmtCheck = $pdo->prepare("SELECT id FROM attendees WHERE event_id=? AND (full_name=? OR email=?)");
      $stmtCheck->execute([$id, $uName, $uEmail ?: 'N/A']);
      
      if ($stmtCheck->rowCount() > 0) {
          flash_set("You are already registered for this event.", "error");
      } else {
          $stmtIns = $pdo->prepare("INSERT INTO attendees (full_name, email, event_id, attendance_status) VALUES (?, ?, ?, 'Registered')");
          $stmtIns->execute([$uName, $uEmail ?: null, $id]);
          
          // Auto-Notification: Send confirmation email
          if ($uEmail && filter_var($uEmail, FILTER_VALIDATE_EMAIL)) {
              $eStmt = $pdo->prepare("SELECT title, event_date, location FROM events WHERE id=?");
              $eStmt->execute([$id]);
              $ev = $eStmt->fetch();
              
              $subj = "Event Registration Confirmed: " . ($ev['title'] ?? 'Church Event');
              $msg = "Dear <strong>$uName</strong>,<br><br>" .
                     "You have successfully registered for the following event at <strong>HAPPY CHURCH RUIRU</strong>:<br><br>" .
                     "📅 <strong>Event:</strong> " . e($ev['title'] ?? 'N/A') . "<br>" .
                     "🗓️ <strong>Date:</strong> " . e(format_date($ev['event_date'] ?? '')) . "<br>" .
                     "📍 <strong>Location:</strong> " . e($ev['location'] ?? 'Main Sanctuary') . "<br><br>" .
                     "We look forward to seeing you there! God bless you.";
              
              send_church_email($uEmail, $subj, $msg);
          }
          
          flash_set("Successfully registered for the event! " . ($uEmail ? "A confirmation has been sent to " . e($uEmail) : ""));

      }
      redirect("events.php");
  }

  $mode = $_POST["mode"] ?? "create";
  $title = trim((string)($_POST["title"] ?? ""));
  $event_date = (string)($_POST["event_date"] ?? "");
  $start_time = ($_POST["start_time"] ?? "") !== "" ? (string)$_POST["start_time"] : null;
  $end_time   = ($_POST["end_time"] ?? "") !== "" ? (string)$_POST["end_time"] : null;
  $location = trim((string)($_POST["location"] ?? ""));
  $category = trim((string)($_POST["category"] ?? "Church Event"));
  $status = (string)($_POST["status"] ?? "Scheduled");
  $description = trim((string)($_POST["description"] ?? ""));
  $notify_members = isset($_POST["notify_members"]) && $_POST["notify_members"] === "1";
  $notify_attendees = isset($_POST["notify_attendees"]) && $_POST["notify_attendees"] === "1";
  $notify_volunteers = isset($_POST["notify_volunteers"]) && $_POST["notify_volunteers"] === "1";

  if ($title === "" || $event_date === "" || $location === "") {
    flash_set("Please fill Title, Date and Location.", "error");
    redirect("events.php");
  }

  $image_path = $_POST["existing_image"] ?? null;
  if (isset($_FILES["event_image"]) && $_FILES["event_image"]["error"] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES["event_image"]["name"], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(8)) . "." . $ext;
    $target = __DIR__ . "/uploads/events/" . $filename;
    if (move_uploaded_file($_FILES["event_image"]["tmp_name"], $target)) {
      $image_path = "uploads/events/" . $filename;
    }
  }

  if ($mode === "update") {
    $eid = (int)($_POST["id"] ?? 0);
    $stmt = $pdo->prepare("UPDATE events SET title=?, event_date=?, start_time=?, end_time=?, location=?, category=?, status=?, description=?, image_path=? WHERE id=?");
    $stmt->execute([$title,$event_date,$start_time,$end_time,$location,$category,$status,$description,$image_path,$eid]);
    $eventId = $eid;
    flash_set("Event updated successfully.");
  } else {
    $stmt = $pdo->prepare("INSERT INTO events (title,event_date,start_time,end_time,location,category,status,description,image_path) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$title,$event_date,$start_time,$end_time,$location,$category,$status,$description,$image_path]);
    $eventId = (int)$pdo->lastInsertId();
    flash_set("Event created successfully.");
  }

  // Handle Email Notifications (for both create and update)
  if ($notify_members || $notify_attendees || $notify_volunteers) {
      // Self-healing: Ensure email column exists
      try {
          $pdo->query("SELECT email FROM users LIMIT 1");
      } catch (Exception $e) {
          $pdo->exec("ALTER TABLE users ADD COLUMN email varchar(100) DEFAULT NULL AFTER username");
          $pdo->exec("ALTER TABLE users ADD UNIQUE (email)");
      }

      // 1. Notify Members
      if ($notify_members) {
          $approvedMembers = $pdo->query("SELECT email FROM users WHERE status = 'Approved' AND email IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
          foreach ($approvedMembers as $targetEmail) {
              $emailSubject = "Church Event Update: " . $title;
              $emailBody = "Event Details: <strong>$title</strong><br><br>" .
                           "<strong>Date:</strong> $event_date<br>" .
                           "<strong>Location:</strong> $location<br><br>" .
                           "Description: " . nl2br($description);
              send_church_email($targetEmail, $emailSubject, $emailBody);
          }
      }

      // 2. Notify Attendees (Linked to this specific event)
      if ($notify_attendees && $eventId > 0) {
          $attendeeEmails = $pdo->prepare("SELECT email FROM attendees WHERE event_id = ? AND email IS NOT NULL");
          $attendeeEmails->execute([$eventId]);
          $emails = $attendeeEmails->fetchAll(PDO::FETCH_COLUMN);
          foreach ($emails as $target) {
              $subj = "Update for Event: " . $title;
              $msg = "Hello! Update regarding <strong>$title</strong>:<br><br>" .
                     "<strong>Location:</strong> $location<br><strong>Date:</strong> $event_date<br><br>" .
                     "Details: " . nl2br($description);
              send_church_email($target, $subj, $msg);
          }
      }

      // 3. Notify Volunteers
      if ($notify_volunteers) {
          $volunteerEmails = $pdo->query("SELECT email FROM volunteers WHERE email IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
          foreach ($volunteerEmails as $target) {
              $subj = "Volunteer Alert: " . $title;
              $msg = "Serving Opportunity: <strong>$title</strong><br>" .
                     "<strong>Date:</strong> $event_date | <strong>Location:</strong> $location<br><br>" .
                     "Description: " . nl2br($description);
              send_church_email($target, $subj, $msg);
          }
      }
      flash_set("Event saved and notifications sent via Gmail!");
  }

  redirect("events.php");
}

/* -----------------------
   CLEANUP PAST DATA
------------------------ */
if ($action === "cleanup" && in_array($userRole, ["admin", "Receptionist"])) {
    $stmt = $pdo->prepare("DELETE FROM events WHERE event_date < CURRENT_DATE()");
    $stmt->execute();
    $count = $stmt->rowCount();
    flash_set("Cleanup complete! Removed $count past events and their associated data.");
    redirect("events.php");
}

if ($action === "delete" && $id > 0) {
  // delete via GET (simple). For stricter security, do delete via POST.
  $pdo->prepare("DELETE FROM events WHERE id=?")->execute([$id]);
  flash_set("Event deleted.");
  redirect("events.php");
}

/* -----------------------
   EDIT FETCH
------------------------ */
$edit = null;
if ($action === "edit" && $id > 0) {
  $stmt = $pdo->prepare("SELECT * FROM events WHERE id=?");
  $stmt->execute([$id]);
  $edit = $stmt->fetch();
}

$rows = $pdo->query("SELECT * FROM events ORDER BY event_date DESC, id DESC")->fetchAll();
// --- Filters + Pagination ---
$q = trim((string)($_GET["q"] ?? ""));
$statusF = trim((string)($_GET["status"] ?? ""));
$from = trim((string)($_GET["from"] ?? ""));
$to = trim((string)($_GET["to"] ?? ""));

// Keep track of which events the current user has applied for
$appliedEvents = [];
if ($userRole === "Member" || $userRole === "user") {
    $stmtUser = $pdo->prepare("SELECT email FROM users WHERE id=?");
    $stmtUser->execute([(int)$_SESSION["user"]["id"]]);
    $uEmail = $stmtUser->fetchColumn();
    $uName = $_SESSION["user"]["username"];
    
    $stmtApp = $pdo->prepare("SELECT event_id FROM attendees WHERE full_name=? OR email=?");
    $stmtApp->execute([$uName, $uEmail ?: 'N/A']);
    $appliedEvents = $stmtApp->fetchAll(PDO::FETCH_COLUMN);
}

$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== "") {
  $where[] = "(title LIKE ? OR location LIKE ? OR category LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($statusF !== "") {
  $where[] = "status = ?";
  $params[] = $statusF;
}
if ($from !== "") {
  $where[] = "event_date >= ?";
  $params[] = $from;
}
if ($to !== "") {
  $where[] = "event_date <= ?";
  $params[] = $to;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM events $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// list rows
$stmt = $pdo->prepare("SELECT * FROM events $whereSql ORDER BY event_date DESC, id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

require_once __DIR__ . "/header.php";

?>
<div style="margin-bottom: 20px;">
  <a class="btn btn-ghost" href="dashboard.php">← Back to Dashboard</a>
</div>

<div class="grid">
  <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
    <!-- Top: Form (Full Width) -->
    <div class="col-12">
      <div class="card">
        <div style="font-weight:950; font-size:1.4rem;">
          <?= $edit ? "Edit Event" : "Create New Event" ?>
        </div>
        <div class="small" style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:10px;">
          <span>Keep the church calendar organized and inspiring.</span>
          <?php if (!$edit && in_array($userRole, ["admin", "Receptionist"])): ?>
            <?php
              $pastCount = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date < CURRENT_DATE()")->fetchColumn();
              if ($pastCount > 0):
            ?>
              <a href="events.php?action=cleanup" class="btn btn-danger" style="font-size:0.75rem; padding:8px 16px;" 
                 onclick="return confirm('CAUTION: This will delete ALL past events (<?= (int)$pastCount ?>) and all linked Attendee/Volunteer records. This cannot be undone. Proceed?');">
                🧹 Cleanup <?= (int)$pastCount ?> Past Events
              </a>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <form method="post" enctype="multipart/form-data" style="margin-top:20px; display:grid; gap:20px;">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="mode" value="<?= $edit ? "update" : "create" ?>">
          <?php if ($edit): ?>
            <input type="hidden" name="id" value="<?= (int)$edit["id"] ?>">
            <input type="hidden" name="existing_image" value="<?= e($edit["image_path"] ?? "") ?>">
          <?php endif; ?>

          <div class="grid">
            <div class="col-6">
              <label class="small">Title</label>
              <input class="input" name="title" required value="<?= e($edit["title"] ?? "") ?>" placeholder="e.g. Sunday Worship Service">
            </div>
            <div class="col-6">
              <label class="small">Location</label>
              <input class="input" name="location" required value="<?= e($edit["location"] ?? "") ?>" placeholder="e.g. Main Sanctuary">
            </div>

            <div class="col-4">
              <label class="small">Date</label>
              <div class="input-wrap">
                <input id="event_date" class="input" type="date" name="event_date" required value="<?= e($edit["event_date"] ?? "") ?>">
                <button type="button" class="input-icon" onclick="document.getElementById('event_date').showPicker?.(); document.getElementById('event_date').focus();">
                  <svg viewBox="0 0 24 24" fill="none">
                    <path d="M7 2v3M17 2v3M3.5 9h17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M6.5 5h11A3.5 3.5 0 0 1 21 8.5v11A3.5 3.5 0 0 1 17.5 23h-11A3.5 3.5 0 0 1 3 19.5v-11A3.5 3.5 0 0 1 6.5 5Z" stroke="currentColor" stroke-width="2"/>
                  </svg>
                </button>
              </div>
            </div>
            <div class="col-4">
              <label class="small">Category</label>
              <input class="input" name="category" value="<?= e($edit["category"] ?? "Church Event") ?>" placeholder="e.g. Worship, Fellowship">
            </div>
            <div class="col-4">
              <label class="small">Status</label>
              <select class="select" name="status">
                <?php
                  foreach (["Scheduled","Ongoing","Completed","Cancelled"] as $o) {
                    $sel = ($o === ($edit["status"] ?? "Scheduled")) ? "selected" : "";
                    echo "<option $sel>".e($o)."</option>";
                  }
                ?>
              </select>
            </div>

            <div class="col-3">
              <label class="small">Start Time</label>
              <input class="input" type="time" name="start_time" value="<?= e($edit["start_time"] ?? "") ?>">
            </div>
            <div class="col-3">
              <label class="small">End Time</label>
              <input class="input" type="time" name="end_time" value="<?= e($edit["end_time"] ?? "") ?>">
            </div>
            <div class="col-12">
              <label class="small">Description</label>
              <textarea class="textarea" name="description" placeholder="Event details..." style="min-height:46px;"><?= e($edit["description"] ?? "") ?></textarea>
            </div>
            <div class="col-12" style="margin-top:10px; display:flex; gap:20px; flex-wrap:wrap;">
              <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:750; font-size:0.85rem; color:var(--brand2);">
                <input type="checkbox" name="notify_members" value="1" style="width:16px; height:16px; accent-color:var(--brand);">
                <span>Members 📧</span>
              </label>
              <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:750; font-size:0.85rem; color:var(--brand2);">
                <input type="checkbox" name="notify_attendees" value="1" style="width:16px; height:16px; accent-color:var(--brand);">
                <span>Attendees 🎟️</span>
              </label>
              <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:750; font-size:0.85rem; color:var(--brand2);">
                <input type="checkbox" name="notify_volunteers" value="1" style="width:16px; height:16px; accent-color:var(--brand);">
                <span>Volunteers 🛠️</span>
              </label>
              <span class="small" style="opacity:0.7;">(Select who to notify via Gmail)</span>
            </div>
          </div>

          <div style="display:flex; gap:12px;">
            <button class="btn" type="submit" style="min-width:180px; padding:12px;"><?= $edit ? "Save Changes" : "Create Event" ?></button>
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="events.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Bottom: List (Full Width) -->
  <div class="col-12">
    <div class="card">
      <div style="font-weight:950; font-size:1.4rem;">Events List</div>
      <div class="small">Displaying upcoming and past church events.</div>

      <div style="margin-top:20px;">
        <form method="get" class="card" style="background:rgba(255,255,255,.03); border-color:rgba(255,255,255,.05); margin-bottom:20px; padding:20px;">
          <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1; min-width:200px;">
              <label class="small" style="display:block; margin-bottom:8px;">Search</label>
              <input class="input" name="q" value="<?= e($q) ?>" placeholder="Title, location...">
            </div>
            <div style="width:180px;">
              <label class="small" style="display:block; margin-bottom:8px;">Status</label>
              <select class="select" name="status">
                <option value="">All Statuses</option>
                <?php foreach (["Scheduled","Ongoing","Completed","Cancelled"] as $s): ?>
                  <option value="<?= e($s) ?>" <?= $statusF===$s?'selected':'' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:nowrap;">
              <button class="btn" type="submit" style="padding: 10px 20px;">Search</button>
              <a class="btn btn-ghost" href="events.php" style="padding: 10px 15px;">Reset</a>
              <a class="btn btn-ghost" href="events_export.php?<?= http_build_query($_GET) ?>" style="padding: 10px 15px;">CSV</a>
              <a class="btn btn-ghost" target="_blank" href="events_report.php?<?= http_build_query($_GET) ?>" style="padding: 10px 15px; white-space:nowrap;">Print Report</a>
            </div>
          </div>
        </form>

        <div style="overflow-x:auto;">
          <table class="table">
            <thead>
              <tr>
                <th>Date</th><th>Title</th><th>Location</th><th>Category</th><th>Status</th>
                <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
                  <th>Actions</th>
                <?php else: ?>
                  <th>Register</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td style="white-space:nowrap;"><?= e(format_date($r["event_date"])) ?></td>
                  <td>
                    <div style="font-weight:850;"><?= e($r["title"]) ?></div>
                    <div class="small" style="max-height:32px; overflow:hidden;"><?= e($r["description"] ?? "") ?></div>
                  </td>
                  <td><?= e($r["location"]) ?></td>
                  <td><span class="pill" style="font-size:0.7rem; margin:0;"><?= e($r["category"]) ?></span></td>
                  <td>
                     <?php
                       $color = ["Scheduled"=>"var(--brand)", "Ongoing"=>"var(--brand2)", "Completed"=>"var(--muted)", "Cancelled"=>"var(--danger)"][$r["status"]] ?? "var(--text)";
                     ?>
                     <span style="color:<?= $color ?>; font-weight:800; font-size:0.85rem;">● <?= e($r["status"]) ?></span>
                  </td>
                  <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
                    <td class="actions">
                      <a class="btn btn-ghost" href="events.php?action=edit&id=<?= (int)$r["id"] ?>">Edit</a>
                      <a class="btn btn-danger" href="events.php?action=delete&id=<?= (int)$r["id"] ?>"
                         onclick="return confirm('Delete this event?');">Delete</a>
                    </td>
                  <?php else: ?>
                    <td class="actions">
                      <?php if (in_array($r["id"], $appliedEvents)): ?>
                         <span class="pill" style="background:var(--brand); color:#fff; font-weight:800; border:none; white-space:nowrap;">✓ Registered Successfully</span>
                      <?php elseif ($r["status"] === "Scheduled" || $r["status"] === "Ongoing"): ?>
                         <form method="post" action="events.php?action=apply&id=<?= (int)$r["id"] ?>" style="display:inline;">
                             <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                             <button type="submit" class="btn" style="padding: 6px 16px; font-size: 0.8rem;" onclick="return confirm('Register for <?= e(addslashes($r["title"])) ?>?');">✋ Apply</button>
                         </form>
                      <?php else: ?>
                         <span class="small" style="color:var(--muted); font-weight:600;">Closed</span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--muted);">No events found matching your criteria.</td></tr>
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
                echo '<a class="btn btn-ghost" href="events.php?' . e(http_build_query($base)) . '">← Prev</a>';
              }
              if ($page < $totalPages) {
                $base["page"] = $page + 1;
                echo '<a class="btn btn-ghost" href="events.php?' . e(http_build_query($base)) . '">Next →</a>';
              }
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . "/footer.php"; ?>
