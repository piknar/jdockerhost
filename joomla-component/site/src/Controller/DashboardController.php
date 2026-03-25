<?php
namespace Joomla\Component\Dockerhost\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

class DashboardController extends BaseController
{
    public function deploy(): void
    {
        if (!Session::checkToken('post')) {
            echo new JsonResponse(null, 'Invalid security token', true);
            Factory::getApplication()->close();
            return;
        }

        $user = Factory::getApplication()->getIdentity();
        if ($user->guest) {
            echo new JsonResponse(null, 'Login required', true);
            Factory::getApplication()->close();
            return;
        }

        $input = Factory::getApplication()->getInput();

        $envRaw = $input->getString('env_json', '{}');
        $env    = json_decode($envRaw, true) ?: [];

        $data = [
            'name'           => trim($input->getString('name', '')),
            'image'          => trim($input->getString('image', '')),
            'port'           => trim($input->getString('port', '')),
            'env'            => $env,
            'restart_policy' => $input->getString('restart_policy', 'unless-stopped'),
            'user_id'        => (int) $user->id,
            'username'       => $user->username,
        ];

        if (empty($data['name']) || empty($data['image'])) {
            echo new JsonResponse(null, 'Container name and image are required', true);
            Factory::getApplication()->close();
            return;
        }

        /** @var \Joomla\Component\Dockerhost\Site\Model\DashboardModel $model */
        $model  = $this->getModel('Dashboard');
        $result = $model->deploy($data);

        if (isset($result['error'])) {
            echo new JsonResponse(null, $result['error'], true);
        } else {
            $msg = $result['message'] ?? 'Container deployed successfully';
            echo new JsonResponse([
                'ssl_port' => $result['ssl_port'] ?? null,
                'ssl_url'  => $result['ssl_url']  ?? null,
                'data'     => $result,
            ], $msg, false);
        }
        Factory::getApplication()->close();
    }

    public function start(): void
    {
        $this->containerAction('start', 'Container started');
    }

    public function stop(): void
    {
        $this->containerAction('stop', 'Container stopped');
    }

    public function restart(): void
    {
        $this->containerAction('restart', 'Container restarted');
    }

    public function remove(): void
    {
        $this->containerAction('delete', 'Container deleted');
    }

    private function containerAction(string $action, string $successMsg): void
    {
        if (!Session::checkToken('post')) {
            echo new JsonResponse(null, 'Invalid security token', true);
            Factory::getApplication()->close();
            return;
        }

        $user = Factory::getApplication()->getIdentity();
        if ($user->guest) {
            echo new JsonResponse(null, 'Login required', true);
            Factory::getApplication()->close();
            return;
        }

        $id     = Factory::getApplication()->getInput()->getString('id', '');
        $model  = $this->getModel('Dashboard');
        
        if ($action === 'delete') {
            $result = $model->deleteContainer($id);
        } else {
            $result = $model->containerAction($id, $action);
        }

        if (isset($result['error'])) {
            echo new JsonResponse(null, $result['error'], true);
        } else {
            echo new JsonResponse([], $successMsg, false);
        }
        Factory::getApplication()->close();
    }
}
