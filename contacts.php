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

<div class="card" style="margin-bottom: 24px;">
    <h1 style="margin:0; font-weight:950; font-size:1.8rem;">Contact Us</h1>
    <p class="small" style="margin-top:8px;">We are here to serve and support you. Feel free to reach out to our team.</p>
</div>

<div class="grid">
    <!-- Admin Card -->
    <div class="col-4">
        <div class="card" style="text-align:center; padding: 32px 18px; display:flex; flex-direction:column; align-items:center; gap:16px;">
            <div style="width:64px; height:64px; border-radius:20px; background:rgba(124,92,255,.15); border:1px solid rgba(124,92,255,.3); display:grid; place-items:center; font-size:24px;">
                👤
            </div>
            <div>
                <div style="font-weight:950; font-size:1.2rem;">Church Admin</div>
                <div class="small" style="margin-top:4px;">Operations & Logistics</div>
            </div>
            <div style="font-weight:800; color:var(--brand2); font-size:1.1rem;">0715 931 990</div>
            <div class="actions" style="justify-content:center; width:100%;">
                <a href="tel:0715931990" class="btn" style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px;">
                    📞 Call
                </a>
                <a href="https://wa.me/254715931990" target="_blank" class="btn btn-ghost" style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; border-color: #25D366; color: #25D366;">
                    💬 WhatsApp
                </a>
            </div>
        </div>
    </div>

    <!-- Pastor Card -->
    <div class="col-4">
        <div class="card" style="text-align:center; padding: 32px 18px; display:flex; flex-direction:column; align-items:center; gap:16px; border-color: var(--brand);">
            <div style="width:64px; height:64px; border-radius:20px; background:linear-gradient(135deg, var(--brand), var(--brand2)); color:#07101f; display:grid; place-items:center; font-size:24px; font-weight:950;">
                ✝
            </div>
            <div>
                <div style="font-weight:950; font-size:1.2rem;">Senior Pastor</div>
                <div class="small" style="margin-top:4px;">Spiritual Guidance & Prayer</div>
            </div>
            <div style="font-weight:800; color:var(--brand); font-size:1.1rem;">0715 931 990</div>
            <div class="actions" style="justify-content:center; width:100%;">
                <a href="tel:0715931990" class="btn" style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; background:var(--brand);">
                    📞 Call
                </a>
                <a href="https://wa.me/254715931990" target="_blank" class="btn btn-ghost" style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; border-color: #25D366; color: #25D366;">
                    💬 WhatsApp
                </a>
            </div>
        </div>
    </div>

    <!-- Receptionist Card -->
    <div class="col-4">
        <div class="card" style="text-align:center; padding: 32px 18px; display:flex; flex-direction:column; align-items:center; gap:16px;">
            <div style="width:64px; height:64px; border-radius:20px; background:rgba(46,233,166,.15); border:1px solid rgba(46,233,166,.3); display:grid; place-items:center; font-size:24px;">
                📞
            </div>
            <div>
                <div style="font-weight:950; font-size:1.2rem;">Receptionist</div>
                <div class="small" style="margin-top:4px;">Inquiries & Appointments</div>
            </div>
            <div style="font-weight:800; color:var(--brand2); font-size:1.1rem;">0743 341 474</div>
            <div class="actions" style="justify-content:center; width:100%;">
                <a href="tel:0743341474" class="btn" style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px;">
                    📞 Call
                </a>
                <a href="https://wa.me/254743341474" target="_blank" class="btn btn-ghost" style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; border-color: #25D366; color: #25D366;">
                    💬 WhatsApp
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:24px; background: linear-gradient(135deg, rgba(124,92,255,.1), rgba(46,233,166,.05));">
    <div style="font-weight:800; margin-bottom:10px;">Visit Us</div>
    <div class="small">HAPPY Church RUIRU Headquarters.</div>
    <div class="small" style="margin-top:4px;">Service Hours: Sundays 8:00 AM - 1:00 PM | Mid-week: Wednesdays 5:30 PM</div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
