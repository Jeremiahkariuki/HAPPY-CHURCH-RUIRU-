<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/header.php";
?>

<style>
    .contact-card {
        text-align: center;
        padding: 36px 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 18px;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .contact-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0,0,0,.3);
    }
    .contact-avatar {
        width: 72px;
        height: 72px;
        border-radius: 22px;
        display: grid;
        place-items: center;
        font-size: 28px;
        transition: transform 0.3s ease;
    }
    .contact-card:hover .contact-avatar {
        transform: scale(1.1) rotate(-3deg);
    }
    .contact-name {
        font-weight: 950;
        font-size: 1.25rem;
        color: var(--text);
    }
    .contact-role {
        font-size: 0.85rem;
        color: var(--muted);
        font-weight: 600;
    }
    .contact-phone {
        font-weight: 800;
        font-size: 1.15rem;
        letter-spacing: 0.5px;
    }
    .contact-actions {
        display: flex;
        gap: 10px;
        width: 100%;
        justify-content: center;
    }
    .contact-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 13px 16px;
        border-radius: 14px;
        font-weight: 800;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .contact-btn:hover {
        transform: translateY(-2px);
    }
    .btn-call {
        background: rgba(124,92,255,.15);
        border: 1px solid rgba(124,92,255,.30);
        color: var(--brand);
    }
    .btn-call:hover {
        background: rgba(124,92,255,.25);
        border-color: rgba(124,92,255,.45);
    }
    .btn-whatsapp {
        background: rgba(37,211,102,.10);
        border: 1px solid rgba(37,211,102,.25);
        color: #25D366;
    }
    .btn-whatsapp:hover {
        background: rgba(37,211,102,.20);
        border-color: rgba(37,211,102,.40);
    }
</style>

<div style="margin-bottom: 20px;">
  <a class="btn btn-ghost" href="dashboard.php">← Back to Dashboard</a>
</div>

<!-- Page Header -->
<div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, rgba(124,92,255,.12), rgba(46,233,166,.06));">
    <h1 style="margin:0; font-weight:950; font-size:1.8rem;">📞 Contact Us</h1>
    <p class="small" style="margin-top:8px;">We are here to serve and support you. Reach out to our team anytime.</p>
</div>

<!-- Contact Cards -->
<div class="grid">
    <!-- Church Admin -->
    <div class="col-4">
        <div class="card contact-card">
            <div class="contact-avatar" style="background: rgba(124,92,255,.15); border: 1px solid rgba(124,92,255,.3);">
                👤
            </div>
            <div>
                <div class="contact-name">Church Admin</div>
                <div class="contact-role">Operations & Logistics</div>
            </div>
            <div class="contact-phone" style="color: var(--brand2);">0715 931 990</div>
            <div class="contact-actions">
                <a href="tel:+254715931990" class="contact-btn btn-call">
                    📞 Call
                </a>
                <a href="https://wa.me/254715931990" target="_blank" class="contact-btn btn-whatsapp">
                    💬 WhatsApp
                </a>
            </div>
        </div>
    </div>

    <!-- Senior Pastor -->
    <div class="col-4">
        <div class="card contact-card" style="border-color: rgba(124,92,255,.3);">
            <div class="contact-avatar" style="background: linear-gradient(135deg, var(--brand), var(--brand2)); color: #07101f; font-weight: 950;">
                ✝
            </div>
            <div>
                <div class="contact-name">Senior Pastor</div>
                <div class="contact-role">Spiritual Guidance & Prayer</div>
            </div>
            <div class="contact-phone" style="color: var(--brand);">0715 931 990</div>
            <div class="contact-actions">
                <a href="tel:+254715931990" class="contact-btn btn-call" style="background: rgba(124,92,255,.20); border-color: var(--brand);">
                    📞 Call
                </a>
                <a href="https://wa.me/254715931990" target="_blank" class="contact-btn btn-whatsapp">
                    💬 WhatsApp
                </a>
            </div>
        </div>
    </div>

    <!-- Receptionist -->
    <div class="col-4">
        <div class="card contact-card">
            <div class="contact-avatar" style="background: rgba(46,233,166,.15); border: 1px solid rgba(46,233,166,.3);">
                📞
            </div>
            <div>
                <div class="contact-name">Receptionist</div>
                <div class="contact-role">Inquiries & Appointments</div>
            </div>
            <div class="contact-phone" style="color: var(--brand2);">0743 341 474</div>
            <div class="contact-actions">
                <a href="tel:+254743341474" class="contact-btn btn-call">
                    📞 Call
                </a>
                <a href="https://wa.me/254743341474" target="_blank" class="contact-btn btn-whatsapp">
                    💬 WhatsApp
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid" style="margin-top:24px;">
    <div class="col-6">
        <div class="card" style="padding:24px; background: linear-gradient(135deg, rgba(37,211,102,.08), rgba(37,211,102,.02));">
            <div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">
                <div style="width:52px; height:52px; border-radius:16px; background:rgba(37,211,102,.15); border:1px solid rgba(37,211,102,.25); display:grid; place-items:center; font-size:22px;">💬</div>
                <div>
                    <div style="font-weight:900; font-size:1.1rem;">WhatsApp Direct</div>
                    <div class="small">Message us instantly</div>
                </div>
            </div>
            <p style="color:var(--muted); font-size:0.9rem; line-height:1.6;">
                Need immediate assistance? Reach out via WhatsApp for quick responses about services, events, and prayer requests.
            </p>
        </div>
    </div>
    <div class="col-6">
        <a href="notifications.php" class="card" style="display:block; text-decoration:none; transition:transform .2s; height:100%; padding:24px; background: linear-gradient(135deg, rgba(124,92,255,.08), rgba(124,92,255,.02)); border: 1px solid rgba(255,255,255,.1);">
            <div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">
                <div style="width:52px; height:52px; border-radius:16px; background:rgba(124,92,255,.15); border:1px solid rgba(124,92,255,.25); display:grid; place-items:center; font-size:22px;">📧</div>
                <div>
                    <div style="font-weight:900; font-size:1.1rem; color:var(--brand);">Email & Notifications</div>
                    <div class="small">Stay informed</div>
                </div>
            </div>
            <p style="color:var(--muted); font-size:0.9rem; line-height:1.6;">
                Members receive event reminders, volunteer alerts, and community updates. Click here to open the notification broadcast panel.
            </p>
        </a>
    </div>
</div>

<!-- Visit Us -->
<div class="card" style="margin-top:24px; background: linear-gradient(135deg, rgba(124,92,255,.1), rgba(46,233,166,.05)); padding:30px;">
    <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
        <div style="width:52px; height:52px; border-radius:16px; background:linear-gradient(135deg, var(--brand), var(--brand2)); display:grid; place-items:center; font-size:22px; color:#07101f; font-weight:950;">📍</div>
        <div>
            <div style="font-weight:900; font-size:1.2rem;">Visit Us</div>
            <div class="small">HAPPY Church RUIRU Headquarters</div>
        </div>
    </div>
    <div class="grid" style="gap:14px;">
        <div class="col-6">
            <div style="display:flex; align-items:center; gap:10px; padding:14px; border-radius:14px; background:rgba(255,255,255,.03); border: var(--border);">
                <span style="font-size:1.3rem;">🕐</span>
                <div>
                    <div style="font-weight:800; font-size:0.9rem;">Sunday Service</div>
                    <div class="small">8:00 AM — 1:00 PM</div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div style="display:flex; align-items:center; gap:10px; padding:14px; border-radius:14px; background:rgba(255,255,255,.03); border: var(--border);">
                <span style="font-size:1.3rem;">🕐</span>
                <div>
                    <div style="font-weight:800; font-size:0.9rem;">Mid-Week Service</div>
                    <div class="small">Wednesday 5:30 PM</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
