<?php
namespace Joomla\Component\Dockerhost\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Dockerhost\Administrator\Helper\ApiHelper;

class ImagesModel extends BaseDatabaseModel
{
    private ApiHelper $api;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->api = new ApiHelper();
    }

    public function getImages(): array
    {
        $result = $this->api->get('/images');
        if (isset($result['error'])) {
            return [];
        }
        if (isset($result['images']) && is_array($result['images'])) {
            return $result['images'];
        }
        if (is_array($result)) {
            return array_values($result);
        }
        return [];
    }

    public function pullImage(string $image): array
    {
        return $this->api->post('/images/pull', ['image' => $image]);
    }

    public function deleteImage(string $id): array
    {
        return $this->api->delete('/images/' . rawurlencode($id));
    }
}
