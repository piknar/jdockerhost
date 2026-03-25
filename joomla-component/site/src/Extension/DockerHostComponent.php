<?php
namespace Joomla\Component\Dockerhost\Site\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

class DockerHostComponent extends MVCComponent
{
    public function setMVCFactory(MVCFactoryInterface $mvcFactory): void
    {
        parent::setMVCFactory($mvcFactory);
    }
}
