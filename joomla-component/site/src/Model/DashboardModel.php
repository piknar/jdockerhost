<?php
namespace Joomla\Component\Dockerhost\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Dockerhost\Site\Helper\ApiHelper;

class DashboardModel extends BaseDatabaseModel
{
    private ?ApiHelper $api = null;

    private function getApi(): ApiHelper
    {
        if ($this->api === null) {
            $this->api = new ApiHelper();
        }
        return $this->api;
    }

    public function getHealth(): array
    {
        return $this->getApi()->get('/health');
    }

    public function getContainers(?int $userId = null): array
    {
        $result = $this->getApi()->get('/containers');
        if (isset($result['error'])) {
            return [];
        }
        
        $containers = [];
        if (is_array($result)) {
            $containers = isset($result['containers']) ? $result['containers'] : array_values($result);
        }
        
        // Filter by user_id if specified
        if ($userId !== null) {
            $containers = array_filter($containers, function($c) use ($userId) {
                $labels = $c['labels'] ?? [];
                return (int)($labels['user_id'] ?? 0) === $userId;
            });
            $containers = array_values($containers);
        }
        
        return $containers;
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
            $envList = [];
            foreach ($data['env'] as $k => $v) {
                if (is_string($k) && $k !== '') {
                    $envList[] = $k . '=' . $v;
                }
            }
            $payload['env'] = $envList;
        }
        if (!empty($data['user_id'])) {
            $payload['user_id'] = (int) $data['user_id'];
        }
        if (!empty($data['username'])) {
            $payload['username'] = $data['username'];
        }
        
        return $this->getApi()->post('/containers/deploy', $payload);
    }

    public function containerAction(string $id, string $action): array
    {
        if (!in_array($action, ['start', 'stop', 'restart'], true)) {
            return ['error' => 'Invalid action'];
        }
        return $this->getApi()->post('/containers/' . rawurlencode($id) . '/' . $action);
    }

    public function deleteContainer(string $id): array
    {
        return $this->getApi()->delete('/containers/' . rawurlencode($id) . '?force=true');
    }
}
