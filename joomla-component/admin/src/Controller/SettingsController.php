<?php
namespace Joomla\Component\Dockerhost\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class SettingsController extends BaseController
{
    public function save(): void
    {
        $this->checkToken();
        $app   = Factory::getApplication();
        $input = $app->getInput();
        $data  = [
            'api_url'   => trim($input->getString('api_url',   'http://127.0.0.1:7001')),
            'api_token' => trim($input->getString('api_token', 'dockerhost-secret-token-2026')),
        ];
        $model = $this->getModel('Settings', 'Administrator');
        if ($model->saveSettings($data)) {
            $app->enqueueMessage('Settings saved successfully.', 'success');
        } else {
            $app->enqueueMessage('Error saving settings: ' . $model->getError(), 'error');
        }
        $this->setRedirect(Route::_('index.php?option=com_dockerhost&view=settings', false));
    }
}
