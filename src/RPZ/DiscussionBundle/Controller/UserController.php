<?php

namespace RPZ\DiscussionBundle\Controller;

use RPZ\UserBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class UserController extends Controller
{
    public $entityNameSpace = 'RPZUserBundle:User';
    public function indexAction() {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $users = $repository->findAll();

        return $this->render('RPZDiscussionBundle:User:index.html.twig', array(
                'users' => $users
        ));
    }

    public function editAction(Request $request, $id = 0) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $username = $this->getUser()->getUsername();
        $repository = $this->getDoctrine()
          ->getManager()
          ->getRepository($this->entityNameSpace)
        ;
        $users = $repository->findBy(array('username' => $username));
        if($users != null){
          $user = $users[0];
        } else {
          $user = new User();
        }
        $user->setUsername($username);
        $user->setSalt($username);
        $user->setPassword('');

        $form = $this->get('form.factory')->createBuilder(FormType::class, $user)
        ->add('firstname', TextType::class)
        ->add('surname', TextType::class)
        ->add('save',	SubmitType::class)
        ->getForm();

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();
            $request->getSession()->getFlashBag()->add('success', 'Profil mis à jour.');
            return $this->redirect($this->generateUrl('rpz_discussion_article'));
        }
        return $this->render('RPZDiscussionBundle:User:edit.html.twig', array(
            'form' => $form->createView(),
            'username' => $username
        ));

    }
    public function removeAction(Request $request, $id = 0){
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $em->remove($entity);
        $em->flush();
        $request->getSession()->getFlashBag()->add('danger', 'Profil supprimé.');
        return $this->redirect($this->generateUrl('rpz_discussion_article'));
    }
}
