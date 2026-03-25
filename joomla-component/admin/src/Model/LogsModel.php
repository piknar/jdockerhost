<?php
namespace Joomla\Component\Dockerhost\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Dockerhost\Administrator\Helper\ApiHelper;

class LogsModel extends BaseDatabaseModel
{
    private ApiHelper $api;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->api = new ApiHelper();
    }

    public function getContainer(string $id): array
    {
        return $this->api->get('/containers/' . rawurlencode($id));
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
        // Fallback: raw text response
        return $this->api->getText($endpoint);
    }
}
