<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/header.php";
?>

<div style="margin-bottom: 20px;">
  <a class="btn btn-ghost" href="dashboard.php">← Back to Dashboard</a>
</div>

<!-- Hero Banner -->
<div class="card" style="overflow:hidden; position:relative; padding: 60px 24px; text-align:center; background: radial-gradient(circle at top right, rgba(124,92,255,.2), transparent), radial-gradient(circle at bottom left, rgba(46,233,166,.1), transparent), var(--card);">
    <div style="position:relative; z-index:2;">
        <div class="drawer-logo" style="width:80px; height:80px; font-size:40px; margin: 0 auto 24px; border-radius:24px;">✝</div>
        <h1 style="margin:0; font-weight:950; font-size:2.8rem; letter-spacing:-1px;">HAPPY CHURCH RUIRU</h1>
        <p style="font-size:1.2rem; color:var(--brand2); font-weight:700; margin-top:12px;">Where Faith Finds a Home & Hearts Find Hope.</p>
    </div>
</div>

<!-- Mission + Scripture -->
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

<!-- Inspirational Bible Verses Section -->
<div class="card" style="margin-top:24px; padding:30px;">
    <h2 style="margin:0 0 24px; font-weight:950; font-size:1.5rem; text-align:center;">
        ✨ Inspirational Verses for Our Community
    </h2>
    <div class="grid" style="gap:16px;">
        <div class="col-6">
            <div class="card" style="background: rgba(124,92,255,.08); border-color: rgba(124,92,255,.15); height:100%;">
                <div style="font-size:2rem; margin-bottom:12px;">🕊️</div>
                <div style="font-style:italic; font-size:1.05rem; line-height:1.6; color:var(--text); font-weight:500;">
                    "For I know the plans I have for you," declares the LORD, "plans to prosper you and not to harm you, plans to give you hope and a future."
                </div>
                <div style="margin-top:14px; font-weight:900; color:var(--brand); font-size:0.95rem;">— Jeremiah 29:11</div>
            </div>
        </div>
        <div class="col-6">
            <div class="card" style="background: rgba(46,233,166,.06); border-color: rgba(46,233,166,.15); height:100%;">
                <div style="font-size:2rem; margin-bottom:12px;">💡</div>
                <div style="font-style:italic; font-size:1.05rem; line-height:1.6; color:var(--text); font-weight:500;">
                    "Trust in the LORD with all your heart and lean not on your own understanding; in all your ways submit to him, and he will make your paths straight."
                </div>
                <div style="margin-top:14px; font-weight:900; color:var(--brand2); font-size:0.95rem;">— Proverbs 3:5-6</div>
            </div>
        </div>
        <div class="col-6">
            <div class="card" style="background: rgba(255,193,7,.06); border-color: rgba(255,193,7,.12); height:100%;">
                <div style="font-size:2rem; margin-bottom:12px;">🌟</div>
                <div style="font-style:italic; font-size:1.05rem; line-height:1.6; color:var(--text); font-weight:500;">
                    "Be strong and courageous. Do not be afraid; do not be discouraged, for the LORD your God will be with you wherever you go."
                </div>
                <div style="margin-top:14px; font-weight:900; color:#ffcc00; font-size:0.95rem;">— Joshua 1:9</div>
            </div>
        </div>
        <div class="col-6">
            <div class="card" style="background: rgba(255,77,109,.05); border-color: rgba(255,77,109,.12); height:100%;">
                <div style="font-size:2rem; margin-bottom:12px;">❤️</div>
                <div style="font-style:italic; font-size:1.05rem; line-height:1.6; color:var(--text); font-weight:500;">
                    "And now these three remain: faith, hope and love. But the greatest of these is love."
                </div>
                <div style="margin-top:14px; font-weight:900; color:#ff6b8a; font-size:0.95rem;">— 1 Corinthians 13:13</div>
            </div>
        </div>
        <div class="col-6">
            <div class="card" style="background: rgba(124,92,255,.06); border-color: rgba(124,92,255,.12); height:100%;">
                <div style="font-size:2rem; margin-bottom:12px;">🙏</div>
                <div style="font-style:italic; font-size:1.05rem; line-height:1.6; color:var(--text); font-weight:500;">
                    "I can do all this through him who gives me strength."
                </div>
                <div style="margin-top:14px; font-weight:900; color:var(--brand); font-size:0.95rem;">— Philippians 4:13</div>
            </div>
        </div>
        <div class="col-6">
            <div class="card" style="background: rgba(46,233,166,.05); border-color: rgba(46,233,166,.12); height:100%;">
                <div style="font-size:2rem; margin-bottom:12px;">🌿</div>
                <div style="font-style:italic; font-size:1.05rem; line-height:1.6; color:var(--text); font-weight:500;">
                    "The LORD is my shepherd, I lack nothing. He makes me lie down in green pastures, he leads me beside quiet waters, he refreshes my soul."
                </div>
                <div style="margin-top:14px; font-weight:900; color:var(--brand2); font-size:0.95rem;">— Psalm 23:1-3</div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
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

<!-- Daily Devotional Quote -->
<div class="card" style="margin-top:24px; background: linear-gradient(135deg, rgba(124,92,255,.1), rgba(46,233,166,.05)); text-align:center; padding:40px 24px;">
    <div style="font-size:2.5rem; margin-bottom:16px;">📜</div>
    <h3 style="margin:0 0 16px; font-weight:950; font-size:1.3rem; color:var(--brand2); text-transform:uppercase; letter-spacing:1px;">Daily Encouragement</h3>
    <div style="font-size:1.3rem; font-style:italic; font-weight:600; line-height:1.5; max-width:700px; margin:0 auto; color:var(--text);">
        "Let all things be done decently and in order."
    </div>
    <div style="margin-top:14px; font-weight:950; color:var(--brand); font-size:1.1rem;">— 1 Corinthians 14:40</div>
    <div style="margin-top:20px; color:var(--muted); font-size:0.85rem; font-weight:700;">
        This verse inspires how we manage our church — with excellence, order, and love.
    </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
