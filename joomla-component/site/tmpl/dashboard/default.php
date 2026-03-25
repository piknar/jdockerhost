<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

$token = \Joomla\CMS\Session\Session::getFormToken();
$serverIp = '168.231.72.66';
?>

<div class="dockerhost-dashboard">
    <?php if ($this->isGuest): ?>
    <!-- Guest View - Login Prompt -->
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-user-lock" style="font-size:4rem;color:#6c757d;opacity:.5"></i>
            <h3 class="mt-4">Login Required</h3>
            <p class="text-muted">Please log in to view and manage your containers.</p>
            <a href="<?php echo Route::_('index.php?option=com_users&view=login'); ?>" class="btn btn-primary btn-lg mt-3">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </a>
            <p class="mt-4 mb-0">
                <small class="text-muted">Don't have an account? 
                    <a href="<?php echo Route::_('index.php?option=com_users&view=registration'); ?>">Register here</a>
                </small>
            </p>
        </div>
    </div>
    <?php else: ?>
    <!-- Logged-in User View -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-server me-2"></i>My Containers</h2>
            <p class="text-muted mb-0">Welcome, <strong><?php echo htmlspecialchars($this->user->name); ?></strong></p>
        </div>
        <button class="btn btn-primary" onclick="openDeployModal()">
            <i class="fas fa-plus me-1"></i>Deploy New Container
        </button>
    </div>

    <div id="dh-alert-box" class="mb-3"></div>

    <?php if ($this->apiError): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        API Error: <?php echo htmlspecialchars($this->apiError); ?>
    </div>
    <?php elseif (empty($this->containers)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-cloud" style="font-size:4rem;color:#6c757d;opacity:.5"></i>
            <h4 class="mt-4">No Containers Yet</h4>
            <p class="text-muted">You haven't deployed any containers yet.</p>
            <button class="btn btn-primary mt-2" onclick="openDeployModal()">
                <i class="fas fa-rocket me-1"></i>Deploy Your First Container
            </button>
        </div>
    </div>
    <?php else: ?>
    <!-- Containers Grid -->
    <div class="row g-4">
        <?php foreach ($this->containers as $c):
            $cid = (string)($c['id'] ?? '');
            $cname = ltrim((string)($c['name'] ?? $cid), '/');
            $image = (string)($c['image'] ?? '');
            $state = (string)($c['status'] ?? 'unknown');
            $isUp = str_contains(strtolower($state), 'running') || str_contains(strtolower($state), 'up');
            $labels = (array)($c['labels'] ?? []);
            $sslPort = $labels['ssl_port'] ?? '';
            $sslUrl = $sslPort ? 'https://ymmude.com:' . (int)$sslPort : '';
            
            // Get direct URL
            $directUrl = '';
            $ports = $c['ports'] ?? [];
            if (is_array($ports)) {
                foreach ($ports as $containerPort => $bindings) {
                    if (!empty($bindings) && is_array($bindings)) {
                        $hostPort = $bindings[0]['HostPort'] ?? null;
                        if ($hostPort) {
                            $directUrl = "http://{$serverIp}:{$hostPort}";
                            break;
                        }
                    }
                }
            }
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm <?php echo $isUp ? 'border-success' : 'border-secondary'; ?>">
                <div class="card-header d-flex align-items-center <?php echo $isUp ? 'bg-success text-white' : 'bg-secondary text-white'; ?>">
                    <i class="fas fa-<?php echo $isUp ? 'check-circle' : 'circle'; ?> me-2"></i>
                    <strong class="text-truncate"><?php echo htmlspecialchars($cname); ?></strong>
                </div>
                <div class="card-body">
                    <p class="mb-2"><small class="text-muted">Image:</small><br>
                        <code class="text-break"><?php echo htmlspecialchars($image); ?></code>
                    </p>
                    <p class="mb-2">
                        <small class="text-muted">Status:</small><br>
                        <?php if ($isUp): ?>
                            <span class="badge bg-success"><i class="fas fa-circle me-1" style="font-size:.5rem"></i>Running</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($state); ?></span>
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($sslUrl || $directUrl): ?>
                    <div class="mt-3">
                        <small class="text-muted d-block mb-2">Access URLs:</small>
                        <?php if ($sslUrl): ?>
                        <a href="<?php echo htmlspecialchars($sslUrl); ?>" target="_blank" class="btn btn-success btn-sm w-100 mb-2">
                            <i class="fas fa-lock me-1"></i>Open (SSL)
                        </a>
                        <?php endif; ?>
                        <?php if ($directUrl): ?>
                        <a href="<?php echo htmlspecialchars($directUrl); ?>" target="_blank" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fas fa-link me-1"></i>Direct Link
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if (!$isUp): ?>
                        <button class="btn btn-success btn-sm flex-fill" onclick="dhAction('start','<?php echo htmlspecialchars($cid); ?>')">
                            <i class="fas fa-play"></i> Start
                        </button>
                        <?php else: ?>
                        <button class="btn btn-warning btn-sm flex-fill" onclick="dhAction('stop','<?php echo htmlspecialchars($cid); ?>')">
                            <i class="fas fa-stop"></i> Stop
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-info btn-sm" onclick="dhAction('restart','<?php echo htmlspecialchars($cid); ?>')" title="Restart">
                            <i class="fas fa-redo"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="dhDelete('<?php echo htmlspecialchars($cid); ?>','<?php echo htmlspecialchars($cname); ?>')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Deploy Modal -->
    <div class="modal fade" id="deployModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-rocket me-2"></i>Deploy New Container</h5>
                    <button type="button" class="btn-close btn-close-white" onclick="closeDeployModal()"></button>
                </div>
                <div class="modal-body">
                    <form id="deploy-form">
                        <input type="hidden" id="csrf-token" value="<?php echo $token; ?>">
                        <input type="hidden" id="user-id" value="<?php echo $this->user->id; ?>">
                        <input type="hidden" id="username" value="<?php echo htmlspecialchars($this->user->username); ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Container Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="dc-name" required pattern="[a-z0-9][a-z0-9_.-]*" placeholder="my-app">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Docker Image <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="dc-image" required placeholder="nginx:latest">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Container Port</label>
                                <input type="number" class="form-control" id="dc-port" placeholder="8080">
                                <small class="text-muted">Port your app listens on inside the container</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Restart Policy</label>
                                <select class="form-select" id="dc-restart">
                                    <option value="unless-stopped" selected>Unless Stopped</option>
                                    <option value="always">Always</option>
                                    <option value="on-failure">On Failure</option>
                                    <option value="no">Never</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label">Environment Variables</label>
                            <div id="env-rows"></div>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addEnvRow()">
                                <i class="fas fa-plus me-1"></i>Add Variable
                            </button>
                        </div>
                        
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Your container will automatically get an SSL URL like <strong>https://ymmude.com:9002</strong>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeployModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" id="deploy-btn" onclick="doDeploy()">
                        <i class="fas fa-rocket me-1"></i>Deploy
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
var CSRF_TOKEN = '<?php echo $token; ?>';
var envIdx = 0;

function addEnvRow(key, val) {
    key = key || ''; val = val || '';
    var i = envIdx++;
    var html = '<div class="input-group mb-2" id="env-row-' + i + '">' +
        '<input type="text" class="form-control" placeholder="KEY" id="env-key-' + i + '" value="' + key + '">' +
        '<span class="input-group-text">=</span>' +
        '<input type="text" class="form-control" placeholder="value" id="env-val-' + i + '" value="' + val + '">' +
        '<button type="button" class="btn btn-outline-danger" onclick="document.getElementById(\'env-row-' + i + '\').remove()"><i class="fas fa-times"></i></button></div>';
    document.getElementById('env-rows').insertAdjacentHTML('beforeend', html);
}

function collectEnv() {
    var pairs = {};
    document.querySelectorAll('[id^="env-key-"]').forEach(function(el) {
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

async function doDeploy() {
    var btn = document.getElementById('deploy-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deploying...';
    
    var body = new URLSearchParams();
    body.append(CSRF_TOKEN, '1');
    body.append('name', document.getElementById('dc-name').value.trim());
    body.append('image', document.getElementById('dc-image').value.trim());
    body.append('port', document.getElementById('dc-port').value.trim());
    body.append('restart_policy', document.getElementById('dc-restart').value);
    body.append('user_id', document.getElementById('user-id').value);
    body.append('username', document.getElementById('username').value);
    body.append('env_json', JSON.stringify(collectEnv()));
    
    try {
        var resp = await fetch('index.php?option=com_dockerhost&task=dashboard.deploy&format=json', {
            method: 'POST', body: body
        });
        var data = await resp.json();
        if (data.success) {
            closeDeployModal();
            var msg = '<i class="fas fa-check-circle me-1"></i>' + data.message;
            if (data.ssl_url) msg += '<br><a href="' + data.ssl_url + '" target="_blank" class="alert-link">' + data.ssl_url + '</a>';
            showAlert('success', msg);
            setTimeout(function() { location.reload(); }, 2000);
        } else {
            showAlert('danger', '<i class="fas fa-times-circle me-1"></i>' + (data.message || 'Deploy failed'));
        }
    } catch(e) {
        showAlert('danger', 'Error: ' + e.message);
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-rocket me-1"></i>Deploy';
}

async function dhAction(action, id) {
    var btn = event.currentTarget;
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    var body = new URLSearchParams();
    body.append('id', id);
    body.append(CSRF_TOKEN, '1');
    
    try {
        var resp = await fetch('index.php?option=com_dockerhost&task=dashboard.' + action + '&format=json', {
            method: 'POST', body: body
        });
        var data = await resp.json();
        if (data.success) {
            showAlert('success', '<i class="fas fa-check-circle me-1"></i>' + data.message);
            setTimeout(function() { location.reload(); }, 1500);
        } else {
            showAlert('danger', '<i class="fas fa-times-circle me-1"></i>' + (data.message || 'Failed'));
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    } catch(e) {
        showAlert('danger', 'Error: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

function dhDelete(id, name) {
    if (!confirm('Delete container "' + name + '"?\n\nThis cannot be undone.')) return;
    dhAction('remove', id);
}

function openDeployModal() {
    var modalEl = document.getElementById('deployModal');
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
    } else {
        // Fallback: plain JS show
        modalEl.style.display = 'block';
        modalEl.classList.add('show');
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'dh-backdrop';
        document.body.appendChild(backdrop);
    }
}

function closeDeployModal() {
    var modalEl = document.getElementById('deployModal');
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    } else {
        modalEl.style.display = 'none';
        modalEl.classList.remove('show');
        document.body.classList.remove('modal-open');
        var backdrop = document.getElementById('dh-backdrop');
        if (backdrop) backdrop.remove();
    }
}

</script>

<style>
.dockerhost-dashboard { max-width: 1200px; margin: 0 auto; padding: 20px; }
.card { transition: transform 0.2s, box-shadow 0.2s; }
.card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
</style>
