<?php

namespace LFR\StoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class InformationController extends Controller
{
    public $entityNameSpace = 'LFRStoreBundle:Information';
    public function historyAction()
    {
        return $this->render($this->entityNameSpace.':history.html.twig');
    }
}
