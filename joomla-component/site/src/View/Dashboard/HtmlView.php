<?php
namespace Joomla\Component\Dockerhost\Site\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array  $containers = [];
    public array  $health     = [];
    public string $apiError   = '';
    public ?object $user      = null;
    public bool   $isGuest    = true;

    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();
        $doc  = $app->getDocument();
        $this->user    = $app->getIdentity();
        $this->isGuest = $this->user->guest;

        // Cassiopeia already loads Bootstrap 5 - just add FontAwesome
        $doc->addStyleSheet(
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css'
        );

        if (!$this->isGuest) {
            $model  = $this->getModel();
            $health = $model->getHealth();

            if (isset($health['error'])) {
                $this->apiError = $health['error'];
            } else {
                $this->health = $health;
            }

            // Only this user's containers
            $this->containers = $model->getContainers((int) $this->user->id);
        }

        parent::display($tpl);
    }
}
