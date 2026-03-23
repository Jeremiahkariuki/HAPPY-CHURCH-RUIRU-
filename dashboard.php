<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

function e2(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }

$tab = (string)($_GET["tab"] ?? "events");
$allowedTabs = ["events","volunteers","attendees","contacts","about"];
if (!in_array($tab, $allowedTabs, true)) $tab = "events";

$action = (string)($_GET["action"] ?? "");
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

$flash = "";
$edit  = null;
$rows  = [];

/* ==========================
   ADVANCED ANALYTICS (Fault-Tolerant)
========================== */
$today = date("Y-m-d");
$dbErr = false;

try {
    $eventsMonthly = $pdo->query("
      SELECT DATE_FORMAT(event_date, '%Y-%m') AS ym, COUNT(*) AS c
      FROM events
      WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
      GROUP BY ym ORDER BY ym ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $eventsMonthly = []; $dbErr = true; }

$monthLabels = []; $monthCounts = [];
foreach ($eventsMonthly as $r) { $monthLabels[] = (string)$r["ym"]; $monthCounts[] = (int)$r["c"]; }

try {
    $attStatus = $pdo->query("SELECT attendance_status AS s, COUNT(*) AS c FROM attendees GROUP BY attendance_status ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $attStatus = []; $dbErr = true; }

$attLabels = []; $attCounts = [];
foreach ($attStatus as $r) { $attLabels[] = (string)($r["s"] ?: "Unknown"); $attCounts[] = (int)$r["c"]; }

try {
    $eventsDaily30 = $pdo->query("
      SELECT DATE(event_date) AS d, COUNT(*) AS c FROM events
      WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY d ORDER BY d ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $eventsDaily30 = []; $dbErr = true; }

$dailyLabels30 = []; $dailyCounts30 = [];
foreach ($eventsDaily30 as $r) { $dailyLabels30[] = (string)$r["d"]; $dailyCounts30[] = (int)$r["c"]; }

try {
    $volsByMinistry = $pdo->query("SELECT COALESCE(NULLIF(ministry,''),'Unknown') AS m, COUNT(*) AS c FROM volunteers GROUP BY m ORDER BY c DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $volsByMinistry = []; $dbErr = true; }

$volsMinistryLabels = []; $volsMinistryCounts = [];
foreach ($volsByMinistry as $r) { $volsMinistryLabels[] = (string)$r["m"]; $volsMinistryCounts[] = (int)$r["c"]; }

try {
    $attsByEvent = $pdo->query("SELECT COALESCE(e.title,'(No Event)') AS t, COUNT(*) AS c FROM attendees a LEFT JOIN events e ON e.id=a.event_id GROUP BY t ORDER BY c DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $attsByEvent = []; $dbErr = true; }

$attsEventLabels = []; $attsEventCounts = [];
foreach ($attsByEvent as $r) { $attsEventLabels[] = (string)$r["t"]; $attsEventCounts[] = (int)$r["c"]; }

$eventsCount   = (int)($pdo->query("SELECT COUNT(*) FROM events")->fetchColumn() ?: 0);
$volsCount     = (int)($pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn() ?: 0);
$attsCount     = (int)($pdo->query("SELECT COUNT(*) FROM attendees")->fetchColumn() ?: 0);
$upcomingCount = (int)($pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()")->fetchColumn() ?: 0);
$todayCount    = (int)($pdo->query("SELECT COUNT(*) FROM events WHERE event_date = CURDATE()")->fetchColumn() ?: 0);
$completedCount= (int)($pdo->query("SELECT COUNT(*) FROM events WHERE status = 'Completed'")->fetchColumn() ?: 0);
$attendedCount = (int)($pdo->query("SELECT COUNT(*) FROM attendees WHERE attendance_status='Attended'")->fetchColumn() ?: 0);
$cancelledAtt  = (int)($pdo->query("SELECT COUNT(*) FROM attendees WHERE attendance_status='Cancelled'")->fetchColumn() ?: 0);
$attRate = ($attsCount > 0) ? round(($attendedCount / max(1, $attsCount - $cancelledAtt)) * 100, 1) : 0.0;

/* ==========================
   EVENTS CRUD
========================== */
if ($tab === "events") {
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mode = (string)($_POST["mode"] ?? "create");
    $title = trim((string)($_POST["title"] ?? ""));
    $event_date = trim((string)($_POST["event_date"] ?? ""));
    $location = trim((string)($_POST["location"] ?? ""));
    $status = trim((string)($_POST["status"] ?? "Scheduled"));

    if ($title === "" || $event_date === "" || $location === "") {
      $flash = "Please fill Title, Date and Location.";
    } else {
      if ($mode === "update") {
        $eid = (int)($_POST["id"] ?? 0);
        $pdo->prepare("UPDATE events SET title=?, event_date=?, location=?, status=? WHERE id=?")->execute([$title, $event_date, $location, $status, $eid]);
      } else {
        $pdo->prepare("INSERT INTO events (title,event_date,location,status) VALUES (?,?,?,?)")->execute([$title, $event_date, $location, $status]);
      }
      header("Location: dashboard.php?tab=events");
      exit;
    }
  }
  if ($action === "delete" && $id > 0) {
    $pdo->prepare("DELETE FROM events WHERE id=?")->execute([$id]);
    header("Location: dashboard.php?tab=events"); exit;
  }
  if ($action === "edit" && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM events WHERE id=?"); $st->execute([$id]);
    $edit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
  $rows = $pdo->query("SELECT * FROM events ORDER BY event_date DESC, id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
}

/* ==========================
   VOLUNTEERS CRUD
========================== */
if ($tab === "volunteers") {
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mode = (string)($_POST["mode"] ?? "create");
    $full_name = trim((string)($_POST["full_name"] ?? ""));
    $phone = trim((string)($_POST["phone"] ?? ""));
    $ministry = trim((string)($_POST["ministry"] ?? ""));
    $availability = trim((string)($_POST["availability"] ?? "Both"));

    if ($full_name === "" || $ministry === "") {
      $flash = "Please fill Full Name and Ministry.";
    } else {
      if ($mode === "update") {
        $vid = (int)($_POST["id"] ?? 0);
        $pdo->prepare("UPDATE volunteers SET full_name=?, phone=?, ministry=?, availability=? WHERE id=?")->execute([$full_name, $phone ?: null, $ministry, $availability, $vid]);
      } else {
        $pdo->prepare("INSERT INTO volunteers (full_name, phone, ministry, availability) VALUES (?,?,?,?)")->execute([$full_name, $phone ?: null, $ministry, $availability]);
      }
      header("Location: dashboard.php?tab=volunteers"); exit;
    }
  }
  if ($action === "delete" && $id > 0) {
    $pdo->prepare("DELETE FROM volunteers WHERE id=?")->execute([$id]);
    header("Location: dashboard.php?tab=volunteers"); exit;
  }
  if ($action === "edit" && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM volunteers WHERE id=?"); $st->execute([$id]);
    $edit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
  $rows = $pdo->query("SELECT * FROM volunteers ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
}

/* ==========================
   ATTENDEES CRUD
========================== */
$eventsList = [];
if ($tab === "attendees") {
  $eventsList = $pdo->query("SELECT id,title,event_date FROM events ORDER BY event_date DESC")->fetchAll(PDO::FETCH_ASSOC);
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mode = (string)($_POST["mode"] ?? "create");
    $full_name = trim((string)($_POST["full_name"] ?? ""));
    $phone = trim((string)($_POST["phone"] ?? ""));
    $event_id = ($_POST["event_id"] ?? "") !== "" ? (int)$_POST["event_id"] : null;
    $attendance_status = trim((string)($_POST["attendance_status"] ?? "Registered"));

    if ($full_name === "") {
      $flash = "Please enter attendee full name.";
    } else {
      if ($mode === "update") {
        $aid = (int)($_POST["id"] ?? 0);
        $pdo->prepare("UPDATE attendees SET full_name=?, phone=?, event_id=?, attendance_status=? WHERE id=?")->execute([$full_name, $phone ?: null, $event_id, $attendance_status, $aid]);
      } else {
        $pdo->prepare("INSERT INTO attendees (full_name, phone, event_id, attendance_status) VALUES (?,?,?,?)")->execute([$full_name, $phone ?: null, $event_id, $attendance_status]);
      }
      header("Location: dashboard.php?tab=attendees"); exit;
    }
  }
  if ($action === "delete" && $id > 0) {
    $pdo->prepare("DELETE FROM attendees WHERE id=?")->execute([$id]);
    header("Location: dashboard.php?tab=attendees"); exit;
  }
  if ($action === "edit" && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM attendees WHERE id=?"); $st->execute([$id]);
    $edit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
  $rows = $pdo->query("SELECT a.*, e.title AS event_title FROM attendees a LEFT JOIN events e ON e.id=a.event_id ORDER BY a.id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . "/header.php";
?>

<style>
  .hero{ border-radius:var(--radius); background:linear-gradient(135deg,rgba(124,92,255,.18),rgba(46,233,166,.10)); border:var(--border); box-shadow:var(--shadow); padding:18px; margin-bottom:14px; }
  .heroTitle{ margin:0; font-size:1.45rem; font-weight:950; letter-spacing:.45px; line-height:1.1; }
  .heroSub{ margin-top:6px; color:var(--muted); font-weight:800; }
  .miniGrid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px;}
  @media(max-width:960px){.miniGrid{grid-template-columns:repeat(2,minmax(0,1fr));}}
  @media(max-width:560px){.miniGrid{grid-template-columns:1fr;}}
  .mini{border-radius:var(--radius);border:var(--border);background:rgba(255,255,255,.04);box-shadow:var(--shadow);padding:14px;}
  .mini .k{font-weight:950;font-size:1.05rem;} .mini .s{margin-top:6px;color:var(--muted);font-weight:800;} .mini .t{margin-top:10px;font-weight:950;font-size:1.6rem;}
  .chartBox{border-radius:var(--radius);border:var(--border);background:rgba(255,255,255,.04);box-shadow:var(--shadow);padding:14px;}
  .chartHead{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px;}
  .chartTitle{font-weight:950;font-size:1.05rem;} .chartSub{color:var(--muted);font-weight:800;font-size:.9rem;}
  .canvasWrap{height:280px;} .canvasWrap canvas{width:100%!important;height:280px!important;}
  .seg{display:inline-flex;gap:8px;flex-wrap:wrap;margin-top:10px;}
  .seg button{border:var(--border);background:rgba(255,255,255,.04);color:var(--text);border-radius:999px;padding:8px 12px;cursor:pointer;font-weight:850;}
  .seg button.active{background:linear-gradient(135deg,rgba(124,92,255,.30),rgba(46,233,166,.12));border-color:rgba(124,92,255,.35);}
  .crud-form{border-radius:var(--radius);border:var(--border);background:rgba(255,255,255,.04);box-shadow:var(--shadow);padding:20px;margin-bottom:18px;}
</style>

<!-- Hero + KPIs -->
<div class="hero">
  <h1 class="heroTitle">HAPPY CHURCH RUIRU</h1>
  <div class="heroSub">Church Management Dashboard</div>
  <div class="miniGrid">
    <div class="mini"><div class="k">📅 Upcoming Events</div><div class="s">From today onwards</div><div class="t"><?= $upcomingCount ?></div></div>
    <div class="mini"><div class="k">🗓️ Today's Events</div><div class="s"><?= e2($today) ?></div><div class="t"><?= $todayCount ?></div></div>
    <div class="mini"><div class="k">✅ Attendance Rate</div><div class="s">Attended / Total</div><div class="t"><?= $attRate ?>%</div></div>
    <div class="mini"><div class="k">🏁 Completed Events</div><div class="s">Finished programs</div><div class="t"><?= $completedCount ?></div></div>
  </div>
</div>

<!-- Summary KPI Cards -->
<div class="grid">
  <div class="col-4"><div class="kpi"><div class="num"><?= $eventsCount ?></div><div class="lbl">📅 Events</div></div></div>
  <div class="col-4"><div class="kpi"><div class="num"><?= $volsCount ?></div><div class="lbl">🤝 Volunteers</div></div></div>
  <div class="col-4"><div class="kpi"><div class="num"><?= $attsCount ?></div><div class="lbl">👥 Attendees</div></div></div>

  <?php if ($flash): ?>
    <div class="col-12"><div class="flash error"><?= e2($flash) ?></div></div>
  <?php endif; ?>

  <?php if (isset($dbErr) && $dbErr): ?>
    <div class="col-12">
      <div class="flash error" style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,77,109,.12); color:#ff4d6d; border:1px solid rgba(255,77,109,.25);">
        <div>⚠️ <strong>Database Structure Outdated:</strong> Some analytics could not load because your database needs an update.</div>
        <a href="db_setup.php" class="btn" style="background:#ff4d6d; color:#fff; border:none; padding:8px 16px; font-weight:950; font-size:0.85rem;">🔧 Fix Database Now</a>
      </div>
    </div>
  <?php endif; ?>

<!-- ============================================================
     EVENTS TAB
============================================================ -->
<?php if ($tab === "events"): ?>
  <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
  <div class="col-12">
    <div class="crud-form">
      <h2 style="margin:0 0 16px; font-weight:950; font-size:1.3rem;">
        <?= $edit ? '✏️ Edit Event' : '➕ Create New Event' ?>
      </h2>
      <form method="post" action="dashboard.php?tab=events">
        <input type="hidden" name="mode" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="grid" style="gap:12px;">
          <div class="col-6">
            <label class="small" style="font-weight:800;">Event Title *</label>
            <input class="input" name="title" required placeholder="e.g. Sunday Worship" value="<?= e2($edit['title'] ?? '') ?>">
          </div>
          <div class="col-3">
            <label class="small" style="font-weight:800;">📅 Event Date *</label>
            <input class="input" type="date" name="event_date" required value="<?= e2($edit['event_date'] ?? '') ?>">
          </div>
          <div class="col-3">
            <label class="small" style="font-weight:800;">Status</label>
            <select class="select" name="status">
              <?php foreach(["Scheduled","Ongoing","Completed","Cancelled"] as $s): ?>
                <option value="<?= $s ?>" <?= ($edit['status'] ?? 'Scheduled') === $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="small" style="font-weight:800;">📍 Location *</label>
            <input class="input" name="location" required placeholder="e.g. Main Sanctuary" value="<?= e2($edit['location'] ?? '') ?>">
          </div>
          <div class="col-6" style="display:flex; align-items:flex-end; gap:10px;">
            <button type="submit" class="btn" style="padding:12px 24px; background:linear-gradient(135deg,var(--brand),var(--brand2)); color:#07101f; font-weight:950; border:none;">
              <?= $edit ? '💾 Update Event' : '➕ Create Event' ?>
            </button>
            <?php if ($edit): ?><a href="dashboard.php?tab=events" class="btn btn-ghost">Cancel</a><?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="col-8">
    <div class="chartBox">
      <div class="chartHead">
        <div>
          <div class="chartTitle">Events Trend</div>
          <div class="chartSub">Switch between 7 days, 30 days, and 6 months</div>
          <div class="seg" role="group">
            <button type="button" class="active" data-range="7">7 Days</button>
            <button type="button" data-range="30">30 Days</button>
            <button type="button" data-range="6m">6 Months</button>
          </div>
        </div>
        <div class="tag">Analytics</div>
      </div>
      <div class="canvasWrap"><canvas id="eventsLine"></canvas></div>
    </div>
  </div>
  <div class="col-4">
    <div class="chartBox">
      <div class="chartHead">
        <div><div class="chartTitle">Attendance Status</div><div class="chartSub">Distribution overview</div></div>
        <div class="tag">Live</div>
      </div>
      <div class="canvasWrap"><canvas id="attendancePie"></canvas></div>
    </div>
  </div>

  <!-- Events Records Table -->
  <div class="col-12" style="margin-top:18px;">
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
        <h2 style="margin:0; font-weight:900; font-size:1.3rem;">📋 Events Records</h2>
        <div style="display:flex; gap:8px;">
          <a href="events_report.php" class="btn" style="padding:8px 16px; font-size:0.85rem; background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.1);">🖨️ Print List</a>
          <a href="events_export.php" class="btn" style="padding:8px 16px; font-size:0.85rem; background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.1);">📥 Export CSV</a>
          <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
          <a href="events.php" class="btn btn-ghost" style="font-size:0.85rem;">Full Management →</a>
          <?php endif; ?>
        </div>
      </div>
      <div style="overflow-x:auto; margin-top:14px;">
        <table class="table">
          <thead><tr><th>Title</th><th>📅 Date</th><th>📍 Location</th><th>Status</th><?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?><th>Actions</th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td style="font-weight:800;"><?= e2($r["title"]) ?></td>
                <td style="color:var(--brand2); font-weight:700;"><?= e2($r["event_date"]) ?></td>
                <td><?= e2($r["location"]) ?></td>
                <td>
                  <?php $sc=["Completed"=>"rgba(46,233,166,0.15)","Scheduled"=>"rgba(124,92,255,0.15)","Ongoing"=>"rgba(255,193,7,0.15)","Cancelled"=>"rgba(255,77,109,0.15)"][$r["status"]] ?? "rgba(255,255,255,.06)";
                  $stc=["Completed"=>"var(--brand2)","Scheduled"=>"var(--brand)","Ongoing"=>"var(--warning)","Cancelled"=>"var(--danger)"][$r["status"]] ?? "var(--text)"; ?>
                  <span class="tag" style="background:<?= $sc ?>;color:<?= $stc ?>;"><?= e2($r["status"]) ?></span>
                </td>
                <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
                <td>
                  <div style="display:flex; gap:6px;">
                    <a href="dashboard.php?tab=events&action=edit&id=<?= $r['id'] ?>" class="btn" style="padding:5px 10px;font-size:0.75rem;">✏️ Edit</a>
                    <a href="dashboard.php?tab=events&action=delete&id=<?= $r['id'] ?>" class="btn btn-danger" style="padding:5px 10px;font-size:0.75rem;" onclick="return confirm('Delete this event?')">🗑️</a>
                  </div>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--muted);">No events yet. Create your first event above!</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ============================================================
     VOLUNTEERS TAB
============================================================ -->
<?php if ($tab === "volunteers"): ?>
  <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist", "Member"])): ?>
  <div class="col-12">
    <div class="crud-form">
      <h2 style="margin:0 0 16px; font-weight:950; font-size:1.3rem;">
        <?= $edit ? '✏️ Edit Volunteer' : '➕ Add New Volunteer' ?>
      </h2>
      <form method="post" action="dashboard.php?tab=volunteers">
        <input type="hidden" name="mode" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="grid" style="gap:12px;">
          <div class="col-4">
            <label class="small" style="font-weight:800;">Full Name *</label>
            <input class="input" name="full_name" required placeholder="e.g. John Mutua" value="<?= e2($edit['full_name'] ?? '') ?>">
          </div>
          <div class="col-4">
            <label class="small" style="font-weight:800;">📞 Phone</label>
            <input class="input" name="phone" placeholder="e.g. 0712345678" value="<?= e2($edit['phone'] ?? '') ?>">
          </div>
          <div class="col-4">
            <label class="small" style="font-weight:800;">Ministry *</label>
            <input class="input" name="ministry" required placeholder="e.g. Worship, Ushering" value="<?= e2($edit['ministry'] ?? '') ?>">
          </div>
          <div class="col-4">
            <label class="small" style="font-weight:800;">Availability</label>
            <select class="select" name="availability">
              <?php foreach(["Both","Weekdays","Weekends"] as $a): ?>
                <option value="<?= $a ?>" <?= ($edit['availability'] ?? 'Both') === $a ? 'selected' : '' ?>><?= $a ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-8" style="display:flex; align-items:flex-end; gap:10px;">
            <button type="submit" class="btn" style="padding:12px 24px; background:linear-gradient(135deg,var(--brand),var(--brand2)); color:#07101f; font-weight:950; border:none;">
              <?= $edit ? '💾 Update Volunteer' : '➕ Add Volunteer' ?>
            </button>
            <?php if ($edit): ?><a href="dashboard.php?tab=volunteers" class="btn btn-ghost">Cancel</a><?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
  <div class="col-12">
    <div class="chartBox">
      <div class="chartHead">
        <div><div class="chartTitle">Volunteers by Ministry</div><div class="chartSub">Top ministries (live from database)</div></div>
        <div class="tag">Live</div>
      </div>
      <div class="canvasWrap"><canvas id="volunteersBar"></canvas></div>
    </div>
  </div>

  <div class="col-12" style="margin-top:18px;">
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
        <h2 style="margin:0; font-weight:900; font-size:1.3rem;">📋 Volunteers Records</h2>
        <div style="display:flex; gap:8px;">
          <a href="volunteers_report.php" class="btn" style="padding:8px 16px; font-size:0.85rem; background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.1);">🖨️ Print List</a>
          <a href="volunteers_export.php" class="btn" style="padding:8px 16px; font-size:0.85rem; background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.1);">📥 Export CSV</a>
          <a href="volunteers.php" class="btn btn-ghost" style="font-size:0.85rem;">Full Management →</a>
        </div>
      </div>
      <div style="overflow-x:auto; margin-top:14px;">
        <table class="table">
          <thead><tr><th>Name</th><th>Ministry</th><th>📞 Phone</th><th>Availability</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td style="font-weight:800;"><?= e2($r["full_name"]) ?></td>
                <td><span class="tag" style="background:rgba(124,92,255,0.15);color:#c4b5ff;"><?= e2($r["ministry"]) ?></span></td>
                <td style="color:var(--brand2); font-weight:700;"><?= e2($r["phone"] ?: 'N/A') ?></td>
                <td><span class="tag" style="background:rgba(255,193,7,0.15);color:var(--warning);">📁 <?= e2($r["availability"]) ?></span></td>
                <td>
                  <div style="display:flex; gap:6px;">
                    <a href="dashboard.php?tab=volunteers&action=edit&id=<?= $r['id'] ?>" class="btn" style="padding:5px 10px;font-size:0.75rem;">✏️ Edit</a>
                    <a href="dashboard.php?tab=volunteers&action=delete&id=<?= $r['id'] ?>" class="btn btn-danger" style="padding:5px 10px;font-size:0.75rem;" onclick="return confirm('Delete this volunteer?')">🗑️</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--muted);">No volunteers yet. Add your first volunteer above!</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
<?php endif; ?>

<!-- ============================================================
     ATTENDEES TAB
============================================================ -->
<?php if ($tab === "attendees"): ?>
  <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist", "Member"])): ?>
  <div class="col-12">
    <div class="crud-form">
      <h2 style="margin:0 0 16px; font-weight:950; font-size:1.3rem;">
        <?= $edit ? '✏️ Edit Attendee' : '➕ Register New Attendee' ?>
      </h2>
      <form method="post" action="dashboard.php?tab=attendees">
        <input type="hidden" name="mode" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="grid" style="gap:12px;">
          <div class="col-4">
            <label class="small" style="font-weight:800;">Full Name *</label>
            <input class="input" name="full_name" required placeholder="e.g. Grace Wanjiru" value="<?= e2($edit['full_name'] ?? '') ?>">
          </div>
          <div class="col-4">
            <label class="small" style="font-weight:800;">📞 Phone</label>
            <input class="input" name="phone" placeholder="e.g. 0712345678" value="<?= e2($edit['phone'] ?? '') ?>">
          </div>
          <div class="col-4">
            <label class="small" style="font-weight:800;">📅 Assign to Event</label>
            <select class="select" name="event_id">
              <option value="">— No Event —</option>
              <?php foreach($eventsList as $ev): ?>
                <option value="<?= $ev['id'] ?>" <?= (($edit['event_id'] ?? '') == $ev['id']) ? 'selected' : '' ?>>
                  <?= e2($ev['title'] . ' (' . $ev['event_date'] . ')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-4">
            <label class="small" style="font-weight:800;">Attendance Status</label>
            <select class="select" name="attendance_status">
              <?php foreach(["Registered","Confirmed","Attended","Cancelled"] as $s): ?>
                <option value="<?= $s ?>" <?= ($edit['attendance_status'] ?? 'Registered') === $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-8" style="display:flex; align-items:flex-end; gap:10px;">
            <button type="submit" class="btn" style="padding:12px 24px; background:linear-gradient(135deg,var(--brand),var(--brand2)); color:#07101f; font-weight:950; border:none;">
              <?= $edit ? '💾 Update Attendee' : '➕ Register Attendee' ?>
            </button>
            <?php if ($edit): ?><a href="dashboard.php?tab=attendees" class="btn btn-ghost">Cancel</a><?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
  <div class="col-12">
    <div class="chartBox">
      <div class="chartHead">
        <div><div class="chartTitle">Attendees by Event</div><div class="chartSub">Top events by attendance (live from database)</div></div>
        <div class="tag">Live</div>
      </div>
      <div class="canvasWrap"><canvas id="attendeesBar"></canvas></div>
    </div>
  </div>

  <div class="col-12" style="margin-top:18px;">
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
        <h2 style="margin:0; font-weight:900; font-size:1.3rem;">📋 Attendees Records</h2>
        <div style="display:flex; gap:8px;">
          <a href="attendees_report.php" class="btn" style="padding:8px 16px; font-size:0.85rem; background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.1);">🖨️ Print List</a>
          <a href="attendees_export.php" class="btn" style="padding:8px 16px; font-size:0.85rem; background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.1);">📥 Export CSV</a>
          <a href="attendees.php" class="btn btn-ghost" style="font-size:0.85rem;">Full Management →</a>
        </div>
      </div>
      <div style="overflow-x:auto; margin-top:14px;">
        <table class="table">
          <thead><tr><th>Name</th><th>Event</th><th>📞 Phone</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td style="font-weight:800;"><?= e2($r["full_name"]) ?></td>
                <td style="color:#c4b5ff; font-weight:700;"><?= e2($r["event_title"] ?: 'General') ?></td>
                <td style="color:var(--brand2); font-weight:700;"><?= e2($r["phone"] ?: 'N/A') ?></td>
                <td>
                  <?php $ac=["Attended"=>"rgba(46,233,166,0.15)","Confirmed"=>"rgba(124,92,255,0.15)","Registered"=>"rgba(255,193,7,0.15)","Cancelled"=>"rgba(255,77,109,0.15)"][$r["attendance_status"]] ?? "rgba(255,255,255,.06)";
                  $atc=["Attended"=>"var(--brand2)","Confirmed"=>"var(--brand)","Registered"=>"var(--warning)","Cancelled"=>"var(--danger)"][$r["attendance_status"]] ?? "var(--text)"; ?>
                  <span class="tag" style="background:<?= $ac ?>;color:<?= $atc ?>;"><?= e2($r["attendance_status"]) ?></span>
                </td>
                <td>
                  <div style="display:flex; gap:6px;">
                    <a href="dashboard.php?tab=attendees&action=edit&id=<?= $r['id'] ?>" class="btn" style="padding:5px 10px;font-size:0.75rem;">✏️ Edit</a>
                    <a href="dashboard.php?tab=attendees&action=delete&id=<?= $r['id'] ?>" class="btn btn-danger" style="padding:5px 10px;font-size:0.75rem;" onclick="return confirm('Delete this attendee?')">🗑️</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--muted);">No attendees yet. Register your first attendee above!</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
<?php endif; ?>

<!-- ============================================================
     CONTACTS TAB
============================================================ -->
<?php if ($tab === "contacts"): ?>
  <div class="col-12">
    <div class="card" style="background:linear-gradient(135deg,rgba(124,92,255,.12),rgba(46,233,166,.06));margin-bottom:18px;">
      <h2 style="margin:0; font-weight:950; font-size:1.5rem;">📞 Contact Us</h2>
      <p class="small" style="margin-top:8px;">We are here to serve and support you. Reach out anytime.</p>
    </div>
  </div>
  <div class="col-4">
    <div class="card" style="text-align:center; padding:30px 20px;">
      <div style="width:64px;height:64px;border-radius:20px;background:rgba(124,92,255,.15);border:1px solid rgba(124,92,255,.3);display:grid;place-items:center;font-size:26px;margin:0 auto 16px;">👤</div>
      <div style="font-weight:950; font-size:1.2rem;">Church Admin</div>
      <div class="small">Operations & Logistics</div>
      <div style="font-weight:800; font-size:1.1rem; color:var(--brand2); margin:14px 0;">0715 931 990</div>
      <div style="display:flex; gap:8px;">
        <a href="tel:+254715931990" class="btn" style="flex:1;text-align:center;padding:10px;">📞 Call</a>
        <a href="https://wa.me/254715931990" target="_blank" class="btn" style="flex:1;text-align:center;padding:10px;background:rgba(37,211,102,.12);border-color:rgba(37,211,102,.25);color:#25D366;">💬 WhatsApp</a>
      </div>
    </div>
  </div>
  <div class="col-4">
    <div class="card" style="text-align:center; padding:30px 20px; border-color:rgba(124,92,255,.3);">
      <div style="width:64px;height:64px;border-radius:20px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:grid;place-items:center;font-size:26px;color:#07101f;font-weight:950;margin:0 auto 16px;">✝</div>
      <div style="font-weight:950; font-size:1.2rem;">Senior Pastor</div>
      <div class="small">Spiritual Guidance & Prayer</div>
      <div style="font-weight:800; font-size:1.1rem; color:var(--brand); margin:14px 0;">0715 931 990</div>
      <div style="display:flex; gap:8px;">
        <a href="tel:+254715931990" class="btn" style="flex:1;text-align:center;padding:10px;">📞 Call</a>
        <a href="https://wa.me/254715931990" target="_blank" class="btn" style="flex:1;text-align:center;padding:10px;background:rgba(37,211,102,.12);border-color:rgba(37,211,102,.25);color:#25D366;">💬 WhatsApp</a>
      </div>
    </div>
  </div>
  <div class="col-4">
    <div class="card" style="text-align:center; padding:30px 20px;">
      <div style="width:64px;height:64px;border-radius:20px;background:rgba(46,233,166,.15);border:1px solid rgba(46,233,166,.3);display:grid;place-items:center;font-size:26px;margin:0 auto 16px;">📞</div>
      <div style="font-weight:950; font-size:1.2rem;">Receptionist</div>
      <div class="small">Inquiries & Appointments</div>
      <div style="font-weight:800; font-size:1.1rem; color:var(--brand2); margin:14px 0;">0743 341 474</div>
      <div style="display:flex; gap:8px;">
        <a href="tel:+254743341474" class="btn" style="flex:1;text-align:center;padding:10px;">📞 Call</a>
        <a href="https://wa.me/254743341474" target="_blank" class="btn" style="flex:1;text-align:center;padding:10px;background:rgba(37,211,102,.12);border-color:rgba(37,211,102,.25);color:#25D366;">💬 WhatsApp</a>
      </div>
    </div>
  </div>
  <div class="col-12" style="margin-top:12px;">
    <a href="notifications.php" class="card" style="display:block; text-decoration:none; padding:24px; background:linear-gradient(135deg,rgba(124,92,255,.1),rgba(46,233,166,.05)); text-align:center; border: 1px solid rgba(255,255,255,.1);">
      <div style="font-size:2rem; margin-bottom:12px;">📢</div>
      <h3 style="margin:0 0 8px; font-weight:950; color:var(--text);">Send Email Broadcast</h3>
      <p class="small" style="color:var(--muted); margin-bottom:15px;">Broadcast updates to all members, volunteers, and attendees via Gmail.</p>
      <div class="btn btn-ghost" style="display:inline-block; font-size:0.85rem;">Open Notification Panel →</div>
    </a>
  </div>
<?php endif; ?>

<!-- ============================================================
     ABOUT TAB
============================================================ -->
<?php if ($tab === "about"): ?>
  <div class="col-12">
    <div class="card" style="padding:40px 24px;text-align:center;background:radial-gradient(circle at top right,rgba(124,92,255,.15),transparent),radial-gradient(circle at bottom left,rgba(46,233,166,.08),transparent),var(--card);">
      <div style="width:64px;height:64px;border-radius:20px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:grid;place-items:center;font-size:32px;color:#07101f;font-weight:950;margin:0 auto 16px;">✝</div>
      <h2 style="margin:0;font-weight:950;font-size:2rem;">HAPPY CHURCH RUIRU</h2>
      <p style="font-size:1.1rem;color:var(--brand2);font-weight:700;margin-top:8px;">Where Faith Finds a Home & Hearts Find Hope</p>
    </div>
  </div>
  <div class="col-8">
    <div class="card" style="height:100%;">
      <h3 style="margin:0 0 14px;font-weight:950;font-size:1.3rem;">Our Mission</h3>
      <p style="line-height:1.7;font-size:1rem;">At HAPPY Church RUIRU, we are dedicated to building a vibrant, Christ-centered community that empowers individuals to discover their divine purpose. Through our ministries—from vibrant worship to impactful community outreach—we strive to reflect the love of Christ in everything we do.</p>
      <div style="margin-top:18px;padding-top:14px;border-top:var(--border);font-weight:900;color:var(--brand);">"Transforming lives, one heart at a time."</div>
    </div>
  </div>
  <div class="col-4">
    <div class="card" style="height:100%;background:linear-gradient(135deg,var(--brand),#4e36f5);color:#fff;text-align:center;padding:30px 20px;">
      <div style="font-size:2.5rem;margin-bottom:14px;">📖</div>
      <div style="font-size:1.2rem;font-style:italic;font-weight:700;line-height:1.4;">"May the God of hope fill you with all joy and peace as you trust in him."</div>
      <div style="margin-top:14px;font-weight:950;color:var(--brand2);">Romans 15:13</div>
    </div>
  </div>
  <div class="col-6">
    <div class="card" style="background:rgba(124,92,255,.08);border-color:rgba(124,92,255,.15);height:100%;">
      <div style="font-size:1.5rem;margin-bottom:10px;">🕊️</div>
      <div style="font-style:italic;font-size:1rem;line-height:1.6;font-weight:500;">"For I know the plans I have for you," declares the LORD, "plans to prosper you and not to harm you, plans to give you hope and a future."</div>
      <div style="margin-top:12px;font-weight:900;color:var(--brand);">— Jeremiah 29:11</div>
    </div>
  </div>
  <div class="col-6">
    <div class="card" style="background:rgba(46,233,166,.06);border-color:rgba(46,233,166,.15);height:100%;">
      <div style="font-size:1.5rem;margin-bottom:10px;">💡</div>
      <div style="font-style:italic;font-size:1rem;line-height:1.6;font-weight:500;">"Trust in the LORD with all your heart and lean not on your own understanding; in all your ways submit to him, and he will make your paths straight."</div>
      <div style="margin-top:12px;font-weight:900;color:var(--brand2);">— Proverbs 3:5-6</div>
    </div>
  </div>
  <div class="col-6">
    <div class="card" style="background:rgba(255,193,7,.06);border-color:rgba(255,193,7,.12);height:100%;">
      <div style="font-size:1.5rem;margin-bottom:10px;">🌟</div>
      <div style="font-style:italic;font-size:1rem;line-height:1.6;font-weight:500;">"Be strong and courageous. Do not be afraid; do not be discouraged, for the LORD your God will be with you wherever you go."</div>
      <div style="margin-top:12px;font-weight:900;color:#ffcc00;">— Joshua 1:9</div>
    </div>
  </div>
  <div class="col-6">
    <div class="card" style="background:rgba(255,77,109,.05);border-color:rgba(255,77,109,.12);height:100%;">
      <div style="font-size:1.5rem;margin-bottom:10px;">❤️</div>
      <div style="font-style:italic;font-size:1rem;line-height:1.6;font-weight:500;">"And now these three remain: faith, hope and love. But the greatest of these is love."</div>
      <div style="margin-top:12px;font-weight:900;color:#ff6b8a;">— 1 Corinthians 13:13</div>
    </div>
  </div>
  <div class="col-6">
    <div class="card" style="background:rgba(124,92,255,.06);border-color:rgba(124,92,255,.12);height:100%;">
      <div style="font-size:1.5rem;margin-bottom:10px;">🙏</div>
      <div style="font-style:italic;font-size:1rem;line-height:1.6;font-weight:500;">"I can do all this through him who gives me strength."</div>
      <div style="margin-top:12px;font-weight:900;color:var(--brand);">— Philippians 4:13</div>
    </div>
  </div>
  <div class="col-6">
    <div class="card" style="background:rgba(46,233,166,.05);border-color:rgba(46,233,166,.12);height:100%;">
      <div style="font-size:1.5rem;margin-bottom:10px;">🌿</div>
      <div style="font-style:italic;font-size:1rem;line-height:1.6;font-weight:500;">"The LORD is my shepherd, I lack nothing. He makes me lie down in green pastures, he leads me beside quiet waters, he refreshes my soul."</div>
      <div style="margin-top:12px;font-weight:900;color:var(--brand2);">— Psalm 23:1-3</div>
    </div>
  </div>
  <div class="col-12">
    <div class="card" style="background:linear-gradient(135deg,rgba(124,92,255,.1),rgba(46,233,166,.05));text-align:center;padding:30px;">
      <div style="font-size:2rem;margin-bottom:12px;">📜</div>
      <div style="font-weight:950;font-size:1.1rem;color:var(--brand2);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">Daily Encouragement</div>
      <div style="font-size:1.2rem;font-style:italic;font-weight:600;line-height:1.5;max-width:600px;margin:0 auto;">"Let all things be done decently and in order."</div>
      <div style="margin-top:12px;font-weight:950;color:var(--brand);">— 1 Corinthians 14:40</div>
    </div>
  </div>
  <div class="col-4"><div class="kpi"><div class="num">1000+</div><div class="lbl">Community Members</div></div></div>
  <div class="col-4"><div class="kpi" style="background:linear-gradient(135deg,rgba(46,233,166,.2),rgba(124,92,255,.1));"><div class="num">15+</div><div class="lbl">Active Ministries</div></div></div>
  <div class="col-4"><div class="kpi"><div class="num">Weekly</div><div class="lbl">Community Outreach</div></div></div>
<?php endif; ?>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  const ChartLib = (typeof window !== "undefined" && window.Chart) ? window.Chart : null;
  const css = getComputedStyle(document.documentElement);
  const textColor = css.getPropertyValue('--text').trim() || '#ffffff';
  const muted = css.getPropertyValue('--muted').trim() || 'rgba(255,255,255,.75)';
  const baseOpts = { responsive:true, maintainAspectRatio:false, plugins:{legend:{labels:{color:textColor,font:{weight:700}}},tooltip:{enabled:true}}, scales:{x:{ticks:{color:muted},grid:{color:'rgba(255,255,255,.06)'}},y:{ticks:{color:muted},grid:{color:'rgba(255,255,255,.06)'}}} };

  const monthLabels=<?= json_encode($monthLabels) ?>, monthCounts=<?= json_encode($monthCounts) ?>;
  const dailyLabels30=<?= json_encode($dailyLabels30) ?>, dailyCounts30=<?= json_encode($dailyCounts30) ?>;
  const attLabels=<?= json_encode($attLabels) ?>, attCounts=<?= json_encode($attCounts) ?>;
  const volsMinistryLabels=<?= json_encode($volsMinistryLabels) ?>, volsMinistryCounts=<?= json_encode($volsMinistryCounts) ?>;
  const attsEventLabels=<?= json_encode($attsEventLabels) ?>, attsEventCounts=<?= json_encode($attsEventCounts) ?>;

  const CHARTS={};
  function destroyChart(k){if(CHARTS[k]){try{CHARTS[k].destroy();}catch(e){}CHARTS[k]=null;}}
  function renderIf(id,build){const el=document.getElementById(id);if(!el||!ChartLib)return null;return build(el);}
  function lastNDays(L,C,n){const s=Math.max(0,L.length-n);return{labels:L.slice(s),counts:C.slice(s)};}

  function renderEventsTrend(range){
    const el=document.getElementById('eventsLine'); if(!el||!ChartLib)return;
    destroyChart('eventsLine');
    let labels,data;
    if(range==='6m'){labels=monthLabels.length?monthLabels:['No data'];data=monthCounts.length?monthCounts:[0];}
    else if(range==='30'){labels=dailyLabels30.length?dailyLabels30:['No data'];data=dailyCounts30.length?dailyCounts30:[0];}
    else{const s=lastNDays(dailyLabels30,dailyCounts30,7);labels=s.labels.length?s.labels:['No data'];data=s.counts.length?s.counts:[0];}
    CHARTS['eventsLine']=new ChartLib(el,{type:'line',data:{labels,datasets:[{label:'Events',data,tension:.35,borderWidth:2,pointRadius:3}]},options:baseOpts});
  }
  if(document.getElementById('eventsLine')){
    renderEventsTrend('7');
    document.querySelectorAll('.seg button').forEach(btn=>{btn.addEventListener('click',()=>{document.querySelectorAll('.seg button').forEach(b=>b.classList.remove('active'));btn.classList.add('active');renderEventsTrend(btn.dataset.range);});});
  }
  renderIf('attendancePie',(el)=>{destroyChart('attendancePie');CHARTS['attendancePie']=new ChartLib(el,{type:'pie',data:{labels:attLabels.length?attLabels:['No data'],datasets:[{label:'Attendance',data:attCounts.length?attCounts:[1],borderWidth:1}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{color:textColor,font:{weight:800}}}}}});return CHARTS['attendancePie'];});
  renderIf('volunteersBar',(el)=>{destroyChart('volunteersBar');CHARTS['volunteersBar']=new ChartLib(el,{type:'bar',data:{labels:volsMinistryLabels.length?volsMinistryLabels:['No data'],datasets:[{label:'Volunteers',data:volsMinistryCounts.length?volsMinistryCounts:[0],borderWidth:1}]},options:baseOpts});return CHARTS['volunteersBar'];});
  renderIf('attendeesBar',(el)=>{destroyChart('attendeesBar');CHARTS['attendeesBar']=new ChartLib(el,{type:'bar',data:{labels:attsEventLabels.length?attsEventLabels:['No data'],datasets:[{label:'Attendees',data:attsEventCounts.length?attsEventCounts:[0],borderWidth:1}]},options:baseOpts});return CHARTS['attendeesBar'];});
</script>

<?php require_once __DIR__ . "/footer.php"; ?>