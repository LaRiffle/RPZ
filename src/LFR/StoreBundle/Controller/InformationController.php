<?php

namespace LFR\StoreBundle\Controller;

use LFR\StoreBundle\Entity\Text;
use LFR\StoreBundle\Entity\Image;
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

$textRepository = null;
class InformationController extends Controller
{
    public function fetchText($role){
      $textRepository = $this->getDoctrine()->getManager()->getRepository('LFRStoreBundle:Text');
      return $textRepository->findBy(array('role' => $role))[0];
    }
    public function fetchImage($role){
      $textRepository = $this->getDoctrine()->getManager()->getRepository('LFRStoreBundle:Image');
      return $textRepository->findBy(array('role' => $role))[0];
    }

    public $entityNameSpace = 'LFRStoreBundle:Information';
    public function historyAction()
    {
      if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
        return $this->redirect($this->generateUrl('login'));
      }
      $text = [];
      $text['history']['title'] = $this->fetchText('history:title');
      $text['history']['content'] = $this->fetchText('history:text');
      $text['history']['under'] = $this->fetchImage('history:under');
      $text['history']['over'] = $this->fetchImage('history:over');
      $text['author']['title'] = $this->fetchText('author:title');
      $text['author']['content'] = $this->fetchText('author:text');
      $text['author']['img'] = $this->fetchImage('author:img');
      return $this->render($this->entityNameSpace.':history.html.twig', array(
        'data' => $text
      ));
    }
    public function textAddAction(Request $request, $id = 0) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        if($id == 0) {
            $text = new Text();
        } else {
            $repository = $em->getRepository('LFRStoreBundle:Text');
            $text = $repository->find($id);
            $text_role = $text->getRole();
        }
        $form = $this->get('form.factory')->createBuilder(FormType::class, $text)
        ->add('role', TextType::class)
        ->add('text', TextareaType::class)
        ->add('save',	SubmitType::class)
        ->getForm();

        $form->handleRequest($request);
        if($form->isValid()) {
            $em->persist($text);
            $em->flush();
            return $this->redirect($this->generateUrl('lfr_store_homepage'));
        } elseif ($request->getMethod() == 'POST') {
            $data = $request->get('form');
            $text = $repository->find($id);
            $text->setText($data['text']);
            $text->setRole($text_role);
            $em->persist($text);
            $em->flush();
            return $this->redirect($request->headers->get('referer'));
        }
        return $this->render('LFRStoreBundle:Text:add.html.twig', array(
            'form' => $form->createView(),
            'id' => $id
        ));
    }
    public function imageAddAction(Request $request, $id = 0) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository('LFRStoreBundle:Image');
        $oldFileName = null;
        if($id == 0) {
            $image = new Image();
        } else {
            $image = $repository->find($id);
            $image_role = $image->getRole();
            if($image->getImage() != ''){
              $oldFileName = $image->getImage();
              $image->setImage(
                  new File($this->getParameter('img_dir').'/'.$image->getImage())
              );
            }
        }
        if($oldFileName != null) {
          $article_img_url = $oldFileName;
        } else {
          $article_img_url = '';
        }
        $form = $this->get('form.factory')->createBuilder(FormType::class, $image)
        ->add('role', TextType::class)
        ->add('image', FileType::class, array('label' => 'Image', 'required' => False))
        ->add('save',	SubmitType::class)
        ->getForm();

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $file = $image->getImage();
            if($file != null) {
              $fileName = md5(uniqid()).'.'.$file->guessExtension();
              $file->move(
                  $this->getParameter('img_dir'),
                  $fileName
              );
              // Update the 'image' property to store the file name instead of its contents
              $image->setImage($fileName);
            } elseif($oldFileName != null) {
              $image->setImage($oldFileName);
            } else {
              $image->setImage('');
            }
            $em->persist($image);
            $em->flush();
            return $this->redirect($this->generateUrl('lfr_store_homepage'));
        } elseif ($request->getMethod() == 'POST') {
            $data = $request->get('form');
            $image = $repository->find($id);
            $image->setRole($image_role);
            $file = $image->getImage();
            if($file != null) {
              $fileName = md5(uniqid()).'.'.$file->guessExtension();
              $file->move(
                  $this->getParameter('img_dir'),
                  $fileName
              );
              // Update the 'image' property to store the file name instead of its contents
              $image->setImage($fileName);
            } elseif($oldFileName != null) {
              $image->setImage($oldFileName);
            } else {
              $image->setImage('');
            }
            $em->persist($image);
            $em->flush();
            return $this->redirect($request->headers->get('referer'));
        }
        return $this->render('LFRStoreBundle:Image:add.html.twig', array(
            'form' => $form->createView(),
            'id' => $id,
            'img' => $article_img_url,
        ));
    }
}
