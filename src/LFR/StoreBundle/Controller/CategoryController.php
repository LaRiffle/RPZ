<?php

namespace LFR\StoreBundle\Controller;

use LFR\StoreBundle\Entity\Category;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CategoryType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class CategoryController extends Controller
{
    public $entityNameSpace = 'LFRStoreBundle:Category';

    public function showAction($id)
    {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $category = $repository->find($id);
        return $this->render($this->entityNameSpace.':show.html.twig', array(
          'category' => $category,
        ));
    }

    public function addAction(Request $request, $id = 0) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        if($id == 0) {
            $category = new Category();
        } else {
            $repository = $em->getRepository($this->entityNameSpace);
            $category = $repository->find($id);
        }
        $form = $this->get('form.factory')->createBuilder(FormType::class, $category)
        ->add('name', TextType::class)
        ->add('slug', TextType::class)
        ->add('save',	SubmitType::class)
        ->getForm();

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($category);
            $em->flush();
            return $this->redirect($this->generateUrl('lfr_store_explore'));
        }
        return $this->render($this->entityNameSpace.':add.html.twig', array(
            'form' => $form->createView(),
            'categoryId' => $id
        ));
    }
    public function removeAction(Request $request, $id){
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $category = $em->getRepository($this->entityNameSpace)->find($id);
        $em->remove($category);
        $em->flush();
        return $this->redirect($this->generateUrl('lfr_store_explore'));
    }
  }
