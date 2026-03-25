<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
$token = \Joomla\CMS\Session\Session::getFormToken();
$cid   = htmlspecialchars($this->containerId,   ENT_QUOTES, 'UTF-8');
$cname = htmlspecialchars($this->containerName, ENT_QUOTES, 'UTF-8');
?>
<?php if (!$this->containerId): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>No container selected.
  <a href="<?php echo Route::_('index.php?option=com_dockerhost&view=containers'); ?>" class="alert-link ms-2">
    &larr; Back to Containers
  </a>
</div>
<?php else: ?>
<div class="card shadow-sm">
  <div class="card-header d-flex align-items-center bg-dark text-white py-2 px-3 flex-wrap gap-2">
    <div>
      <i class="fas fa-file-alt me-2"></i>
      <strong><?php echo $cname; ?></strong>
      <span class="badge bg-secondary font-monospace ms-2" style="font-size:.7rem"><?php echo substr($cid, 0, 12); ?></span>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
      <select id="tail-select" class="form-select form-select-sm" style="width:auto">
        <option value="50">50 lines</option>
        <option value="100">100 lines</option>
        <option value="200" selected>200 lines</option>
        <option value="500">500 lines</option>
        <option value="1000">1000 lines</option>
      </select>
      <button class="btn btn-outline-light btn-sm" id="refresh-btn" onclick="doRefresh()">
        <i class="fas fa-sync-alt me-1"></i>Refresh
      </button>
      <button class="btn btn-outline-light btn-sm" onclick="scrollToBottom()" title="Scroll to bottom">
        <i class="fas fa-arrow-down"></i>
      </button>
      <button class="btn btn-sm" id="auto-btn" onclick="toggleAutoRefresh()"
              style="min-width:90px;background:transparent;border:1px solid #555;color:#adb5bd">
        Auto: Off
      </button>
      <a href="<?php echo Route::_('index.php?option=com_dockerhost&view=containers'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-list me-1"></i>Containers
      </a>
    </div>
  </div>

  <div class="card-body p-0 bg-dark">
    <pre id="log-output"
         class="m-0 p-3 text-light"
         style="min-height:450px;max-height:680px;overflow-y:auto;font-size:.78rem;line-height:1.5;
                white-space:pre-wrap;word-break:break-all;font-family:'Courier New',Consolas,'Liberation Mono',monospace;"
    ><?php echo htmlspecialchars($this->logs, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
  </div>

  <div class="card-footer bg-dark border-top border-secondary d-flex align-items-center gap-3 py-2 px-3">
    <small class="text-muted" id="log-timestamp">Loaded: <?php echo date('H:i:s'); ?></small>
    <small class="text-muted" id="log-linecount"></small>
    <div class="ms-auto">
      <button class="btn btn-sm btn-outline-secondary" onclick="copyLogs()" title="Copy to clipboard">
        <i class="fas fa-copy me-1"></i>Copy
      </button>
    </div>
  </div>
</div>

<script>
(function () {
    'use strict';
    var CSRF  = '<?php echo $token; ?>';
    var CID   = '<?php echo $cid; ?>';
    var timer = null;

    function countLines(text) {
        return text ? text.split('\n').length : 0;
    }

    function updateLineCount() {
        var out = document.getElementById('log-output');
        var n   = countLines(out.textContent);
        document.getElementById('log-linecount').textContent = n + ' lines';
    }

    function scrollToBottom() {
        var el = document.getElementById('log-output');
        el.scrollTop = el.scrollHeight;
    }
    window.scrollToBottom = scrollToBottom;

    window.doRefresh = async function () {
        var tail = document.getElementById('tail-select').value;
        var btn  = document.getElementById('refresh-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        try {
            var body = new URLSearchParams();
            body.append('id',   CID);
            body.append('tail', tail);
            body.append(CSRF,   '1');
            var resp = await fetch('index.php?option=com_dockerhost&task=containers.logs', {
                method: 'POST', body: body
            });
            var data = await resp.json();
            if (data.success) {
                var out = document.getElementById('log-output');
                out.textContent = data.logs || '(no output)';
                document.getElementById('log-timestamp').textContent =
                    'Updated: ' + new Date().toLocaleTimeString();
                updateLineCount();
                scrollToBottom();
            }
        } catch (e) {
            console.error('Log refresh error:', e);
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Refresh';
    };

    window.toggleAutoRefresh = function () {
        var btn = document.getElementById('auto-btn');
        if (timer) {
            clearInterval(timer);
            timer = null;
            btn.textContent = 'Auto: Off';
            btn.style.color = '#adb5bd';
            btn.style.borderColor = '#555';
        } else {
            timer = setInterval(window.doRefresh, 5000);
            btn.textContent = 'Auto: 5s';
            btn.style.color = '#28a745';
            btn.style.borderColor = '#28a745';
        }
    };

    window.copyLogs = function () {
        var text = document.getElementById('log-output').textContent;
        navigator.clipboard.writeText(text).then(function () {
            var btn = event.currentTarget;
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
            setTimeout(function () { btn.innerHTML = orig; }, 2000);
        }).catch(function () {
            alert('Copy failed. Please select and copy manually.');
        });
    };

    // Init
    updateLineCount();
    scrollToBottom();
}());
</script>
<?php endif; ?>
