<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
$token = Session::getFormToken();
$user = Factory::getApplication()->getIdentity();
?>
<div id="dh-alert-box" class="mb-3"></div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>Deploy New Container</h5>
      </div>
      <div class="card-body">
        <form id="deploy-form" novalidate>
          <input type="hidden" id="csrf-token-name" value="<?php echo $token; ?>">
          <input type="hidden" id="user-id" value="<?php echo $user->id; ?>">
          <input type="hidden" id="username" value="<?php echo htmlspecialchars($user->username); ?>">

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="dc-name" class="form-label fw-semibold">Container Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="dc-name" placeholder="my-app" required pattern="[a-z0-9][a-z0-9_.-]*">
              <div class="form-text">Lowercase letters, numbers, dots, dashes, underscores.</div>
            </div>
            <div class="col-md-6">
              <label for="dc-image" class="form-label fw-semibold">Docker Image <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="dc-image" placeholder="nginx:latest" required>
              <div class="form-text">e.g. nginx:latest, piknar/d3b4:latest</div>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="dc-port" class="form-label fw-semibold">Container Port</label>
              <input type="text" class="form-control" id="dc-port" placeholder="8080">
              <div class="form-text">Internal port exposed by the container. A random host port will be assigned.</div>
            </div>
            <div class="col-md-6">
              <label for="dc-restart" class="form-label fw-semibold">Restart Policy</label>
              <select class="form-select" id="dc-restart">
                <option value="unless-stopped" selected>Unless Stopped</option>
                <option value="always">Always</option>
                <option value="on-failure">On Failure</option>
                <option value="no">No</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Environment Variables</label>
            <div id="env-rows"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addEnvRow()"><i class="fas fa-plus me-1"></i>Add Variable</button>
            <div class="form-text">Optional key-value pairs passed to the container.</div>
          </div>

          <div class="alert alert-info">
            <i class="fas fa-info-circle me-1"></i>
            <strong>Access:</strong> After deployment, your container will be accessible via the assigned host port shown in the Containers list.
          </div>

          <button type="submit" id="deploy-btn" class="btn btn-success btn-lg">
            <i class="fas fa-play me-1"></i>Deploy Container
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card bg-light">
      <div class="card-body">
        <h6 class="card-title"><i class="fas fa-user me-1"></i>Deploying as</h6>
        <p class="mb-2"><strong><?php echo htmlspecialchars($user->username); ?></strong></p>
        <small class="text-muted">Containers will be tagged with your user ID for easy management.</small>
      </div>
    </div>
    <div class="card mt-3">
      <div class="card-body">
        <h6 class="card-title"><i class="fas fa-lightbulb me-1"></i>Quick Examples</h6>
        <ul class="small mb-0">
          <li><code>nginx:latest</code> - Web server</li>
          <li><code>redis:alpine</code> - Cache server</li>
          <li><code>postgres:15</code> - Database</li>
          <li><code>piknar/d3b4:latest</code> - AI Agent</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
var envIdx = 0;
function addEnvRow(key, val) {
    key = key || ''; val = val || '';
    var i = envIdx++;
    var html = '<div class="input-group mb-2" id="env-row-' + i + '">' +
        '<input type="text" class="form-control font-monospace" placeholder="KEY" id="env-key-' + i + '" value="' + key + '">' +
        '<span class="input-group-text">=</span>' +
        '<input type="text" class="form-control" placeholder="value" id="env-val-' + i + '" value="' + val + '">' +
        '<button type="button" class="btn btn-outline-danger" onclick="document.getElementById(\'env-row-' + i + '\').remove()"><i class="fas fa-times"></i></button>' +
        '</div>';
    document.getElementById('env-rows').insertAdjacentHTML('beforeend', html);
}

function collectEnv() {
    var pairs = {};
    document.querySelectorAll('[id^="env-key-"]').forEach(function (el) {
        var idx = el.id.replace('env-key-', '');
        var k = el.value.trim();
        var v = document.getElementById('env-val-' + idx)?.value || '';
        if (k) pairs[k] = v;
    });
    return pairs;
}

function showAlert(type, msg) {
    document.getElementById('dh-alert-box').innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show">' + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

document.getElementById('deploy-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!this.checkValidity()) { this.classList.add('was-validated'); return; }

    var btn = document.getElementById('deploy-btn');
    var csrf = document.getElementById('csrf-token-name').value;
    var userId = document.getElementById('user-id').value;
    var username = document.getElementById('username').value;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deploying...';

    var body = new URLSearchParams();
    body.append(csrf, '1');
    body.append('name', document.getElementById('dc-name').value.trim());
    body.append('image', document.getElementById('dc-image').value.trim());
    body.append('port', document.getElementById('dc-port').value.trim());
    body.append('restart_policy', document.getElementById('dc-restart').value);
    body.append('user_id', userId);
    body.append('username', username);
    body.append('env_json', JSON.stringify(collectEnv()));

    try {
        var resp = await fetch('index.php?option=com_dockerhost&task=containers.deploy&format=json', {
            method: 'POST', body: body
        });
        var data = await resp.json();

        if (data.success) {
            var html = '<i class="fas fa-check-circle me-1"></i><strong>Deployed!</strong> ' + data.message;
            if (data.host_port) {
                html += '<div class="mt-2"><i class="fas fa-globe me-1"></i>Access your container at: <strong>http://your-server-ip:' + data.host_port + '</strong></div>';
            }
            html += ' <a href="index.php?option=com_dockerhost&view=containers" class="alert-link ms-2">View Containers &rarr;</a>';
            showAlert('success', html);
            document.getElementById('deploy-form').reset();
            document.getElementById('env-rows').innerHTML = '';
            envIdx = 0;
        } else {
            showAlert('danger', '<i class="fas fa-times-circle me-1"></i>' + (data.message || 'Deploy failed.'));
        }
    } catch (e) {
        showAlert('danger', 'Request failed: ' + e.message);
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-play me-1"></i>Deploy Container';
});
</script>
