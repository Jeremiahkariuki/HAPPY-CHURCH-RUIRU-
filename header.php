<?php
declare(strict_types=1);

require_once __DIR__ . "/helpers.php";

$appName = "HAPPY CHURCH RUIRU";
$current_page = basename($_SERVER["PHP_SELF"]);
$tab = (string)($_GET["tab"] ?? "events");
$flash = $flash ?? flash_get();

function isActiveTab(string $t, string $currentTab, string $page): bool {
  return $page === "dashboard.php" && $currentTab === $t;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e($appName) ?></title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⛪</text></svg>">
  <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>" />
  <style>
    /* Premium drawer */
    .drawer-overlay{
      position:fixed; inset:0;
      background: rgba(0,0,0,.55);
      opacity:0; pointer-events:none;
      transition: .18s ease;
      z-index:50;
    }
    .drawer-overlay.open{opacity:1; pointer-events:auto;}

    .drawer{
      position:fixed; top:0; left:0; height:100vh; width:320px;
      background: rgba(15,26,46,.92);
      border-right: var(--border);
      backdrop-filter: blur(12px);
      transform: translateX(-110%);
      transition:.18s ease;
      z-index:60;
      padding:12px 18px;
      display:flex; flex-direction:column;
      gap:8px;
    }
    .drawer.open{transform: translateX(0);}

    .drawer-head{
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      padding:12px;
      border-radius: var(--radius);
      background: rgba(255,255,255,.05);
      border: var(--border);
    }
    .drawer-brand{display:flex;align-items:center;gap:12px;}
    .drawer-logo{
      width:44px;height:44px;border-radius:14px;
      display:grid;place-items:center;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      color:#07101f;font-weight:950;
    }
    .drawer-close{
      width:44px;height:44px;border-radius:14px;
      border: var(--border);
      background: rgba(255,255,255,.06);
      cursor:pointer;
      display:grid;place-items:center;
      color:var(--text);
      font-size:18px;
    }
    .drawer-nav{display:flex;flex-direction:column;gap:5px;}
    .drawer-section{margin-top:2px; font-size:.78rem; color:var(--muted); letter-spacing:.12em;}

    .drawer-item{
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      padding:10px 12px;
      border-radius: 14px;
      background: rgba(255,255,255,.04);
      border: var(--border);
      transition: transform .12s ease, background .12s ease;
      text-decoration:none;
      color:var(--text);
    }
    .drawer-item:hover{transform: translateY(-1px); background: rgba(255,255,255,.07);}
    .drawer-item.active{
      background: linear-gradient(135deg, rgba(124,92,255,.30), rgba(46,233,166,.12));
      border-color: rgba(124,92,255,.35);
    }
    .drawer-left{display:flex;align-items:center;gap:10px;font-weight:850;}
    .drawer-pill{
      font-size:.75rem;
      padding:6px 10px;
      border-radius: 999px;
      background: rgba(124,92,255,.18);
      border: 1px solid rgba(124,92,255,.35);
      color: var(--text);
      font-weight:800;
    }
    .drawer-foot{
      margin-top:auto;
      padding:12px;
      border-radius: var(--radius);
      background: rgba(255,255,255,.04);
      border: var(--border);
    }

    /* Premium topbar */
    .topbar{
      display:flex; align-items:center; justify-content:space-between; gap:12px;
      padding:14px 16px;
      border-radius: var(--radius);
      background: rgba(255,255,255,.05);
      border: var(--border);
      box-shadow: var(--shadow);
      margin:16px 16px 0;
    }
    .topbar-left{display:flex;align-items:center;gap:12px;}
    .menu-btn{
      width:46px;height:46px;border-radius:14px;
      border: var(--border);
      background: rgba(255,255,255,.06);
      cursor:pointer;
      display:grid;place-items:center;
    }
    .menu-btn span{display:block;width:18px;height:2px;background:var(--text);margin:2px 0;border-radius:999px;}
    .topbar-title{font-weight:950;font-size:1.05rem; letter-spacing:.2px;}
    .topbar-sub{font-size:.85rem;color:var(--muted);}
    .main{padding:18px 22px;}
  </style>
</head>
<body>

<div class="drawer-overlay" id="drawerOverlay"></div>

<aside class="drawer" id="drawer" aria-hidden="true">
  <div class="drawer-head">
    <div class="drawer-brand">
      <div class="drawer-logo" style="background: linear-gradient(135deg, #7c5cff, #2ee9a6); color:#07101f;">+</div>
      <div>
        <div style="font-weight:950;"><?= e($appName) ?></div>
        <div class="small">Events • Volunteers • Attendance</div>
      </div>
    </div>
    <button class="drawer-close" id="drawerClose" type="button" aria-label="Close menu">✕</button>
  </div>

  <nav class="drawer-nav">
    <a class="drawer-item <?= $current_page==="dashboard.php" ? "active" : "" ?>" href="dashboard.php">
      <span class="drawer-left">📊 <span>Dashboard</span></span>
      <span></span>
    </a>

    <a class="drawer-item <?= $current_page==="events.php" ? "active" : "" ?>" href="events.php">
      <span class="drawer-left">📅 <span>Events</span></span>
      <span></span>
    </a>

    <a class="drawer-item <?= $current_page==="volunteers.php" ? "active" : "" ?>" href="volunteers.php">
      <span class="drawer-left">🤝 <span>Volunteers</span></span>
      <span></span>
    </a>

    <a class="drawer-item <?= $current_page==="gallery.php" ? "active" : "" ?>" href="gallery.php">
      <span class="drawer-left">🖼️ <span>Gallery</span></span>
      <span></span>
    </a>

    <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
      <a class="drawer-item <?= $current_page==="attendees.php" ? "active" : "" ?>" href="attendees.php">
        <span class="drawer-left">👥 <span>Attendees</span></span>
        <span></span>
      </a>
    <?php endif; ?>

    <div class="drawer-section">CHURCH</div>

    <a class="drawer-item <?= $current_page==="contacts.php" ? "active" : "" ?>" href="contacts.php">
      <span class="drawer-left">📞 <span>Contacts</span></span>
      <span></span>
    </a>

    <?php if (($_SESSION["user"]["role"] ?? "") === "admin"): ?>
      <a class="drawer-item <?= $current_page==="admin_users.php" ? "active" : "" ?>" href="admin_users.php">
        <span class="drawer-left">👥 <span>Users</span></span>
        <span class="drawer-pill">Admin</span>
      </a>
    <?php endif; ?>

    <a class="drawer-item <?= $current_page==="about.php" ? "active" : "" ?>" href="about.php">
      <span class="drawer-left">✨ <span>About</span></span>
      <span></span>
    </a>
  </nav>

  <div class="drawer-foot" style="padding:16px; border-top:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.02);">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
      <div style="width:44px; height:44px; border-radius:14px; background:linear-gradient(135deg, var(--brand), var(--brand2)); display:grid; place-items:center; font-weight:950; color:#07101f; font-size:1.2rem; box-shadow:0 8px 20px rgba(124,92,255,.3);">
        <?= strtoupper(substr(e($_SESSION["user"]["username"] ?? "Guest"), 0, 1)) ?>
      </div>
      <div>
        <div style="font-size:0.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:1px; font-weight:800;">Signed in as</div>
        <div style="font-weight:950; font-size:1rem; color:var(--text); letter-spacing:-0.2px;"><?= e($_SESSION["user"]["username"] ?? "Guest") ?></div>
      </div>
    </div>
    <a class="btn btn-ghost" href="logout.php" style="width:100%; display:flex; align-items:center; justify-content:center; gap:10px; padding:12px; background:rgba(255,77,109,.1); border-color:rgba(255,77,109,.2); color:#ff4d6d; border-radius:14px; font-weight:850; font-size:0.9rem;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
      Logout Account
    </a>
  </div>
</aside>

<header class="topbar">
  <div class="topbar-left">
    <button class="menu-btn" id="menuBtn" type="button" aria-label="Open menu">
      <span></span><span></span><span></span>
    </button>
    <div>
      <div class="topbar-title"><?= e($appName) ?></div>
      <div class="topbar-sub">
        <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
          Admin Dashboard
        <?php else: ?>
          Member Dashboard
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="tag">Secure</div>
</header>

<!-- ✅ IMPORTANT: Keep MAIN OPEN (do NOT close here) -->
<main class="main">

<script>
document.addEventListener('DOMContentLoaded', () => {
  const menuBtn = document.getElementById('menuBtn') || document.getElementById('toggleBtn');
  const drawer = document.getElementById('drawer') || document.getElementById('sidebar');
  const overlay = document.getElementById('drawerOverlay');
  const closeBtn = document.getElementById('drawerClose');

  if (!menuBtn || !drawer) return;

  const openDrawer = () => {
    drawer.classList.add('open');
    overlay?.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
  };

  const closeDrawer = () => {
    drawer.classList.remove('open');
    overlay?.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
  };

  menuBtn.addEventListener('click', openDrawer);
  closeBtn?.addEventListener('click', closeDrawer);
  overlay?.addEventListener('click', closeDrawer);
});
</script>
