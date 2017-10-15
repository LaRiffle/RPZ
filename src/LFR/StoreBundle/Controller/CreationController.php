<?php

namespace LFR\StoreBundle\Controller;

use LFR\StoreBundle\Entity\Creation;
use LFR\StoreBundle\Entity\Type;
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
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class  CreationController extends Controller
{
    public $entityNameSpace = 'LFRStoreBundle:Creation';
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $creations = $repository->findBy(array(), array('id' => 'desc'));
        return $this->render($this->entityNameSpace.':index.html.twig', array(
          'creations' => $creations,
        ));
    }
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $creation = $repository->find($id);
        return $this->render('LFRStoreBundle:Search:show.html.twig', array(
          'creation' => $creation,
        ));
    }

    public function image_fix_orientation($path)
    {
        $image = imagecreatefromjpeg($path);
        $exif = exif_read_data($path);

        if (empty($exif['Orientation']))
        {
            return false;
        }
        switch ($exif['Orientation'])
        {
            case 3:
                $image = imagerotate($image, 180, 0);
                break;
            case 6:
                $image = imagerotate($image, - 90, 0);
                break;
            case 8:
                $image = imagerotate($image, 90, 0);
                break;
        }
        imagejpeg($image, $path);
        return true;
    }

    public function addAction(Request $request, $id = 0) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        if($id == 0) {
            $creation = new Creation();
        } else {
            $repository = $em->getRepository($this->entityNameSpace);
            $creation = $repository->find($id);
            $images = $creation->getImages();
            $creation->emptyImages();
            foreach ($images as $image) {
              $creation->addImage(
                  new File($this->getParameter('img_dir').'/'.$image)
              );
            }
            $new_creation = new Creation();
            $new_creation->setName($creation->getName());
            $new_creation->setTitle1($creation->getTitle1());
            $new_creation->setText1($creation->getText1());
            $new_creation->setTitle2($creation->getTitle2());
            $new_creation->setText2($creation->getText2());
            $new_creation->setOnsold($creation->getOnsold());
            $new_creation->setPrice($creation->getPrice());
            $new_creation->setCollection($creation->getCollection());
            $new_creation->setCategory($creation->getCategory());
            foreach($creation->getTypes() as $type){
              $new_creation->addType($type);
            }
        }
        $form = $this->get('form.factory')->createBuilder(FormType::class, ($id == 0 ? $creation : $new_creation))
        ->add('name', TextType::class)
        ->add('collection', EntityType::class, array(
                'class'        => 'LFRStoreBundle:Collection',
                'choice_label' => 'title',
        ))
        ->add('category', EntityType::class, array(
                'class'        => 'LFRStoreBundle:Category',
                'choice_label' => 'name',
        ))
        ->add('title1', TextType::class)
        ->add('text1', TextareaType::class)
        ->add('title2', TextType::class, array(
          'required' => false
        ))
        ->add('text2', TextareaType::class, array(
          'required' => false
        ))
        ->add('onsold', CheckboxType::class, array(
          'required' => false
        ))
        ->add('price', NumberType::class, array(
          'required' => false
        ))
        ->add('images', CollectionType::class, array(
            // each entry in the array will be an "image" field
            'entry_type'   => FileType::class,
            'allow_add'    => true,
            'allow_delete' => true,
            'required'     => false,
            // these options are passed to each "image" type
            'entry_options'  => array(
                'attr'      => array('class' => 'image-box')
            ),
        ))
        ->add('imgs', ChoiceType::class, array(
          'mapped' => false,
          'multiple' => true,
          'expanded' => true,
          'choices' => $creation->getImages(),
          'choice_label' => function ($value, $key, $index) {
              return $value;
          }
        ))
        ->add('types', EntityType::class, array(
                'class'        => 'LFRStoreBundle:Type',
                'choice_label' => 'name',
                'multiple'     => true,
                'expanded'     => true,
                'required'     => false))
        ->add('save',	SubmitType::class)
        ->getForm();

        $form->handleRequest($request);
        if($form->isValid()) {
            if($id == 0){
              $files = $creation->getImages();
              $creation->emptyImages();
              if($files != null) {
                foreach ($files as $file) {
                  // Generate a unique name for the file before saving it
                  $fileName = md5(uniqid()).'.'.$file->guessExtension();

                  // Move the file to the directory where images are stored
                  $file->move(
                      $this->getParameter('img_dir'),
                      $fileName
                  );
                  // Check orientation
                  $path = $this->getParameter('img_dir').'/'.$fileName;
                  $this->image_fix_orientation($path);

                  // Update the 'image' property to store the file name
                  // instead of its contents
                  $creation->addImage($fileName);
                }
              }
            } else {
              $files = $new_creation->getImages();
              $creation->emptyImages();
              $new_creation->emptyImages();
              // On met les ancienns images gardées
              $old_files = $form['imgs']->getData();
              foreach ($old_files as $old_file) {
                $fileName = $old_file->getFilename();
                $creation->addImage($fileName);
              }
              // And the new ones
              if($files != null) {
                foreach ($files as $file) {
                  // Generate a unique name for the file before saving it
                  $fileName = md5(uniqid()).'.'.$file->guessExtension();

                  // Move the file to the directory where images are stored
                  $file->move(
                      $this->getParameter('img_dir'),
                      $fileName
                  );
                  // Check orientation
                  $path = $this->getParameter('img_dir').'/'.$fileName;
                  $this->image_fix_orientation($path);

                  // Update the 'image' property to store the file name
                  // instead of its contents
                  $creation->addImage($fileName);
                }
              }
              $creation->setName($new_creation->getName());
              $creation->setTitle1($new_creation->getTitle1());
              $creation->setText1($new_creation->getText1());
              $creation->setTitle2($new_creation->getTitle2());
              $creation->setText2($new_creation->getText2());
              $creation->setOnsold($new_creation->getOnsold());
              $creation->setPrice($new_creation->getPrice());
              $creation->setCollection($new_creation->getCollection());
              $creation->setCategory($new_creation->getCategory());
              $creation->emptyTypes();
              foreach($new_creation->getTypes() as $type){
                $creation->addType($type);
              }
            }
            $em->persist($creation);
            $em->flush();
            //return new Response();
            return $this->redirect($this->generateUrl('lfr_store_explore'));
        }
        return $this->render($this->entityNameSpace.':add.html.twig', array(
            'form' => $form->createView(),
            'id' => $id
        ));
    }
    public function removeAction(Request $request, $id){
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $creation = $em->getRepository($this->entityNameSpace)->find($id);
        $em->remove($creation);
        $em->flush();
        return $this->redirect($this->generateUrl('lfr_store_explore'));
    }
  }
