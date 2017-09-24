<?php

namespace RPZ\DiscussionBundle\Controller;

use RPZ\DiscussionBundle\Entity\Log;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use \Datetime;

class LogController extends Controller
{
    public $entityNameSpace = 'RPZDiscussionBundle:Log';
    public function getAuthorName($username) {
      $em = $this->getDoctrine()->getManager();
      $repository = $em->getRepository('RPZUserBundle:User');
      $users = $repository->findBy(array('username' => $username));
      if($users != null){
        $user = $users[0];
        $authorName = $user->getFirstname();
      } else {
        $authorName = $username;
      }
      return $authorName;
    }
    public function time_since($since) {
        $chunks = array(
            array(60 * 60 * 24 * 365 , 'an'),
            array(60 * 60 * 24 * 30 , 'mois'),
            array(60 * 60 * 24 * 7, 'semaine'),
            array(60 * 60 * 24 , 'jour'),
            array(60 * 60 , 'heure'),
            array(60 , 'minute'),
            array(1 , 'seconde')
        );

        for ($i = 0, $j = count($chunks); $i < $j; $i++) {
            $seconds = $chunks[$i][0];
            $name = $chunks[$i][1];
            if (($count = floor($since / $seconds)) != 0) {
                break;
            }
        }

        $print = ($count <= 1) ? $count.' '.$name : "$count {$name}s";
        return $print;
    }
    public function indexAction() {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $logs = $repository->findBy(array(), array('date'=>'desc'));

        return $this->render($this->entityNameSpace.':index.html.twig', array(
            'logs' => $logs
        ));
    }
    public function showAction() {
        /* Show who was recently active */
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $lastActivity = $repository->lastActivity();
        $date_now = new DateTime();
        $username = $this->getUser()->getUsername();
        $username_index = -1;
        foreach ($lastActivity as $key => $activity) {
          if($activity['username'] == $username){
            $username_index = $key;
          }
          $date_log = new DateTime($activity['date']);
          $diff = $date_now->getTimestamp() - $date_log->getTimestamp();
          $lastActivity[$key]['when'] = $this->time_since($diff);
          $lastActivity[$key]['username'] = $this->getAuthorName($lastActivity[$key]['username']);
        }
        if($username_index >= 0){
          unset($lastActivity[$username_index]);
        }

        return $this->render($this->entityNameSpace.':show.html.twig', array(
                'lastActivity' => $lastActivity,
        ));
    }
}
