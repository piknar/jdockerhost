<?php
namespace Joomla\Component\Dockerhost\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class ImagesController extends BaseController
{
    private function jsonResponse(array $data): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $app->sendHeaders();
        echo json_encode($data);
        $app->close();
    }

    private function checkCsrf(): bool
    {
        if (!Session::checkToken() && !Session::checkToken('get')) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
            return false;
        }
        return true;
    }

    public function pull(): void
    {
        if (!$this->checkCsrf()) return;
        $image = trim(Factory::getApplication()->getInput()->getString('image', ''));
        if (empty($image)) {
            $this->jsonResponse(['success' => false, 'message' => 'Image name is required.']);
            return;
        }
        $result = $this->getModel('Images', 'Administrator')->pullImage($image);
        $this->jsonResponse([
            'success' => !isset($result['error']),
            'message' => $result['error'] ?? 'Pull initiated for: ' . $image,
            'data'    => $result,
        ]);
    }

    public function remove(): void
    {
        if (!$this->checkCsrf()) return;
        $id     = Factory::getApplication()->getInput()->getString('id', '');
        $result = $this->getModel('Images', 'Administrator')->deleteImage($id);
        $this->jsonResponse([
            'success' => !isset($result['error']),
            'message' => $result['error'] ?? 'Image deleted successfully.',
            'data'    => $result,
        ]);
    }
}
