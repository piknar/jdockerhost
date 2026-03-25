<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
$token = \Joomla\CMS\Session\Session::getFormToken();

function dh_img_size(int $bytes): string {
    if ($bytes <= 0)         return '&mdash;';
    if ($bytes < 1048576)    return round($bytes / 1024, 0) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}
?>
<div id="dh-alert-box" class="mb-3"></div>

<!-- Pull New Image -->
<div class="card shadow-sm mb-3">
  <div class="card-header bg-primary text-white">
    <h6 class="mb-0"><i class="fas fa-download me-2"></i>Pull New Image</h6>
  </div>
  <div class="card-body">
    <div class="row g-2 align-items-end" style="max-width:640px">
      <div class="col">
        <label for="pull-image-input" class="form-label fw-semibold">Image Name</label>
        <input type="text" class="form-control" id="pull-image-input"
               placeholder="nginx:latest, piknar/d3b4:latest, postgres:15-alpine">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary" id="pull-btn" onclick="doPull()">
          <i class="fas fa-download me-1"></i>Pull
        </button>
      </div>
    </div>
    <div class="form-text mt-1">
      Pulls from Docker Hub. Large images may take a while &mdash; refresh the page after pulling.
    </div>
  </div>
</div>

<!-- Images Table -->
<div class="card shadow-sm">
  <div class="card-header d-flex align-items-center bg-dark text-white py-2 px-3">
    <h5 class="mb-0 fw-semibold">
      <i class="fas fa-layer-group me-2"></i>Local Images
      <span class="badge bg-light text-dark ms-2"><?php echo count($this->images); ?></span>
    </h5>
    <button class="btn btn-outline-light btn-sm ms-auto" onclick="location.reload()" title="Refresh">
      <i class="fas fa-sync-alt"></i>
    </button>
  </div>
  <div class="card-body p-0">
    <?php if (empty($this->images)): ?>
      <div class="text-center py-5 text-muted">
        <i class="fas fa-inbox" style="font-size:3rem;opacity:.4"></i>
        <p class="mt-3">No images found. Pull one above.</p>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover table-striped align-middle mb-0">
        <thead class="table-dark">
          <tr>
            <th>Repository / Tags</th>
            <th>Image ID</th>
            <th>Size</th>
            <th>Created</th>
            <th class="text-center" style="min-width:120px">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($this->images as $img):
          $iid   = (string)($img['id']       ?? $img['Id']       ?? '');
          $tags  = $img['tags']              ?? $img['RepoTags'] ?? [];
          if (is_string($tags)) $tags = [$tags];
          $tags  = array_values(array_filter((array)$tags, fn($t) => $t && $t !== '<none>:<none>'));
          $tagsHtml = !empty($tags)
            ? implode('<br>', array_map(fn($t) => '<code class="small">' . htmlspecialchars($t, ENT_QUOTES) . '</code>', $tags))
            : '<span class="text-muted small">&lt;none&gt;</span>';
          $sz    = (int)($img['size']    ?? $img['Size']    ?? 0);
          $cr    = htmlspecialchars((string)($img['created'] ?? $img['Created'] ?? ''), ENT_QUOTES);
          $eid   = htmlspecialchars($iid, ENT_QUOTES);
          $etags = htmlspecialchars(implode(', ', $tags) ?: $iid, ENT_QUOTES);
        ?>
        <tr>
          <td><?php echo $tagsHtml; ?></td>
          <td><span class="font-monospace text-muted" style="font-size:.75rem"><?php echo htmlspecialchars(substr($iid, 0, 17), ENT_QUOTES); ?></span></td>
          <td><small><?php echo dh_img_size($sz); ?></small></td>
          <td><small><?php echo $cr; ?></small></td>
          <td class="text-center">
            <button class="btn btn-danger btn-sm" onclick="doDeleteImage('<?php echo $eid; ?>','<?php echo $etags; ?>')">
              <i class="fas fa-trash me-1"></i>Delete
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
    'use strict';
    var CSRF = '<?php echo $token; ?>';

    function showAlert(type, html) {
        var box = document.getElementById('dh-alert-box');
        box.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show shadow-sm">' +
            html + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        setTimeout(function () {
            var a = box.querySelector('.alert');
            if (a) { a.classList.remove('show'); setTimeout(function () { box.innerHTML = ''; }, 300); }
        }, 8000);
    }

    window.doPull = async function () {
        var img = document.getElementById('pull-image-input').value.trim();
        if (!img) { showAlert('warning', 'Please enter an image name.'); return; }
        var btn = document.getElementById('pull-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Pulling...';
        try {
            var body = new URLSearchParams();
            body.append('image', img);
            body.append(CSRF, '1');
            var resp = await fetch('index.php?option=com_dockerhost&task=images.pull&format=json', {
                method: 'POST', body: body
            });
            var data = await resp.json();
            showAlert(
                data.success ? 'success' : 'danger',
                (data.success ? '<i class="fas fa-check-circle me-1"></i>' : '<i class="fas fa-times-circle me-1"></i>') +
                data.message
            );
            if (data.success) {
                setTimeout(function () { location.reload(); }, 3000);
            }
        } catch (e) {
            showAlert('danger', 'Request error: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download me-1"></i>Pull';
        }
    };

    document.getElementById('pull-image-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); window.doPull(); }
    });

    window.doDeleteImage = async function (id, label) {
        if (!confirm('Delete image "' + label + '"?\n\nEnsure no running containers use this image.')) return;
        try {
            var body = new URLSearchParams();
            body.append('id', id);
            body.append(CSRF, '1');
            var resp = await fetch('index.php?option=com_dockerhost&task=images.remove&format=json', {
                method: 'POST', body: body
            });
            var data = await resp.json();
            showAlert(
                data.success ? 'success' : 'danger',
                (data.success ? '<i class="fas fa-check-circle me-1"></i>' : '<i class="fas fa-times-circle me-1"></i>') +
                data.message
            );
            if (data.success) {
                setTimeout(function () { location.reload(); }, 2000);
            }
        } catch (e) {
            showAlert('danger', 'Request error: ' + e.message);
        }
    };
}());
</script>
