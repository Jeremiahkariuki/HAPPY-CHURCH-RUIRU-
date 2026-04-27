<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

function e2(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }

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
    $eventsMonthly = $pdo ? $pdo->query("
      SELECT DATE_FORMAT(event_date, '%Y-%m') AS ym, COUNT(*) AS c
      FROM events
      WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
      GROUP BY ym ORDER BY ym ASC
    ")->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $eventsMonthly = []; $dbErr = true; }

$monthLabels = []; $monthCounts = [];
foreach ($eventsMonthly as $r) { $monthLabels[] = (string)$r["ym"]; $monthCounts[] = (int)$r["c"]; }

try {
    $attStatus = $pdo ? $pdo->query("SELECT attendance_status AS s, COUNT(*) AS c FROM attendees GROUP BY attendance_status ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $attStatus = []; $dbErr = true; }

$attLabels = []; $attCounts = [];
foreach ($attStatus as $r) { $attLabels[] = (string)($r["s"] ?: "Unknown"); $attCounts[] = (int)$r["c"]; }

try {
    $eventsDaily30 = $pdo ? $pdo->query("
      SELECT DATE(event_date) AS d, COUNT(*) AS c FROM events
      WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY d ORDER BY d ASC
    ")->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $eventsDaily30 = []; $dbErr = true; }

$dailyLabels30 = []; $dailyCounts30 = [];
foreach ($eventsDaily30 as $r) { $dailyLabels30[] = (string)$r["d"]; $dailyCounts30[] = (int)$r["c"]; }

try {
    $volsByMinistry = $pdo ? $pdo->query("SELECT COALESCE(NULLIF(ministry,''),'Unknown') AS m, COUNT(*) AS c FROM volunteers GROUP BY m ORDER BY c DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $volsByMinistry = []; $dbErr = true; }

$volsMinistryLabels = []; $volsMinistryCounts = [];
foreach ($volsByMinistry as $r) { $volsMinistryLabels[] = (string)$r["m"]; $volsMinistryCounts[] = (int)$r["c"]; }

try {
    $attsByEvent = $pdo ? $pdo->query("SELECT COALESCE(e.title,'(No Event)') AS t, COUNT(*) AS c FROM attendees a LEFT JOIN events e ON e.id=a.event_id GROUP BY t ORDER BY c DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $attsByEvent = []; $dbErr = true; }

$attsEventLabels = []; $attsEventCounts = [];
foreach ($attsByEvent as $r) { $attsEventLabels[] = (string)$r["t"]; $attsEventCounts[] = (int)$r["c"]; }

$eventsCount   = (int)($pdo ? ($pdo->query("SELECT COUNT(*) FROM events")->fetchColumn() ?: 0) : 0);
$volsCount     = (int)($pdo ? ($pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn() ?: 0) : 0);
$attsCount     = (int)($pdo ? ($pdo->query("SELECT COUNT(*) FROM attendees")->fetchColumn() ?: 0) : 0);
$upcomingCount = (int)($pdo ? ($pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()")->fetchColumn() ?: 0) : 0);
$todayCount    = (int)($pdo ? ($pdo->query("SELECT COUNT(*) FROM events WHERE event_date = CURDATE()")->fetchColumn() ?: 0) : 0);
$completedCount= (int)($pdo ? ($pdo->query("SELECT COUNT(*) FROM events WHERE status = 'Completed'")->fetchColumn() ?: 0) : 0);
// Functional Attendance Rate: (Attended / Total Registered) for COMPLETED events only
try {
    $totalRegisteredForCompleted = 0;
    $totalAttendedForCompleted = 0;
    $completedEventIds = $pdo->query("SELECT id FROM events WHERE status = 'Completed'")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($completedEventIds)) {
        $placeholders = implode(',', array_fill(0, count($completedEventIds), '?'));
        $stmtRegistered = $pdo->prepare("SELECT COUNT(*) FROM attendees WHERE event_id IN ($placeholders) AND attendance_status != 'Cancelled'");
        $stmtRegistered->execute($completedEventIds);
        $totalRegisteredForCompleted = (int)$stmtRegistered->fetchColumn();

        $stmtAttended = $pdo->prepare("SELECT COUNT(*) FROM attendees WHERE event_id IN ($placeholders) AND attendance_status = 'Attended'");
        $stmtAttended->execute($completedEventIds);
        $totalAttendedForCompleted = (int)$stmtAttended->fetchColumn();

        $attRate = ($totalRegisteredForCompleted > 0) ? round(($totalAttendedForCompleted / $totalRegisteredForCompleted) * 100, 1) : 100.0;
    } else {
        // If no events are completed, we show 100% or "N/A" - let's go with 100% as a positive baseline
        $attRate = 100.0;
    }
} catch (Exception $e) { $attRate = 0.0; }

// Load church name from config — change config.php to rename site-wide
$_cfg = require __DIR__ . "/config.php";
$appName = $_cfg["app"]["name"] ?? "HAPPY CHURCH RUIRU";
unset($_cfg);

require_once __DIR__ . "/header.php";
?>

<!-- Auto-Clean Lingering Tab Parameters from Cached Browsers -->
<script>
  if (window.location.search.includes('tab=')) {
    // Forcefully remove the tab parameter from the URL bar immediately
    window.history.replaceState({}, document.title, window.location.pathname);
    // Hard refresh to completely kill the cached state
    window.location.replace(window.location.pathname);
  }
</script>

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
  <h1 class="heroTitle"><?= e($appName) ?></h1>
  <div class="heroSub">Church Management Dashboard</div>
  <div class="miniGrid">
    <div class="mini"><div class="k">📅 Upcoming Events</div><div class="s">From today onwards</div><div class="t"><?= $upcomingCount ?></div></div>
    <div class="mini"><div class="k">🗓️ Today's Events</div><div class="s"><?= e2($today) ?></div><div class="t"><?= $todayCount ?></div></div>
    <div class="mini"><div class="k">✅ Attendance Rate</div><div class="s"><?= $totalRegisteredForCompleted > 0 ? "Based on $completedCount events" : "No events completed yet" ?></div><div class="t"><?= $totalRegisteredForCompleted > 0 ? $attRate . "%" : "---" ?></div></div>
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
     MAIN DASHBOARD
============================================================ -->
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
      <div class="canvasWrap">
        <?php if (empty($monthLabels) && empty($dailyLabels30)): ?>
          <div style="height:100%; display:grid; place-items:center; opacity:0.5; text-align:center;">
             <div><div style="font-size:2rem; margin-bottom:10px;">📉</div><div class="small">No event data to plot yet.</div></div>
          </div>
        <?php else: ?>
          <canvas id="eventsLine"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-4">
    <div class="chartBox">
      <div class="chartHead">
        <div><div class="chartTitle">Attendance Status</div><div class="chartSub">Distribution overview</div></div>
        <div class="tag">Live</div>
      </div>
      <div class="canvasWrap">
        <?php if (empty($attLabels)): ?>
          <div style="height:100%; display:grid; place-items:center; opacity:0.5; text-align:center;">
             <div><div style="font-size:2rem; margin-bottom:10px;">📊</div><div class="small">Waiting for participants.</div></div>
          </div>
        <?php else: ?>
          <canvas id="attendancePie"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-6">
    <div class="chartBox">
      <div class="chartHead">
        <div><div class="chartTitle">Volunteers by Ministry</div><div class="chartSub">Top ministries (live from database)</div></div>
        <div class="tag" style="background:rgba(46,233,166,.15); color:var(--brand2); border:1px solid rgba(46,233,166,.3);">Volunteers</div>
      </div>
      <div class="canvasWrap">
        <?php if (empty($volsMinistryLabels)): ?>
          <div style="height:100%; display:grid; place-items:center; opacity:0.5; text-align:center;">
             <div><div style="font-size:2rem; margin-bottom:10px;">🤝</div><div class="small">No volunteers found.</div></div>
          </div>
        <?php else: ?>
          <canvas id="volunteersBar"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <div class="col-6">
    <div class="chartBox">
      <div class="chartHead">
        <div><div class="chartTitle">Top Events Attendance</div><div class="chartSub">By number of attendees</div></div>
        <div class="tag" style="background:rgba(255,193,7,.15); color:#ffcc00; border:1px solid rgba(255,193,7,.3);">Attendees</div>
      </div>
      <div class="canvasWrap">
        <?php if (empty($attsEventLabels)): ?>
          <div style="height:100%; display:grid; place-items:center; opacity:0.5; text-align:center;">
             <div><div style="font-size:2rem; margin-bottom:10px;">👥</div><div class="small">No attendee data yet.</div></div>
          </div>
        <?php else: ?>
          <canvas id="attendeesBar"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>


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
  function renderIf(id,build){const el=document.getElementById(id);if(!el||!window.Chart)return null;return build(el);}
  function lastNDays(L,C,n){const s=Math.max(0,L.length-n);return{labels:L.slice(s),counts:C.slice(s)};}

  function renderEventsTrend(range){
    const el=document.getElementById('eventsLine'); if(!el||!window.Chart)return;
    destroyChart('eventsLine');
    let labels,data;
    if(range==='6m'){labels=monthLabels.length?monthLabels:['No data'];data=monthCounts.length?monthCounts:[0];}
    else if(range==='30'){labels=dailyLabels30.length?dailyLabels30:['No data'];data=dailyCounts30.length?dailyCounts30:[0];}
    else{const s=lastNDays(dailyLabels30,dailyCounts30,7);labels=s.labels.length?s.labels:['No data'];data=s.counts.length?s.counts:[0];}
    CHARTS['eventsLine']=new window.Chart(el,{type:'line',data:{labels,datasets:[{label:'Events',data,tension:.35,borderWidth:2,pointRadius:3}]},options:baseOpts});
  }
  if(document.getElementById('eventsLine')){
    renderEventsTrend('7');
    document.querySelectorAll('.seg button').forEach(btn=>{btn.addEventListener('click',()=>{document.querySelectorAll('.seg button').forEach(b=>b.classList.remove('active'));btn.classList.add('active');renderEventsTrend(btn.dataset.range);});});
  }
  renderIf('attendancePie',(el)=>{destroyChart('attendancePie');CHARTS['attendancePie']=new window.Chart(el,{type:'pie',data:{labels:attLabels.length?attLabels:['No data'],datasets:[{label:'Attendance',data:attCounts.length?attCounts:[1],borderWidth:1}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{color:textColor,font:{weight:800}}}}}});return CHARTS['attendancePie'];});
  renderIf('volunteersBar',(el)=>{destroyChart('volunteersBar');CHARTS['volunteersBar']=new window.Chart(el,{type:'bar',data:{labels:volsMinistryLabels.length?volsMinistryLabels:['No data'],datasets:[{label:'Volunteers',data:volsMinistryCounts.length?volsMinistryCounts:[0],borderWidth:1}]},options:baseOpts});return CHARTS['volunteersBar'];});
  renderIf('attendeesBar',(el)=>{destroyChart('attendeesBar');CHARTS['attendeesBar']=new window.Chart(el,{type:'bar',data:{labels:attsEventLabels.length?attsEventLabels:['No data'],datasets:[{label:'Attendees',data:attsEventCounts.length?attsEventCounts:[0],borderWidth:1}]},options:baseOpts});return CHARTS['attendeesBar'];});
</script>

<?php require_once __DIR__ . "/footer.php"; ?>