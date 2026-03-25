<?php
namespace Joomla\Component\Dockerhost\Administrator\View\Images;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public array $images = [];

    public function display($tpl = null): void
    {
        $this->images = $this->getModel()->getImages();
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('DockerHost &mdash; Images', 'image');
        ToolbarHelper::link('index.php?option=com_dockerhost&view=containers', 'Back to Containers', 'list');
    }
}
