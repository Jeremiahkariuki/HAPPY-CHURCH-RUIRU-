<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/helpers.php";

// Auto-migration: Ensure gallery table exists
try {
  $pdo->query("SELECT 1 FROM gallery LIMIT 1");
} catch (Exception $e) {
  $pdo->exec("CREATE TABLE IF NOT EXISTS `gallery` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `image_path` varchar(255) NOT NULL,
    `caption` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
  )");
}

$action = $_GET["action"] ?? "";
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$flash = flash_get();

// Handle Uploads
if ($_SERVER["REQUEST_METHOD"] === "POST" && in_array($_SESSION['user']['role'] ?? '', ["admin", "Receptionist", "Member"])) {
  csrf_verify();

  if (isset($_FILES["gallery_image"]) && $_FILES["gallery_image"]["error"] === UPLOAD_ERR_OK) {
    $caption = trim((string)($_POST["caption"] ?? ""));
    $ext = strtolower(pathinfo($_FILES["gallery_image"]["name"], PATHINFO_EXTENSION));
    
    // Validate extension
    $allowed = ["jpg","jpeg","png","gif","webp"];
    if (!in_array($ext, $allowed)) {
        flash_set("Only images (JPG, PNG, WEBP, GIF) are allowed.", "error");
        redirect("gallery.php");
    }

    $filename = bin2hex(random_bytes(8)) . "." . $ext;
    $uploadDir = __DIR__ . "/uploads/gallery";
    
    // Ensure directory exists
    if (!file_exists($uploadDir)) {
      mkdir($uploadDir, 0777, true);
    }

    $target = $uploadDir . "/" . $filename;
    
    if (move_uploaded_file($_FILES["gallery_image"]["tmp_name"], $target)) {
      $path = "uploads/gallery/" . $filename;
      $stmt = $pdo->prepare("INSERT INTO gallery (image_path, caption) VALUES (?, ?)");
      $stmt->execute([$path, $caption]);
      flash_set("Photo added to gallery successfully!");
    } else {
      flash_set("System Error: Failed to save image to $uploadDir. Check folder permissions.", "error");
    }
  } else {
      flash_set("Please select a valid image file within the size limit.", "error");
  }
  redirect("gallery.php");
}

// Handle Deletion
if ($action === "delete" && $id > 0 && in_array($_SESSION['user']['role'] ?? '', ["admin", "Receptionist", "Member"])) {
  $stmt = $pdo->prepare("SELECT image_path FROM gallery WHERE id = ?");
  $stmt->execute([$id]);
  $img = $stmt->fetchColumn();
  if ($img && file_exists(__DIR__ . "/" . $img)) {
    unlink(__DIR__ . "/" . $img);
  }
  $pdo->prepare("DELETE FROM gallery WHERE id = ?")->execute([$id]);
  flash_set("Photo removed from gallery.");
  redirect("gallery.php");
}

$photos = $pdo->query("SELECT * FROM gallery ORDER BY created_at DESC")->fetchAll();

require_once __DIR__ . "/header.php";
?>

<style>
  .gallery-grid {
    column-count: 4;
    column-gap: 20px;
    margin-top: 20px;
  }
  @media (max-width: 1200px) { .gallery-grid { column-count: 3; } }
  @media (max-width: 800px) { .gallery-grid { column-count: 2; } }
  @media (max-width: 500px) { .gallery-grid { column-count: 1; } }

  .gallery-item {
    break-inside: avoid;
    margin-bottom: 20px;
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.05);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    animation: itemFade 0.6s ease backwards;
  }
  @keyframes itemFade {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .gallery-item:hover {
    transform: translateY(-5px);
    border-color: var(--brand);
    box-shadow: 0 10px 30px rgba(124,92,255,0.2);
  }
  .gallery-item img {
    width: 100%;
    display: block;
    transition: transform 0.5s ease;
  }
  .gallery-item:hover img {
    transform: scale(1.05);
  }
  .gallery-overlay {
    position: absolute;
    inset:0;
    background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 60%);
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 20px;
  }
  .gallery-item:hover .gallery-overlay {
    opacity: 1;
  }
  .gallery-caption {
    color: white;
    font-weight: 850;
    font-size: 0.95rem;
    margin: 0;
    transform: translateY(10px);
    transition: transform 0.3s ease;
  }
  .gallery-item:hover .gallery-caption {
    transform: translateY(0);
  }
  .gallery-caption {
    white-space: pre-wrap;
    max-height: 100px;
    overflow-y: auto;
  }

  /* Upload Modal Stylings */
  .modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(8px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
  }
  .modal.open { display: flex; }
  .modal-content {
    background: #0f1a2e;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 24px;
    width: 100%;
    max-width: 500px;
    padding: 30px;
    position: relative;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
  }

  /* Lightbox */
  #lightbox {
    position: fixed;
    inset: 0;
    background: rgba(7, 16, 31, 0.95);
    backdrop-filter: blur(15px);
    display: none;
    z-index: 2000;
    padding: 40px;
    text-align: center;
  }
  #lightbox.open { display: flex; flex-direction: column; align-items: center; justify-content: center; }
  #lightbox img {
    max-width: 90%;
    max-height: 80vh;
    border-radius: 12px;
    box-shadow: 0 30px 60px rgba(0,0,0,0.5);
  }
  #lightbox-caption {
    margin-top: 20px;
    color: white;
    font-size: 1.2rem;
    font-weight: 800;
  }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
  <div>
    <h1 style="margin:0; font-weight:950; font-size:2.2rem; background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.7) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Church Gallery</h1>
    <p style="margin:5px 0 0; color:var(--muted); font-size:1rem;">Capturing the heartbeat of our community.</p>
  </div>
  <?php if (in_array($_SESSION['user']['role'] ?? '', ["admin", "Receptionist", "Member"])): ?>
    <button class="btn" onclick="document.getElementById('uploadModal').classList.add('open')">
      <span style="font-size:1.2rem; margin-right:8px;">📸</span> Add Photo
    </button>
  <?php endif; ?>
</div>

<?php if ($flash): ?>
  <div class="flash <?= e($flash["type"] ?? "success") ?>" style="margin-bottom:20px;"><?= e($flash["msg"] ?? "") ?></div>
<?php endif; ?>

<div class="gallery-grid">
  <?php foreach ($photos as $p): ?>
    <div class="gallery-item" onclick="openLightbox('<?= e($p['image_path']) ?>', '<?= e($p['caption'] ?: '') ?>')">
      <img src="<?= e($p['image_path']) ?>" loading="lazy">
      <div class="gallery-overlay">
        <?php if ($p['caption']): ?>
          <p class="gallery-caption"><?= nl2br(e($p['caption'])) ?></p>
        <?php endif; ?>
        <?php if (in_array($_SESSION['user']['role'] ?? '', ["admin", "Receptionist", "Member"])): ?>
          <a href="gallery.php?action=delete&id=<?= $p['id'] ?>" 
             onclick="event.stopPropagation(); return confirm('Remove this photo?');"
             style="position:absolute; top:15px; right:15px; background:rgba(255,77,109,0.2); backdrop-filter:blur(4px); color:#ff4d6d; padding:8px; border-radius:10px; text-decoration:none; font-size:0.8rem; border:1px solid rgba(255,77,109,0.3);">
             🗑️
          </a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$photos): ?>
    <div style="grid-column: 1/-1; text-align:center; padding:100px 20px; color:var(--muted);">
      <div style="font-size:4rem; margin-bottom:20px;">🖼️</div>
      <h2>Our gallery is waiting for its first photo.</h2>
      <p>Soon this space will be filled with beautiful church memories.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="modal">
  <div class="modal-content">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
      <h2 style="margin:0; font-weight:950;">Add to Gallery</h2>
      <button onclick="document.getElementById('uploadModal').classList.remove('open')" style="background:none; border:none; color:var(--muted); font-size:1.5rem; cursor:pointer;">✕</button>
    </div>
    <form method="post" enctype="multipart/form-data" style="display:grid; gap:20px;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div>
        <label class="small">Upload Photo</label>
        <input class="input" type="file" name="gallery_image" accept="image/*" required>
      </div>
      <div>
        <label class="small">Image Details / Caption</label>
        <textarea class="textarea" name="caption" placeholder="Describe this moment..." style="min-height:80px;"></textarea>
      </div>
      <button class="btn" type="submit" style="padding:14px;">Publish Photo</button>
    </form>
  </div>
</div>

<!-- Lightbox -->
<div id="lightbox" onclick="this.classList.remove('open')">
  <img id="lightbox-img" src="">
  <div id="lightbox-caption"></div>
  <div style="position:absolute; top:30px; right:40px; color:white; font-size:2rem; cursor:pointer;">✕</div>
</div>

<script>
function openLightbox(src, caption) {
  const lb = document.getElementById('lightbox');
  document.getElementById('lightbox-img').src = src;
  document.getElementById('lightbox-caption').innerText = caption;
  lb.classList.add('open');
}
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
