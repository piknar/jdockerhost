<?php defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$app   = Factory::getApplication();
$user  = $app->getIdentity();
$containers = $this->containers ?? [];
?>
<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-docker me-2"></i>My Containers</h2>
    
    <?php if (!$user || $user->guest): ?>
        <div class="alert alert-info">
            <h4>Login Required</h4>
            <p>Please log in to view your containers.</p>
            <a href="<?php echo $app->get('sef') ? 'login' : 'index.php?option=com_users&view=login'; ?>" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-1"></i>Log In
            </a>
        </div>
    <?php else: ?>
        
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Manage containers:</strong> Use the <a href="/administrator/index.php?option=com_dockerhost" class="alert-link">Administrator Dashboard</a> to deploy, start, stop, or delete containers.
        </div>

        <?php if (empty($containers)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-1"></i>No containers yet. Deploy your first container from the administrator!
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($containers as $c): 
                    $cname = ltrim((string)($c['name'] ?? ''), '/');
                    $image = (string)($c['image'] ?? '');
                    $state = (string)($c['status'] ?? 'unknown');
                    $isUp  = str_contains(strtolower($state), 'running') || str_contains(strtolower($state), 'up');
                    $labels = (array)($c['labels'] ?? []);
                    $sslPort = $labels['ssl_port'] ?? '';
                    $sslUrl = $sslPort ? 'https://jtest.ymmude.com:' . (int)$sslPort : '';
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 <?php echo $isUp ? 'border-success' : 'border-secondary'; ?>">
                        <div class="card-header <?php echo $isUp ? 'bg-success text-white' : 'bg-secondary text-white'; ?>">
                            <i class="fas fa-<?php echo $isUp ? 'check-circle' : 'circle'; ?> me-1"></i><?php echo htmlspecialchars($cname); ?>
                        </div>
                        <div class="card-body">
                            <p class="mb-1"><small class="text-muted">Image:</small><br><code class="small text-break"><?php echo htmlspecialchars($image); ?></code></p>
                            <p class="mb-1"><small class="text-muted">Status:</small><br><?php echo $isUp ? '<span class="badge bg-success">Running</span>' : '<span class="badge bg-secondary">' . htmlspecialchars($state) . '</span>'; ?></p>
                            <?php if ($sslUrl): ?>
                            <p class="mb-0 mt-3">
                                <a href="<?php echo htmlspecialchars($sslUrl); ?>" target="_blank" class="btn btn-success btn-sm w-100">
                                    <i class="fas fa-lock me-1"></i>Open (SSL)
                                </a>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
