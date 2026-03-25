<?php
namespace Joomla\Component\Dockerhost\Administrator\View\Logs;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public string $logs          = '';
    public string $containerId   = '';
    public string $containerName = '';

    public function display($tpl = null): void
    {
        $this->containerId = Factory::getApplication()->getInput()->getString('id', '');
        if ($this->containerId) {
            $model               = $this->getModel();
            $container           = $model->getContainer($this->containerId);
            $this->containerName = ltrim((string) ($container['name'] ?? $this->containerId), '/');
            $this->logs          = $model->getLogs($this->containerId, 200);
        }
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $title = $this->containerName
            ? 'Logs: ' . htmlspecialchars($this->containerName, ENT_QUOTES)
            : 'Container Logs';
        ToolbarHelper::title('DockerHost &mdash; ' . $title, 'file-text');
        ToolbarHelper::link('index.php?option=com_dockerhost&view=containers', 'Back to Containers', 'list');
    }
}
