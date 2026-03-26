<?php defined('_JEXEC') or die; ?>
<div class="container py-4">
    <h2>My Containers</h2>
    <?php if (empty($this->containers)): ?>
        <div class="alert alert-info">No containers yet.</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($this->containers as $c): 
                $sslPort = $c['labels']['ssl_port'] ?? '';
                $sslUrl = $sslPort ? 'https://jtest.ymmude.com:' . (int)$sslPort : '';
            ?>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5><?php echo htmlspecialchars($c['name']); ?></h5>
                        <p>Status: <?php echo htmlspecialchars($c['status']); ?></p>
                        <?php if ($sslUrl): ?>
                        <a href="<?php echo htmlspecialchars($sslUrl); ?>" target="_blank" class="btn btn-success btn-sm">
                            <i class="fas fa-lock"></i> Open (SSL)
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
