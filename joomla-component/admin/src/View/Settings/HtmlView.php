<?php
namespace Joomla\Component\Dockerhost\Administrator\View\Settings;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public array $settings = [];

    public function display($tpl = null): void
    {
        $this->settings = $this->getModel()->getSettings();
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('DockerHost &mdash; Settings', 'cog');
        ToolbarHelper::apply('settings.save', 'JTOOLBAR_APPLY');
    }
}
