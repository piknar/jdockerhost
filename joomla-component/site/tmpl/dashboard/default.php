<?php defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

$app   = Factory::getApplication();
$user  = $app->getIdentity();
$token = Session::getFormToken();

$containers = $this->containers ?? [];
?>
<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-docker me-2"></i>My Containers</h2>
    
    <?php if (!$user || $user->guest): ?>
        <div class="alert alert-info">
            <h4>Login Required</h4>
            <p>Please log in to view and manage your containers.</p>
            <a href="<?php echo $app->get('sef') ? 'login' : 'index.php?option=com_users&view=login'; ?>" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-1"></i>Log In
            </a>
        </div>
    <?php else: ?>
        
        <!-- Deploy Form -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>Deploy New Container</h5>
            </div>
            <div class="card-body">
                <form id="deploy-form">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Container Name *</label>
                            <input type="text" class="form-control" id="dc-name" placeholder="my-app" required pattern="[a-z0-9][a-z0-9_.-]*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Docker Image *</label>
                            <input type="text" class="form-control" id="dc-image" placeholder="nginx:latest" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Container Port</label>
                            <input type="text" class="form-control" id="dc-port" placeholder="8080">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Restart Policy</label>
                            <select class="form-select" id="dc-restart">
                                <option value="unless-stopped" selected>Unless Stopped</option>
                                <option value="always">Always</option>
                                <option value="on-failure">On Failure</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Environment Variables</label>
                            <button type="button" class="btn btn-outline-secondary" onclick="addEnvRow()">
                                <i class="fas fa-plus me-1"></i>Add Variable
                            </button>
                        </div>
                    </div>
                    <div id="env-rows" class="mt-3"></div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success" id="deploy-btn">
                            <i class="fas fa-play me-1"></i>Deploy Container
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Alert Box -->
        <div id="dh-alert-box"></div>

        <!-- Containers List -->
        <?php if (empty($containers)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-1"></i>No containers yet. Deploy your first container above!
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($containers as $c): 
                    $cid   = (string)($c['id'] ?? '');
                    $cname = ltrim((string)($c['name'] ?? $cid), '/');
                    $image = (string)($c['image'] ?? '');
                    $state = (string)($c['status'] ?? 'unknown');
                    $isUp  = str_contains(strtolower($state), 'running') || str_contains(strtolower($state), 'up');
                    $labels = (array)($c['labels'] ?? []);
                    $sslPort = $labels['ssl_port'] ?? '';
                    $sslUrl = $sslPort ? 'https://jtest.ymmude.com:' . (int)$sslPort : '';
                    
                    // Get owner info from labels
                    $owner = $labels['username'] ?? 'Unknown';
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 <?php echo $isUp ? 'border-success' : 'border-secondary'; ?>">
                        <div class="card-header d-flex justify-content-between align-items-center <?php echo $isUp ? 'bg-success text-white' : 'bg-secondary text-white'; ?>">
                            <span class="text-truncate"><i class="fas fa-<?php echo $isUp ? 'check-circle' : 'circle'; ?> me-1"></i><?php echo htmlspecialchars($cname); ?></span>
                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($owner); ?></span>
                        </div>
                        <div class="card-body">
                            <p class="mb-1"><small class="text-muted">Image:</small><br><code class="small"><?php echo htmlspecialchars($image); ?></code></p>
                            <p class="mb-1"><small class="text-muted">Status:</small><br><?php echo $isUp ? '<span class="badge bg-success">Running</span>' : '<span class="badge bg-secondary">' . htmlspecialchars($state) . '</span>'; ?></p>
                            <?php if ($sslUrl): ?>
                            <p class="mb-0 mt-2">
                                <a href="<?php echo htmlspecialchars($sslUrl); ?>" target="_blank" class="btn btn-success btn-sm w-100">
                                    <i class="fas fa-lock me-1"></i>Open (SSL)
                                </a>
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-flex gap-2">
                                <?php if ($isUp): ?>
                                    <button class="btn btn-warning btn-sm flex-fill" onclick="dhAction('stop', '<?php echo htmlspecialchars($cid); ?>')">
                                        <i class="fas fa-stop"></i> Stop
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-success btn-sm flex-fill" onclick="dhAction('start', '<?php echo htmlspecialchars($cid); ?>')">
                                        <i class="fas fa-play"></i> Start
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" onclick="dhAction('delete', '<?php echo htmlspecialchars($cid); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
var envIdx = 0;
function addEnvRow(key, val) {
    key = key || ''; val = val || '';
    var i = envIdx++;
    var html = '<div class="row g-2 mb-2" id="env-row-' + i + '">' +
        '<div class="col-5"><input type="text" class="form-control form-control-sm" placeholder="KEY" id="env-key-' + i + '" value="' + key + '"></div>' +
        '<div class="col-5"><input type="text" class="form-control form-control-sm" placeholder="value" id="env-val-' + i + '" value="' + val + '"></div>' +
        '<div class="col-2"><button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById(\'env-row-' + i + '\').remove()">Remove</button></div>' +
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

<?php if ($user && !$user->guest): ?>
document.getElementById('deploy-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    var btn = document.getElementById('deploy-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deploying...';

    var body = new URLSearchParams();
    body.append('<?php echo $token; ?>', '1');
    body.append('name', document.getElementById('dc-name').value.trim());
    body.append('image', document.getElementById('dc-image').value.trim());
    body.append('port', document.getElementById('dc-port').value.trim());
    body.append('restart_policy', document.getElementById('dc-restart').value);
    body.append('env_json', JSON.stringify(collectEnv()));

    try {
        var resp = await fetch('index.php?option=com_dockerhost&task=containers.deploy&format=json', {
            method: 'POST', body: body
        });
        var data = await resp.json();

        if (data.success) {
            showAlert('success', '<i class="fas fa-check-circle me-1"></i>Deployed! ' + data.message);
            setTimeout(function() { location.reload(); }, 1500);
        } else {
            showAlert('danger', data.message || 'Deploy failed.');
        }
    } catch (err) {
        showAlert('danger', 'Error: ' + err.message);
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-play me-1"></i>Deploy Container';
});

function dhAction(action, cid) {
    if (action === 'delete' && !confirm('Delete this container?')) return;
    
    var body = new URLSearchParams();
    body.append('<?php echo $token; ?>', '1');
    body.append('cid', cid);
    
    fetch('index.php?option=com_dockerhost&task=containers.' + action + '&format=json', {
        method: 'POST', body: body
    }).then(function() {
        location.reload();
    }).catch(function(err) {
        showAlert('danger', 'Action failed: ' + err.message);
    });
}
<?php endif; ?>
</script>
