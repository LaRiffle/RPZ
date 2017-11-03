<?php

namespace LFR\StoreBundle\Controller;

use LFR\StoreBundle\Entity\Attribute;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class AttributeController extends Controller
{
    public $entityNameSpace = 'LFRStoreBundle:Attribute';
    public function indexAction()
    {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $attributes = $repository->findAll();
        $sizes = [];
        $colors = [];
        $others = [];
        foreach ($attributes as $attribute) {
          if($attribute->getCategory() == 'size'){
            $sizes[] = $attribute;
          } elseif($attribute->getCategory() == 'color'){
            $colors[] = $attribute;
          } else {
            $others[] = $attribute;
          }
        }
        return $this->render($this->entityNameSpace.':index.html.twig', array(
          'sizes' => $sizes,
          'colors' => $colors,
          'others' => $others,
        ));
    }
    public function addAction(Request $request, $id = 0, $category) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        if($id == 0) {
            $attribute = new Attribute();
            $attribute->setCategory($category);
        } else {
            $repository = $em->getRepository('LFRStoreBundle:Attribute');
            $attribute = $repository->find($id);
        }
        $form = $this->get('form.factory')->createBuilder(FormType::class, $attribute)
        ->add('name', TextType::class)
        ->add('value', TextType::class)
        ->add('save',	SubmitType::class)
        ->getForm();

        $form->handleRequest($request);
        if($form->isValid()) {
            $em->persist($attribute);
            $em->flush();
            return $this->redirect($this->generateUrl('lfr_admin_creation_attributes'));
        }
        return $this->render('LFRStoreBundle:Attribute:add.html.twig', array(
            'form' => $form->createView(),
            'id' => $id,
            'category' => $category
        ));

    }
    public function removeAction(Request $request, $id){
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $type = $em->getRepository('LFRStoreBundle:Attribute')->find($id);
        $em->remove($type);
        $em->flush();
        return $this->redirect($this->generateUrl('lfr_admin_creation_attributes'));
    }
}
