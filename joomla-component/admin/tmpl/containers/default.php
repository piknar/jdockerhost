<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

$token = \Joomla\CMS\Session\Session::getFormToken();
$serverIp = '168.231.72.66';

function dh_status_badge(string $status): string {
    $s = strtolower(trim($status));
    if (str_contains($s, 'running') || str_contains($s, 'up')) {
        return '<span class="badge bg-success"><i class="fas fa-circle me-1" style="font-size:.6rem"></i>running</span>';
    }
    if (str_contains($s, 'exited') || str_contains($s, 'stopped')) {
        return '<span class="badge bg-danger"><i class="fas fa-circle me-1" style="font-size:.6rem"></i>' . htmlspecialchars($s, ENT_QUOTES) . '</span>';
    }
    if (str_contains($s, 'restarting')) {
        return '<span class="badge bg-info text-dark"><i class="fas fa-circle me-1" style="font-size:.6rem"></i>restarting</span>';
    }
    return '<span class="badge bg-secondary">' . htmlspecialchars($s, ENT_QUOTES) . '</span>';
}

function dh_get_ssl_url($labels): string {
    $sslPort = $labels['ssl_port'] ?? '';
    if ($sslPort && is_numeric($sslPort)) {
        return 'https://ymmude.com:' . (int)$sslPort;
    }
    return '';
}

function dh_get_direct_url($ports, string $serverIp): string {
    if (empty($ports) || !is_array($ports)) return '';
    foreach ($ports as $containerPort => $bindings) {
        if (!empty($bindings) && is_array($bindings)) {
            foreach ($bindings as $binding) {
                $hostPort = $binding['HostPort'] ?? null;
                if ($hostPort) {
                    return "http://{$serverIp}:{$hostPort}";
                }
            }
        }
    }
    return '';
}
?>
<div id="dh-alert-box" class="mb-3"></div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center gap-3">
    <?php if (!empty($this->health)): ?>
      <span class="badge bg-success fs-6 px-3 py-2"><i class="fas fa-check-circle me-1"></i> API Online</span>
    <?php elseif ($this->apiError): ?>
      <span class="badge bg-danger fs-6 px-3 py-2"><i class="fas fa-times-circle me-1"></i> API Error</span>
      <small class="text-danger"><?php echo htmlspecialchars($this->apiError, ENT_QUOTES); ?></small>
    <?php endif; ?>
    <div class="ms-auto d-flex gap-2">
      <a href="<?php echo Route::_('index.php?option=com_dockerhost&view=deploy'); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i>Deploy New
      </a>
      <a href="<?php echo Route::_('index.php?option=com_dockerhost&view=images'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-layer-group me-1"></i>Images
      </a>
      <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()" title="Refresh">
        <i class="fas fa-sync-alt"></i>
      </button>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex align-items-center bg-dark text-white py-2 px-3">
    <h5 class="mb-0 fw-semibold"><i class="fas fa-server me-2"></i>Containers
      <span class="badge bg-light text-dark ms-2"><?php echo count($this->containers); ?></span>
    </h5>
  </div>
  <div class="card-body p-0">
    <?php if (empty($this->containers)): ?>
      <div class="text-center py-5 text-muted">
        <i class="fas fa-inbox" style="font-size:3rem;opacity:.4"></i>
        <p class="mt-3 mb-1">No containers found.</p>
        <a href="<?php echo Route::_('index.php?option=com_dockerhost&view=deploy'); ?>" class="btn btn-primary btn-sm mt-2">
          <i class="fas fa-rocket me-1"></i>Deploy your first container
        </a>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover table-striped align-middle mb-0">
        <thead class="table-dark">
          <tr>
            <th>Name</th>
            <th>Image</th>
            <th>Status</th>
            <th>Owner</th>
            <th>Access</th>
            <th class="text-center" style="min-width:180px">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($this->containers as $c):
          $cid       = (string)($c['id'] ?? '');
          $cname     = ltrim((string)($c['name'] ?? $cid), '/');
          $image     = (string)($c['image'] ?? '');
          $state     = (string)($c['status'] ?? 'unknown');
          $isUp      = str_contains(strtolower($state), 'running') || str_contains(strtolower($state), 'up');
          $ports     = $c['ports'] ?? [];
          $labels    = (array)($c['labels'] ?? []);
          $username  = trim((string)($labels['username'] ?? ''));
          $sslUrl    = dh_get_ssl_url($labels);
          $directUrl = dh_get_direct_url($ports, $serverIp);
          $eid       = htmlspecialchars($cid, ENT_QUOTES, 'UTF-8');
          $ename     = htmlspecialchars($cname, ENT_QUOTES, 'UTF-8');
          $logsUrl   = Route::_('index.php?option=com_dockerhost&view=logs&id=' . urlencode($cid));
        ?>
        <tr>
          <td>
            <strong><?php echo $ename; ?></strong>
            <div class="text-muted font-monospace" style="font-size:.7rem"><?php echo substr($cid, 0, 12); ?></div>
          </td>
          <td><small class="text-break"><?php echo htmlspecialchars($image, ENT_QUOTES); ?></small></td>
          <td><?php echo dh_status_badge($state); ?></td>
          <td><?php echo $username ? '<span class="badge bg-info">' . htmlspecialchars($username, ENT_QUOTES) . '</span>' : '—'; ?></td>
          <td>
            <?php if ($sslUrl): ?>
              <a href="<?php echo htmlspecialchars($sslUrl); ?>" target="_blank" class="badge bg-success text-decoration-none d-block mb-1">
                <i class="fas fa-lock me-1"></i>SSL <?php echo htmlspecialchars($sslUrl); ?>
              </a>
            <?php endif; ?>
            <?php if ($directUrl): ?>
              <a href="<?php echo htmlspecialchars($directUrl); ?>" target="_blank" class="badge bg-secondary text-decoration-none d-block">
                <i class="fas fa-link me-1"></i>Direct <?php echo htmlspecialchars($directUrl); ?>
              </a>
            <?php endif; ?>
            <?php if (!$sslUrl && !$directUrl): ?>—<?php endif; ?>
          </td>
          <td class="text-center">
            <div class="d-flex flex-wrap gap-1 justify-content-center">
              <?php if (!$isUp): ?>
                <button class="btn btn-success btn-sm" onclick="dhAction('start','<?php echo $eid;?>')" title="Start"><i class="fas fa-play"></i></button>
              <?php else: ?>
                <button class="btn btn-warning btn-sm" onclick="dhAction('stop','<?php echo $eid;?>')" title="Stop"><i class="fas fa-stop"></i></button>
              <?php endif; ?>
              <button class="btn btn-info btn-sm" onclick="dhAction('restart','<?php echo $eid;?>')" title="Restart"><i class="fas fa-redo"></i></button>
              <a href="<?php echo $logsUrl; ?>" class="btn btn-secondary btn-sm" title="Logs"><i class="fas fa-file-alt"></i></a>
              <button class="btn btn-danger btn-sm" onclick="dhDelete('<?php echo $eid;?>','<?php echo $ename;?>')" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
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
var CSRF_TOKEN = '<?php echo $token; ?>';
function showAlert(type, html) {
    document.getElementById('dh-alert-box').innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show">' + html + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
window.dhAction = async function(action, id) {
    var btn = event.currentTarget, orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        var body = new URLSearchParams(); body.append('id', id); body.append(CSRF_TOKEN, '1');
        var resp = await fetch('index.php?option=com_dockerhost&task=containers.' + action + '&format=json', {method: 'POST', body: body});
        var data = await resp.json();
        if (data.success) { showAlert('success', data.message); setTimeout(() => location.reload(), 1500); }
        else { showAlert('danger', data.message || 'Failed'); btn.disabled = false; btn.innerHTML = orig; }
    } catch(e) { showAlert('danger', e.message); btn.disabled = false; btn.innerHTML = orig; }
};
window.dhDelete = function(id, name) {
    if (!confirm('Delete container "' + name + '"?')) return;
    dhAction('remove', id);
};
</script>
