<?php
declare(strict_types = 1)
;

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/header.php";
?>

<div style="margin-bottom: 20px;">
  <a class="btn btn-ghost" href="dashboard.php">← Back to Dashboard</a>
</div>

<div class="card" style="overflow:hidden; position:relative; padding: 60px 24px; text-align:center; background: radial-gradient(circle at top right, rgba(124,92,255,.2), transparent), radial-gradient(circle at bottom left, rgba(46,233,166,.1), transparent), var(--card);">
    <div style="position:relative; z-index:2;">
        <div class="drawer-logo" style="width:80px; height:80px; font-size:40px; margin: 0 auto 24px; border-radius:24px;">✝</div>
        <h1 style="margin:0; font-weight:950; font-size:2.8rem; letter-spacing:-1px;">HAPPY CHURCH RUIRU</h1>
        <p style="font-size:1.2rem; color:var(--brand2); font-weight:700; margin-top:12px;">Where Faith Finds a Home & Hearts Find Hope.</p>
    </div>
</div>

<div class="grid" style="margin-top:24px;">
    <div class="col-8">
        <div class="card" style="height:100%; display:flex; flex-direction:column; gap:20px;">
            <h2 style="margin:0; font-weight:950; font-size:1.5rem;">Our Mission</h2>
            <p style="line-height:1.7; color:var(--text); font-size:1.05rem;">
                At HAPPY Church RUIRU, we are dedicated to building a vibrant, Christ-centered community that empowers individuals to discover their divine purpose. Located in the heart of Ruiru, we serve as a beacon of light, offering spiritual nourishment, fellowship, and a place where everyone is welcome.
            </p>
            <p style="line-height:1.7; color:var(--text); font-size:1.05rem;">
                Our journey is one of faith, driven by the belief that heaven's hope can transform any life. Through our various ministries—from vibrant worship to impactful community outreach—we strive to reflect the love of Christ in everything we do.
            </p>
            
            <div style="margin-top:auto; padding-top:20px; border-top: var(--border);">
                <div style="font-weight:900; color:var(--brand);">"Transforming lives, one heart at a time."</div>
            </div>
        </div>
    </div>

    <div class="col-4">
        <div class="card" style="height:100%; background: linear-gradient(135deg, var(--brand), #4e36f5); color:#fff; display:flex; flex-direction:column; justify-content:center; text-align:center; padding: 40px 24px;">
            <div style="font-size:3rem; margin-bottom:20px; opacity:0.8;">📖</div>
            <h3 style="margin:0; font-weight:950; font-size:1.3rem; text-transform:uppercase; letter-spacing:1px; opacity:0.9;">Scripture of Hope</h3>
            <div style="margin: 24px 0; font-size:1.4rem; font-style:italic; font-weight:700; line-height:1.4;">
                "May the God of hope fill you with all joy and peace as you trust in him, so that you may overflow with hope by the power of the Holy Spirit."
            </div>
            <div style="font-weight:950; font-size:1.1rem; color:var(--brand2);">Romans 15:13</div>
        </div>
    </div>
</div>

<div class="grid" style="margin-top:24px;">
    <div class="col-4">
        <div class="card kpi">
            <div class="num">1000+</div>
            <div class="lbl">Community Members</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card kpi" style="background: linear-gradient(135deg, rgba(46,233,166,.2), rgba(124,92,255,.1));">
            <div class="num">15+</div>
            <div class="lbl">Active Ministries</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card kpi">
            <div class="num">Weekly</div>
            <div class="lbl">Community Outreach</div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
