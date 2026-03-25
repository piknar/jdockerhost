<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
$s = $this->settings;
?>
<div class="row g-3">

  <!-- Settings Form -->
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>DockerHost API Settings</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="<?php echo Route::_('index.php?option=com_dockerhost&task=settings.save'); ?>">
          <?php echo HTMLHelper::_('form.token'); ?>

          <div class="mb-3">
            <label for="api_url" class="form-label fw-semibold">API Base URL</label>
            <input type="url" class="form-control font-monospace" id="api_url" name="api_url"
                   value="<?php echo htmlspecialchars($s['api_url'] ?? 'http://127.0.0.1:7001', ENT_QUOTES); ?>"
                   placeholder="http://127.0.0.1:7001" required>
            <div class="form-text">Base URL of the DockerHost API (no trailing slash).</div>
          </div>

          <div class="mb-4">
            <label for="api_token" class="form-label fw-semibold">API Bearer Token</label>
            <div class="input-group">
              <input type="password" class="form-control font-monospace" id="api_token" name="api_token"
                     value="<?php echo htmlspecialchars($s['api_token'] ?? '', ENT_QUOTES); ?>"
                     placeholder="dockerhost-secret-token-2026" required>
              <button type="button" class="btn btn-outline-secondary" id="toggle-token-btn"
                      onclick="toggleTokenVisibility()" title="Show/hide token">
                <i class="fas fa-eye" id="toggle-token-icon"></i>
              </button>
            </div>
            <div class="form-text">Bearer token sent in <code>Authorization</code> header on every API request.</div>
          </div>

          <div class="d-flex gap-2 pt-3 border-top">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>Save Settings
            </button>
            <button type="button" class="btn btn-outline-info" id="test-btn" onclick="testConnection()">
              <i class="fas fa-plug me-1"></i>Test Connection
            </button>
            <a href="<?php echo Route::_('index.php?option=com_dockerhost&view=containers'); ?>" class="btn btn-secondary ms-auto">
              <i class="fas fa-list me-1"></i>Containers
            </a>
          </div>
        </form>
        <div id="test-result" class="mt-3"></div>
      </div>
    </div>
  </div>

  <!-- Info Panels -->
  <div class="col-lg-5">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Configuration Info</h6></div>
      <div class="card-body small">
        <table class="table table-sm mb-0">
          <tr><td class="fw-semibold text-nowrap">Default URL</td><td><code>http://127.0.0.1:7001</code></td></tr>
          <tr><td class="fw-semibold text-nowrap">Default Token</td><td><code>dockerhost-secret-token-2026</code></td></tr>
          <tr><td class="fw-semibold text-nowrap">Auth Header</td><td><code>Authorization: Bearer &lt;token&gt;</code></td></tr>
          <tr class="table-warning"><td class="fw-semibold text-nowrap">Note</td><td>API must be reachable from the Joomla server (not browser).</td></tr>
        </table>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-list me-2"></i>API Endpoints</h6></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0 small font-monospace">
          <thead class="table-dark"><tr><th>Method</th><th>Path</th></tr></thead>
          <tbody>
            <tr><td><span class="badge bg-primary">GET</span></td><td>/health</td></tr>
            <tr><td><span class="badge bg-primary">GET</span></td><td>/containers</td></tr>
            <tr><td><span class="badge bg-warning text-dark">POST</span></td><td>/containers/deploy</td></tr>
            <tr><td><span class="badge bg-primary">GET</span></td><td>/containers/{id}</td></tr>
            <tr><td><span class="badge bg-warning text-dark">POST</span></td><td>/containers/{id}/start</td></tr>
            <tr><td><span class="badge bg-warning text-dark">POST</span></td><td>/containers/{id}/stop</td></tr>
            <tr><td><span class="badge bg-warning text-dark">POST</span></td><td>/containers/{id}/restart</td></tr>
            <tr><td><span class="badge bg-danger">DEL</span></td><td>/containers/{id}</td></tr>
            <tr><td><span class="badge bg-primary">GET</span></td><td>/containers/{id}/logs</td></tr>
            <tr><td><span class="badge bg-primary">GET</span></td><td>/containers/{id}/stats</td></tr>
            <tr><td><span class="badge bg-primary">GET</span></td><td>/images</td></tr>
            <tr><td><span class="badge bg-warning text-dark">POST</span></td><td>/images/pull</td></tr>
            <tr><td><span class="badge bg-danger">DEL</span></td><td>/images/{id}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script>
(function () {
    'use strict';

    window.toggleTokenVisibility = function () {
        var inp  = document.getElementById('api_token');
        var icon = document.getElementById('toggle-token-icon');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            inp.type = 'password';
            icon.className = 'fas fa-eye';
        }
    };

    window.testConnection = async function () {
        var url   = document.getElementById('api_url').value.trim().replace(/\/+$/, '');
        var token = document.getElementById('api_token').value.trim();
        var res   = document.getElementById('test-result');
        var btn   = document.getElementById('test-btn');
        if (!url) {
            res.innerHTML = '<div class="alert alert-warning py-2 mb-0">Please enter an API URL first.</div>';
            return;
        }
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing...';
        res.innerHTML = '';
        try {
            var resp = await fetch(url + '/health', {
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                },
                signal: AbortSignal.timeout(6000)
            });
            if (resp.ok) {
                var data = await resp.json();
                res.innerHTML = '<div class="alert alert-success py-2 mb-0">' +
                    '<i class="fas fa-check-circle me-1"></i><strong>Connected!</strong> Status: ' +
                    (data.status || 'OK') + '</div>';
            } else {
                res.innerHTML = '<div class="alert alert-warning py-2 mb-0">' +
                    '<i class="fas fa-exclamation-triangle me-1"></i>HTTP ' + resp.status +
                    ' &mdash; Check your token or API configuration.</div>';
            }
        } catch (e) {
            res.innerHTML = '<div class="alert alert-secondary py-2 mb-0">' +
                '<i class="fas fa-info-circle me-1"></i>' +
                '<strong>Note:</strong> Browser cannot reach the API directly (CORS/network). ' +
                'Save and test via the <a href="index.php?option=com_dockerhost&view=containers">Containers dashboard</a>.' +
                '</div>';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plug me-1"></i>Test Connection';
        }
    };
}());
</script>
