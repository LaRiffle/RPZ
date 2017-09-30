<?php

namespace LFR\StoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class HomeController extends Controller
{
    public function animationAction()
    {
        return $this->render('LFRStoreBundle:Home:animation.html.twig');
    }
    public function homepageAction()
    {
        return $this->render('LFRStoreBundle:Home:home_page.html.twig');
    }
    public function homeAction()
    {
        return $this->render('LFRStoreBundle:Home:home.html.twig');
    }
}
