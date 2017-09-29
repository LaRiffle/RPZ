<?php

namespace LFR\StoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('LFRStoreBundle:Default:index.html.twig');
    }
}
