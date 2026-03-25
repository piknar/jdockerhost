<?php
namespace Joomla\Component\Dockerhost\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;

class SettingsModel extends BaseDatabaseModel
{
    public function getSettings(): array
    {
        $params = ComponentHelper::getParams('com_dockerhost');
        return [
            'api_url'   => $params->get('api_url',   'http://127.0.0.1:7001'),
            'api_token' => $params->get('api_token', 'dockerhost-secret-token-2026'),
        ];
    }

    public function saveSettings(array $data): bool
    {
        $db     = $this->getDatabase();
        $params = new Registry();
        $params->set('api_url',   $data['api_url']   ?? 'http://127.0.0.1:7001');
        $params->set('api_token', $data['api_token'] ?? 'dockerhost-secret-token-2026');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString()))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_dockerhost'))
            ->where($db->quoteName('type')    . ' = ' . $db->quote('component'));

        $db->setQuery($query);
        try {
            $db->execute();
            return true;
        } catch (\RuntimeException $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }
}
