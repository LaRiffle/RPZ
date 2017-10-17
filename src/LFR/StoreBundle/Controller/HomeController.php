<?php

namespace LFR\StoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class HomeController extends Controller
{
    public function fetchText($role){
      $textRepository = $this->getDoctrine()->getManager()->getRepository('LFRStoreBundle:Text');
      return $textRepository->findBy(array('role' => $role))[0];
    }
    public function fetchImage($role){
      $textRepository = $this->getDoctrine()->getManager()->getRepository('LFRStoreBundle:Image');
      $image = $textRepository->findBy(array('role' => $role))[0];
      $filename = $image->getImage();
      $imagehandler = $this->container->get('lfr_store.imagehandler');
      $path_small_image = $imagehandler->get_image_in_quality($filename, 'md');
      $image->small_image = $path_small_image;
      return $image;
    }
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
        $text = [];
        $text['home']['title']['left'] = $this->fetchText('home:title:left');
        $text['home']['left_img'] = $this->fetchImage('home:left_img');
        $text['home']['title']['right'] = $this->fetchText('home:title:right');
        $text['home']['right_img'] = $this->fetchImage('home:right_img');
        return $this->render('LFRStoreBundle:Home:home.html.twig', array(
          'data' => $text
        ));
    }
}
