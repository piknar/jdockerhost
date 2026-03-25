<?php
namespace Joomla\Component\Dockerhost\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

class ContainersController extends BaseController
{
    protected function checkCsrf(): bool
    {
        if (!Session::checkToken()) {
            $this->jsonResponse(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            return false;
        }
        return true;
    }

    protected function jsonResponse(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        Factory::getApplication()->close();
    }

    public function start(): void
    {
        if (!$this->checkCsrf()) return;
        $id     = Factory::getApplication()->getInput()->getString('id', '');
        $model  = $this->getModel('Containers', 'Administrator');
        $result = $model->containerAction($id, 'start');
        if (isset($result['error'])) {
            $this->jsonResponse(['success' => false, 'message' => $result['error']]);
        } else {
            $this->jsonResponse(['success' => true, 'message' => 'Container started.']);
        }
    }

    public function stop(): void
    {
        if (!$this->checkCsrf()) return;
        $id     = Factory::getApplication()->getInput()->getString('id', '');
        $model  = $this->getModel('Containers', 'Administrator');
        $result = $model->containerAction($id, 'stop');
        if (isset($result['error'])) {
            $this->jsonResponse(['success' => false, 'message' => $result['error']]);
        } else {
            $this->jsonResponse(['success' => true, 'message' => 'Container stopped.']);
        }
    }

    public function restart(): void
    {
        if (!$this->checkCsrf()) return;
        $id     = Factory::getApplication()->getInput()->getString('id', '');
        $model  = $this->getModel('Containers', 'Administrator');
        $result = $model->containerAction($id, 'restart');
        if (isset($result['error'])) {
            $this->jsonResponse(['success' => false, 'message' => $result['error']]);
        } else {
            $this->jsonResponse(['success' => true, 'message' => 'Container restarted.']);
        }
    }

    public function remove(): void
    {
        if (!$this->checkCsrf()) return;
        $id     = Factory::getApplication()->getInput()->getString('id', '');
        $model  = $this->getModel('Containers', 'Administrator');
        $result = $model->deleteContainer($id, true);
        if (isset($result['error'])) {
            $this->jsonResponse(['success' => false, 'message' => $result['error']]);
        } else {
            $this->jsonResponse(['success' => true, 'message' => 'Container deleted.']);
        }
    }

    public function deploy(): void
    {
        if (!$this->checkCsrf()) return;

        $input  = Factory::getApplication()->getInput();
        $envRaw = $input->getString('env_json', '{}');
        $env    = json_decode($envRaw, true);
        if (!is_array($env)) {
            $env = [];
        }

        $data = [
            'name'           => trim($input->getString('name', '')),
            'image'          => trim($input->getString('image', '')),
            'port'           => trim($input->getString('port', '')),
            'env'            => $env,
            'restart_policy' => $input->getString('restart_policy', 'unless-stopped'),
            'user_id'        => (int) $input->getInt('user_id', 0),
            'username'       => trim($input->getString('username', '')),
        ];

        if (empty($data['name']) || empty($data['image'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Container name and image are required.']);
            return;
        }

        // Deploy container
        $model  = $this->getModel('Containers', 'Administrator');
        $result = $model->deploy($data);

        if (isset($result['error'])) {
            $this->jsonResponse(['success' => false, 'message' => $result['error'], 'data' => $result]);
            return;
        }

        // Extract host port from result
        $hostPort = $result['host_port'] ?? null;
        $message = 'Container deployed successfully!';
        if ($hostPort) {
            $message .= " Access at port {$hostPort}.";
        }

        $this->jsonResponse([
            'success'   => true,
            'message'   => $message,
            'host_port' => $hostPort,
            'data'      => $result,
        ]);
    }
}
