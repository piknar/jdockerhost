<?php
namespace Joomla\Component\Dockerhost\Administrator\View\Containers;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public array  $containers = [];
    public array  $health     = [];
    public string $apiError   = '';

    public function display($tpl = null): void
    {
        $model  = $this->getModel();
        $health = $model->getHealth();
        if (isset($health['error'])) {
            $this->apiError = $health['error'];
        } else {
            $this->health = $health;
        }
        $this->containers = $model->getContainers();
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('DockerHost &mdash; Containers', 'cube');
        ToolbarHelper::link('index.php?option=com_dockerhost&view=deploy',   'Deploy New', 'plus');
        ToolbarHelper::link('index.php?option=com_dockerhost&view=images',   'Images',     'image');
        ToolbarHelper::link('index.php?option=com_dockerhost&view=settings', 'Settings',   'cog');
        ToolbarHelper::link('index.php?option=com_dockerhost&view=containers', 'Refresh',  'refresh');
    }
}
