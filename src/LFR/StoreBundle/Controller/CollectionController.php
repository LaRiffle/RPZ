<?php

namespace LFR\StoreBundle\Controller;

use LFR\StoreBundle\Entity\Collection;

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
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class  CollectionController extends Controller
{
    public $entityNameSpace = 'LFRStoreBundle:Collection';
    /* Index is in Information Controller */

    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $collection = $repository->find($id);
        return $this->render('LFRStoreBundle:Collection:show.html.twig', array(
          'collection' => $collection,
        ));
    }
    public function addAction(Request $request, $id = 0) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $oldFileName = null;
        if($id == 0) {
            $collection = new Collection();
        } else {
            $repository = $em->getRepository($this->entityNameSpace);
            $collection = $repository->find($id);
            if($collection->getImage() != ''){
              $oldFileName = $collection->getImage();
              $collection->setImage(
                  new File($this->getParameter('img_dir').'/'.$collection->getImage())
              );
            }
        }
        if($oldFileName != null) {
          $collection_img_url = $oldFileName;
        } else {
          $collection_img_url = '';
        }
        $form = $this->get('form.factory')->createBuilder(FormType::class, $collection)
        ->add('title', TextType::class)
        ->add('description', TextareaType::class)
        ->add('image', FileType::class, array('label' => 'Image', 'required' => False))
        ->add('save',	SubmitType::class)
        ->getForm();

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            // $file stores the uploaded PDF file
            /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */
            $file = $collection->getImage();
            if($file != null) {
              // Generate a unique name for the file before saving it
              $fileName = md5(uniqid()).'.'.$file->guessExtension();

              // Move the file to the directory where images are stored
              $file->move(
                  $this->getParameter('img_dir'),
                  $fileName
              );

              // Update the 'image' property to store the file name
              // instead of its contents
              $collection->setImage($fileName);
            } elseif($oldFileName != null) {
              $collection->setImage($oldFileName);
            } else {
              $collection->setImage('');
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($collection);
            $em->flush();
            return $this->redirect($this->generateUrl('lfr_store_collection'));
        }
        return $this->render($this->entityNameSpace.':add.html.twig', array(
            'form' => $form->createView(),
            'collectionId' => $id,
            'img' => $collection_img_url,
        ));
    }
    public function removeAction(Request $request, $id){
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $collection = $em->getRepository($this->entityNameSpace)->find($id);
        $em->remove($collection);
        $em->flush();
        return $this->redirect($this->generateUrl('lfr_store_collection'));
    }
  }
