<?php
namespace Joomla\Component\Dockerhost\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Dockerhost\Administrator\Helper\ApiHelper;

class ContainersModel extends BaseDatabaseModel
{
    private ApiHelper $api;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->api = new ApiHelper();
    }

    public function getHealth(): array
    {
        return $this->api->get('/health');
    }

    public function getContainers(?int $userId = null): array
    {
        $result = $this->api->get('/containers');
        if (isset($result['error'])) {
            return [];
        }
        
        $containers = [];
        if (isset($result['containers']) && is_array($result['containers'])) {
            $containers = $result['containers'];
        } elseif (is_array($result)) {
            $containers = array_values($result);
        }
        
        // Filter by user_id if specified (for multi-user)
        if ($userId !== null) {
            $containers = array_filter($containers, function($c) use ($userId) {
                $labels = $c['labels'] ?? $c['Labels'] ?? [];
                $containerUserId = (int)($labels['user_id'] ?? 0);
                return $containerUserId === $userId;
            });
            $containers = array_values($containers);
        }
        
        return $containers;
    }

    public function getContainer(string $id): array
    {
        return $this->api->get('/containers/' . rawurlencode($id));
    }

    public function containerAction(string $id, string $action): array
    {
        if (!in_array($action, ['start', 'stop', 'restart'], true)) {
            return ['error' => 'Invalid action: ' . $action];
        }
        return $this->api->post('/containers/' . rawurlencode($id) . '/' . $action);
    }

    public function deleteContainer(string $id, bool $force = false): array
    {
        $qs = $force ? '?force=true' : '?force=false';
        return $this->api->delete('/containers/' . rawurlencode($id) . $qs);
    }

    public function getLogs(string $id, int $tail = 200): string
    {
        $endpoint = '/containers/' . rawurlencode($id) . '/logs?tail=' . $tail;
        $result   = $this->api->get($endpoint);
        if (isset($result['logs'])) {
            return (string) $result['logs'];
        }
        if (isset($result['error'])) {
            return '[API Error] ' . $result['error'];
        }
        return $this->api->getText($endpoint);
    }

    public function getStats(string $id): array
    {
        return $this->api->get('/containers/' . rawurlencode($id) . '/stats');
    }

    public function deploy(array $data): array
    {
        $payload = [
            'name'           => $data['name'],
            'image'          => $data['image'],
            'restart_policy' => $data['restart_policy'] ?? 'unless-stopped',
        ];
        if (!empty($data['port'])) {
            $payload['port'] = (int) $data['port'];
        }
        if (!empty($data['env']) && is_array($data['env'])) {
            // Convert to KEY=value format
            $envList = [];
            foreach ($data['env'] as $k => $v) {
                if (is_string($k) && $k !== '') {
                    $envList[] = $k . '=' . $v;
                } elseif (is_string($v) && str_contains($v, '=')) {
                    $envList[] = $v;
                }
            }
            $payload['env'] = $envList;
        }
        // Add user info
        if (!empty($data['user_id'])) {
            $payload['user_id'] = (int) $data['user_id'];
        }
        if (!empty($data['username'])) {
            $payload['username'] = $data['username'];
        }
        
        return $this->api->post('/containers/deploy', $payload);
    }
}
