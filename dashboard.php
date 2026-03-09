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
   ADVANCED ANALYTICS (for charts)
========================== */
$today = date("Y-m-d");

/** Events per month (last 6 months) */
$eventsMonthly = $pdo->query("
  SELECT DATE_FORMAT(event_date, '%Y-%m') AS ym, COUNT(*) AS c
  FROM events
  WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
  GROUP BY ym
  ORDER BY ym ASC
")->fetchAll(PDO::FETCH_ASSOC);

$monthLabels = [];
$monthCounts = [];
foreach ($eventsMonthly as $r) {
  $monthLabels[] = (string)$r["ym"];
  $monthCounts[] = (int)$r["c"];
}

/** Attendance status distribution (pie) */
$attStatus = $pdo->query("
  SELECT attendance_status AS s, COUNT(*) AS c
  FROM attendees
  GROUP BY attendance_status
  ORDER BY c DESC
")->fetchAll(PDO::FETCH_ASSOC);

$attLabels = [];
$attCounts = [];
foreach ($attStatus as $r) {
  $attLabels[] = (string)($r["s"] ?: "Unknown");
  $attCounts[] = (int)$r["c"];
}

/** Event status distribution (bar) */
$eventStatus = $pdo->query("
  SELECT status AS s, COUNT(*) AS c
  FROM events
  GROUP BY status
  ORDER BY c DESC
")->fetchAll(PDO::FETCH_ASSOC);

$eventStatusLabels = [];
$eventStatusCounts = [];
foreach ($eventStatus as $r) {
  $eventStatusLabels[] = (string)($r["s"] ?: "Unknown");
  $eventStatusCounts[] = (int)$r["c"];
}

/* ==========================
   NEW: Events trend by day for filters (7/30 days)
========================== */
$eventsDaily30 = $pdo->query("
  SELECT DATE(event_date) AS d, COUNT(*) AS c
  FROM events
  WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  GROUP BY d
  ORDER BY d ASC
")->fetchAll(PDO::FETCH_ASSOC);

$dailyLabels30 = [];
$dailyCounts30 = [];
foreach ($eventsDaily30 as $r) {
  $dailyLabels30[] = (string)$r["d"];
  $dailyCounts30[] = (int)$r["c"];
}

/* ==========================
   NEW: Volunteers analytics (Volunteers by Ministry)
========================== */
$volsByMinistry = $pdo->query("
  SELECT COALESCE(NULLIF(ministry,''),'Unknown') AS m, COUNT(*) AS c
  FROM volunteers
  GROUP BY m
  ORDER BY c DESC
  LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$volsMinistryLabels = [];
$volsMinistryCounts = [];
foreach ($volsByMinistry as $r) {
  $volsMinistryLabels[] = (string)$r["m"];
  $volsMinistryCounts[] = (int)$r["c"];
}

/* ==========================
   NEW: Attendees analytics (Attendees by Event)
========================== */
$attsByEvent = $pdo->query("
  SELECT COALESCE(e.title,'(No Event)') AS t, COUNT(*) AS c
  FROM attendees a
  LEFT JOIN events e ON e.id = a.event_id
  GROUP BY t
  ORDER BY c DESC
  LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$attsEventLabels = [];
$attsEventCounts = [];
foreach ($attsByEvent as $r) {
  $attsEventLabels[] = (string)$r["t"];
  $attsEventCounts[] = (int)$r["c"];
}

/** Modern KPI analytics */
$eventsCount   = (int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$volsCount     = (int)$pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn();
$attsCount     = (int)$pdo->query("SELECT COUNT(*) FROM attendees")->fetchColumn();
$upcomingCount = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()")->fetchColumn();
$todayCount    = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE event_date = CURDATE()")->fetchColumn();
$completedCount= (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status = 'Completed'")->fetchColumn();

$attendedCount = (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE attendance_status='Attended'")->fetchColumn();
$confirmedCount= (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE attendance_status='Confirmed'")->fetchColumn();
$regCount      = (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE attendance_status='Registered'")->fetchColumn();

$attRate = ($attsCount > 0) ? round(($attendedCount / max(1, $attsCount - (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE attendance_status='Cancelled'")->fetchColumn())) * 100, 1) : 0.0;

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
        $pdo->prepare("UPDATE events SET title=?, event_date=?, location=?, status=? WHERE id=?")
            ->execute([$title, $event_date, $location, $status, $eid]);
      } else {
        $pdo->prepare("INSERT INTO events (title,event_date,location,status) VALUES (?,?,?,?)")
            ->execute([$title, $event_date, $location, $status]);
      }
      header("Location: dashboard.php?tab=events");
      exit;
    }
  }

  if ($action === "delete" && $id > 0) {
    $pdo->prepare("DELETE FROM events WHERE id=?")->execute([$id]);
    header("Location: dashboard.php?tab=events");
    exit;
  }

  if ($action === "edit" && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM events WHERE id=?");
    $st->execute([$id]);
    $edit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  $rows = $pdo->query("SELECT * FROM events ORDER BY event_date DESC, id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
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
        $pdo->prepare("UPDATE volunteers SET full_name=?, phone=?, ministry=?, availability=? WHERE id=?")
            ->execute([$full_name, $phone ?: null, $ministry, $availability, $vid]);
      } else {
        $pdo->prepare("INSERT INTO volunteers (full_name, phone, ministry, availability) VALUES (?,?,?,?)")
            ->execute([$full_name, $phone ?: null, $ministry, $availability]);
      }
      header("Location: dashboard.php?tab=volunteers");
      exit;
    }
  }

  if ($action === "delete" && $id > 0) {
    $pdo->prepare("DELETE FROM volunteers WHERE id=?")->execute([$id]);
    header("Location: dashboard.php?tab=volunteers");
    exit;
  }

  if ($action === "edit" && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM volunteers WHERE id=?");
    $st->execute([$id]);
    $edit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  $rows = $pdo->query("SELECT * FROM volunteers ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
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
        $pdo->prepare("UPDATE attendees SET full_name=?, phone=?, event_id=?, attendance_status=? WHERE id=?")
            ->execute([$full_name, $phone ?: null, $event_id, $attendance_status, $aid]);
      } else {
        $pdo->prepare("INSERT INTO attendees (full_name, phone, event_id, attendance_status) VALUES (?,?,?,?)")
            ->execute([$full_name, $phone ?: null, $event_id, $attendance_status]);
      }
      header("Location: dashboard.php?tab=attendees");
      exit;
    }
  }

  if ($action === "delete" && $id > 0) {
    $pdo->prepare("DELETE FROM attendees WHERE id=?")->execute([$id]);
    header("Location: dashboard.php?tab=attendees");
    exit;
  }

  if ($action === "edit" && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM attendees WHERE id=?");
    $st->execute([$id]);
    $edit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  $rows = $pdo->query("
    SELECT a.*, e.title AS event_title
    FROM attendees a
    LEFT JOIN events e ON e.id = a.event_id
    ORDER BY a.id DESC
    LIMIT 15
  ")->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . "/header.php";
?>

<style>
  .hero{
    border-radius: var(--radius);
    background: linear-gradient(135deg, rgba(124,92,255,.18), rgba(46,233,166,.10));
    border: var(--border);
    box-shadow: var(--shadow);
    padding:18px;
    margin-bottom:14px;
  }
  .heroTitle{
    margin:0;
    font-size:1.45rem;
    font-weight:950;
    letter-spacing:.45px;
    line-height:1.1;
  }
  .heroSub{
    margin-top:6px;
    color:var(--muted);
    font-weight:800;
    letter-spacing:.25px;
  }

  .miniGrid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px;}
  @media (max-width: 960px){ .miniGrid{grid-template-columns:repeat(2,minmax(0,1fr));} }
  @media (max-width: 560px){ .miniGrid{grid-template-columns:1fr;} }

  .mini{
    border-radius: var(--radius);
    border: var(--border);
    background: rgba(255,255,255,.04);
    box-shadow: var(--shadow);
    padding:14px;
  }
  .mini .k{font-weight:950;font-size:1.05rem;}
  .mini .s{margin-top:6px;color:var(--muted);font-weight:800;}
  .mini .t{margin-top:10px;font-weight:950;font-size:1.6rem;letter-spacing:.2px;}

  .chartBox{
    border-radius: var(--radius);
    border: var(--border);
    background: rgba(255,255,255,.04);
    box-shadow: var(--shadow);
    padding:14px;
  }
  .chartHead{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px;}
  .chartTitle{font-weight:950;font-size:1.05rem;}
  .chartSub{color:var(--muted);font-weight:800;font-size:.9rem;}
  .canvasWrap{height:280px;}
  .canvasWrap canvas{width:100% !important;height:280px !important;}

  .seg{
    display:inline-flex; gap:8px; flex-wrap:wrap;
    margin-top:10px;
  }
  .seg button{
    border: var(--border);
    background: rgba(255,255,255,.04);
    color: var(--text);
    border-radius: 999px;
    padding: 8px 12px;
    cursor: pointer;
    font-weight: 850;
  }
  .seg button.active{
    background: linear-gradient(135deg, rgba(124,92,255,.30), rgba(46,233,166,.12));
    border-color: rgba(124,92,255,.35);
  }
</style>

<div class="hero">
  <h1 class="heroTitle">HAPPY CHURCH RUIRU</h1>
  <div class="heroSub">Church Management Dashboard</div>

  <div class="miniGrid">
    <div class="mini">
      <div class="k">📅 Upcoming Events</div>
      <div class="s">From today onwards</div>
      <div class="t"><?= (int)$upcomingCount ?></div>
    </div>
    <div class="mini">
      <div class="k">🗓️ Today’s Events</div>
      <div class="s"><?= e2($today) ?></div>
      <div class="t"><?= (int)$todayCount ?></div>
    </div>
    <div class="mini">
      <div class="k">✅ Attendance Rate</div>
      <div class="s">Attended / Total</div>
      <div class="t"><?= e2((string)$attRate) ?>%</div>
    </div>
    <div class="mini">
      <div class="k">🏁 Completed Events</div>
      <div class="s">Finished programs</div>
      <div class="t"><?= (int)$completedCount ?></div>
    </div>
  </div>
</div>

<div class="grid">
  <div class="col-4"><div class="kpi"><div class="num"><?= $eventsCount ?></div><div class="lbl">📅 Events</div></div></div>
  <div class="col-4"><div class="kpi"><div class="num"><?= $volsCount ?></div><div class="lbl">🤝 Volunteers</div></div></div>
  <div class="col-4"><div class="kpi"><div class="num"><?= $attsCount ?></div><div class="lbl">👥 Attendees</div></div></div>

  <?php if ($flash): ?>
    <div class="col-12"><div class="flash error"><?= e2($flash) ?></div></div>
  <?php endif; ?>

  <?php if ($tab === "events"): ?>
    <div class="col-8">
      <div class="chartBox">
        <div class="chartHead">
          <div>
            <div class="chartTitle">Events Trend</div>
            <div class="chartSub">Switch between 7 days, 30 days, and 6 months</div>

            <div class="seg" role="group" aria-label="Events trend range">
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
          <div>
            <div class="chartTitle">Attendance Status</div>
            <div class="chartSub">Distribution overview</div>
          </div>
          <div class="tag">Live</div>
        </div>
        <div class="canvasWrap"><canvas id="attendancePie"></canvas></div>
      </div>
    </div>

    <div class="col-12">
      <div class="chartBox">
        <div class="chartHead">
          <div>
            <div class="chartTitle">Event Status Summary</div>
            <div class="chartSub">Scheduled vs Ongoing vs Completed vs Cancelled</div>
          </div>
          <div class="tag">Insights</div>
        </div>
        <div class="canvasWrap"><canvas id="eventStatusBar"></canvas></div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($tab === "volunteers"): ?>
    <div class="col-12">
      <div class="chartBox">
        <div class="chartHead">
          <div>
            <div class="chartTitle">Volunteers by Ministry</div>
            <div class="chartSub">Top ministries (live from database)</div>
          </div>
          <div class="tag">Live</div>
        </div>
        <div class="canvasWrap"><canvas id="volunteersBar"></canvas></div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($tab === "attendees"): ?>
    <div class="col-12">
      <div class="chartBox">
        <div class="chartHead">
          <div>
            <div class="chartTitle">Attendees by Event</div>
            <div class="chartSub">Top events by attendance (live from database)</div>
          </div>
          <div class="tag">Live</div>
        </div>
        <div class="canvasWrap"><canvas id="attendeesBar"></canvas></div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ✅ Keep your CRUD/contacts/about sections AFTER this point in your file (unchanged) -->
</div>

<!-- ✅ Chart.js (include ONLY ONCE) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
  // ✅ Use ChartLib name to avoid VS Code / Intelephense "Class not imported" warnings
  const ChartLib = (typeof window !== "undefined" && window.Chart) ? window.Chart : null;

  const css = getComputedStyle(document.documentElement);
  const textColor = css.getPropertyValue('--text').trim() || '#ffffff';
  const muted = css.getPropertyValue('--muted').trim() || 'rgba(255,255,255,.75)';

  const baseOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { labels: { color: textColor, font: { weight: 700 } } },
      tooltip: { enabled: true }
    },
    scales: {
      x: { ticks: { color: muted }, grid: { color: 'rgba(255,255,255,.06)' } },
      y: { ticks: { color: muted }, grid: { color: 'rgba(255,255,255,.06)' } }
    }
  };

  // datasets from PHP
  const monthLabels = <?= json_encode($monthLabels, JSON_UNESCAPED_SLASHES) ?>;
  const monthCounts = <?= json_encode($monthCounts, JSON_UNESCAPED_SLASHES) ?>;

  const dailyLabels30 = <?= json_encode($dailyLabels30, JSON_UNESCAPED_SLASHES) ?>;
  const dailyCounts30 = <?= json_encode($dailyCounts30, JSON_UNESCAPED_SLASHES) ?>;

  const attLabels = <?= json_encode($attLabels, JSON_UNESCAPED_SLASHES) ?>;
  const attCounts = <?= json_encode($attCounts, JSON_UNESCAPED_SLASHES) ?>;

  const eventStatusLabels = <?= json_encode($eventStatusLabels, JSON_UNESCAPED_SLASHES) ?>;
  const eventStatusCounts = <?= json_encode($eventStatusCounts, JSON_UNESCAPED_SLASHES) ?>;

  const volsMinistryLabels = <?= json_encode($volsMinistryLabels, JSON_UNESCAPED_SLASHES) ?>;
  const volsMinistryCounts = <?= json_encode($volsMinistryCounts, JSON_UNESCAPED_SLASHES) ?>;

  const attsEventLabels = <?= json_encode($attsEventLabels, JSON_UNESCAPED_SLASHES) ?>;
  const attsEventCounts = <?= json_encode($attsEventCounts, JSON_UNESCAPED_SLASHES) ?>;

  function renderIf(id, build){
    const el = document.getElementById(id);
    if (!el || !ChartLib) return null;
    return build(el);
  }

  const CHARTS = {};

  function destroyChart(key){
    if (CHARTS[key]) {
      try { CHARTS[key].destroy(); } catch(e) {}
      CHARTS[key] = null;
    }
  }

  function lastNDays(labels, counts, n){
    const L = [];
    const C = [];
    const start = Math.max(0, labels.length - n);
    for (let i = start; i < labels.length; i++){
      L.push(labels[i]);
      C.push(counts[i]);
    }
    return {labels: L, counts: C};
  }

  function renderEventsTrend(range){
    const el = document.getElementById('eventsLine');
    if (!el || !ChartLib) return;

    destroyChart('eventsLine');

    let labels = [];
    let data = [];

    if (range === '6m') {
      labels = monthLabels.length ? monthLabels : ['No data'];
      data   = monthCounts.length ? monthCounts : [0];
    } else if (range === '30') {
      labels = dailyLabels30.length ? dailyLabels30 : ['No data'];
      data   = dailyCounts30.length ? dailyCounts30 : [0];
    } else {
      const sliced = lastNDays(dailyLabels30, dailyCounts30, 7);
      labels = sliced.labels.length ? sliced.labels : ['No data'];
      data   = sliced.counts.length ? sliced.counts : [0];
    }

    CHARTS['eventsLine'] = new ChartLib(el, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Events',
          data,
          tension: 0.35,
          borderWidth: 2,
          pointRadius: 3
        }]
      },
      options: baseOpts
    });
  }

  if (document.getElementById('eventsLine')) {
    renderEventsTrend('7');

    document.querySelectorAll('.seg button').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.seg button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderEventsTrend(btn.dataset.range);
      });
    });
  }

  renderIf('attendancePie', (el) => {
    destroyChart('attendancePie');
    CHARTS['attendancePie'] = new ChartLib(el, {
      type: 'pie',
      data: {
        labels: attLabels.length ? attLabels : ['No data'],
        datasets: [{
          label: 'Attendance',
          data: attCounts.length ? attCounts : [1],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { color: textColor, font: { weight: 800 } } }
        }
      }
    });
    return CHARTS['attendancePie'];
  });

  renderIf('eventStatusBar', (el) => {
    destroyChart('eventStatusBar');
    CHARTS['eventStatusBar'] = new ChartLib(el, {
      type: 'bar',
      data: {
        labels: eventStatusLabels.length ? eventStatusLabels : ['No data'],
        datasets: [{
          label: 'Events by Status',
          data: eventStatusCounts.length ? eventStatusCounts : [0],
          borderWidth: 1
        }]
      },
      options: baseOpts
    });
    return CHARTS['eventStatusBar'];
  });

  renderIf('volunteersBar', (el) => {
    destroyChart('volunteersBar');
    CHARTS['volunteersBar'] = new ChartLib(el, {
      type: 'bar',
      data: {
        labels: volsMinistryLabels.length ? volsMinistryLabels : ['No data'],
        datasets: [{
          label: 'Volunteers',
          data: volsMinistryCounts.length ? volsMinistryCounts : [0],
          borderWidth: 1
        }]
      },
      options: baseOpts
    });
    return CHARTS['volunteersBar'];
  });

  renderIf('attendeesBar', (el) => {
    destroyChart('attendeesBar');
    CHARTS['attendeesBar'] = new ChartLib(el, {
      type: 'bar',
      data: {
        labels: attsEventLabels.length ? attsEventLabels : ['No data'],
        datasets: [{
          label: 'Attendees',
          data: attsEventCounts.length ? attsEventCounts : [0],
          borderWidth: 1
        }]
      },
      options: baseOpts
    });
    return CHARTS['attendeesBar'];
  });
</script>

<?php require_once __DIR__ . "/footer.php"; ?>