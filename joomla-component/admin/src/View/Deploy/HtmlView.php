<?php
namespace Joomla\Component\Dockerhost\Administrator\View\Deploy;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('DockerHost &mdash; Deploy Container', 'plus-circle');
        ToolbarHelper::link('index.php?option=com_dockerhost&view=containers', 'Back to Containers', 'list');
    }
}
