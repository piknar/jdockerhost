<?php
namespace Joomla\Component\Dockerhost\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class ContainersController extends BaseController
{
    public function deploy()
    {
        $this->checkToken();
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        
        if (!$user || $user->guest) {
            $app->jsonResponse(['success' => false, 'message' => 'Login required']);
            return;
        }
        
        $name = $app->input->getString('name');
        $image = $app->input->getString('image');
        $port = $app->input->getString('port', '');
        $restart = $app->input->getString('restart_policy', 'unless-stopped');
        $envJson = $app->input->getString('env_json', '{}');
        
        // Parse env_json
        $env = [];
        if ($envJson) {
            $decoded = json_decode($envJson, true);
            if (is_array($decoded)) {
                $env = $decoded;
            }
        }
        
        // Prepare data array for model
        $data = [
            'name' => $name,
            'image' => $image,
            'restart_policy' => $restart,
            'port' => $port,
            'env' => $env,
            'user_id' => $user->id,
            'username' => $user->username
        ];
        
        // Call the model to deploy
        /** @var \Joomla\Component\Dockerhost\Site\Model\DashboardModel $model */
        $model = $this->getModel('Dashboard');
        $result = $model->deploy($data);
        
        if (isset($result['error'])) {
            $app->jsonResponse(['success' => false, 'message' => $result['error']]);
        } else {
            $app->jsonResponse(['success' => true, 'message' => 'Container deployed', 'data' => $result]);
        }
    }
    
    public function start()
    {
        $this->doAction('start');
    }
    
    public function stop()
    {
        $this->doAction('stop');
    }
    
    public function delete()
    {
        $this->checkToken();
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        
        if (!$user || $user->guest) {
            $app->jsonResponse(['success' => false, 'message' => 'Login required']);
            return;
        }
        
        $cid = $app->input->getString('cid');
        
        /** @var \Joomla\Component\Dockerhost\Site\Model\DashboardModel $model */
        $model = $this->getModel('Dashboard');
        $result = $model->deleteContainer($cid);
        
        if (isset($result['error'])) {
            $app->jsonResponse(['success' => false, 'message' => $result['error']]);
        } else {
            $app->jsonResponse(['success' => true, 'message' => 'Container deleted']);
        }
    }
    
    private function doAction($action)
    {
        $this->checkToken();
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        
        if (!$user || $user->guest) {
            $app->jsonResponse(['success' => false, 'message' => 'Login required']);
            return;
        }
        
        $cid = $app->input->getString('cid');
        
        /** @var \Joomla\Component\Dockerhost\Site\Model\DashboardModel $model */
        $model = $this->getModel('Dashboard');
        $result = $model->containerAction($cid, $action);
        
        if (isset($result['error'])) {
            $app->jsonResponse(['success' => false, 'message' => $result['error']]);
        } else {
            $app->jsonResponse(['success' => true, 'message' => 'Action completed']);
        }
    }
    
    protected function checkToken()
    {
        if (!Session::checkToken('request')) {
            Factory::getApplication()->jsonResponse(['success' => false, 'message' => 'Invalid token']);
            exit;
        }
    }
}
