<?php

namespace RPZ\DiscussionBundle\Controller;

use RPZ\DiscussionBundle\Entity\Article;
use RPZ\DiscussionBundle\Entity\Log;
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

use \Datetime;

class ArticleController extends Controller
{
    public $entityNameSpace = 'RPZDiscussionBundle:Article';
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
    public function indexAction($page = 1) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        // We save user activity
        $log = new Log();
        $username = $this->getUser()->getUsername();
        $log->setUsername($username);
        $em->persist($log);
        $em->flush();


        // Load articles
        $repository = $em->getRepository($this->entityNameSpace);
        $nbPerPage = 5;
        $nbArticles = count($repository->findAll());
        $nbPages = ceil($nbArticles / $nbPerPage);
        $articles = $repository->findBy(
            array(),
            array('date'=>'desc'),
            $limit = $nbPerPage,
            $offset = $nbPerPage * ($page - 1)
        );

        // And the comments
        $commentRepository = $em->getRepository('RPZDiscussionBundle:Comment');
        $date_now = new DateTime();
        foreach ($articles as $article) {
            $id = $article->getId();
            $diff = $date_now->getTimestamp() - $article->getDate()->getTimestamp();
            $article->time_since = $this->time_since($diff);
            $article->user = $this->getAuthorName($article->getAuthor());
            $article->comments = $commentRepository->whereArticle($id);
            foreach ($article->comments as $comment) {
              $comment->user = $this->getAuthorName($comment->getAuthor());
              $diff = $date_now->getTimestamp() - $comment->getDate()->getTimestamp();
              $comment->time_since = $this->time_since($diff);
            }
        }

        return $this->render($this->entityNameSpace.':index.html.twig', array(
                'articles' => $articles,
                'page' => $page,
                'nbPages' => $nbPages
        ));
    }
    public function showAction($id) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $article = $repository->find($id);
        return $this->render($this->entityNameSpace.':show.html.twig',array(
                'article' => $article
        ));
    }

    public function editAction($id) {
      return $this->render($this->entityNameSpace.':edit.html.twig', array(
          'articleId' => $id
      ));
    }

    public function addAction(Request $request, $id = 0) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $oldFileName = null;
        if($id == 0) {
            $article = new Article();
        } else {
            $repository = $this->getDoctrine()
              ->getManager()
              ->getRepository($this->entityNameSpace)
            ;
            $article = $repository->find($id);
            if($article->getImage() != ''){
              $oldFileName = $article->getImage();
              $article->setImage(
                  new File($this->getParameter('img_dir').'/'.$article->getImage())
              );
            }
        }
        if($oldFileName != null) {
          $article_img_url = $oldFileName;
        } else {
          $article_img_url = '';
        }
        $user = $this->getUser();
        $article->setAuthor($user->getUsername());
        $form = $this->get('form.factory')->createBuilder(FormType::class, $article)
        ->add('title', TextType::class)
        ->add('content', TextareaType::class)
        ->add('image', FileType::class, array('label' => 'Image', 'required' => False))
        ->add('save',	SubmitType::class)
        ->getForm();

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            // $file stores the uploaded PDF file
            /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */
            $file = $article->getImage();
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
              $article->setImage($fileName);
            } elseif($oldFileName != null) {
              $article->setImage($oldFileName);
            } else {
              $article->setImage('');
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();
            $request->getSession()->getFlashBag()->add('success', 'Article bien publié.');
            return $this->redirect($this->generateUrl('rpz_discussion_article'));
        }
        return $this->render($this->entityNameSpace.':add.html.twig', array(
            'form' => $form->createView(),
            'articleId' => $id,
            'img' => $article_img_url,
        ));

    }
    public function removeAction(Request $request, $id = 0){
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();

        $commentRepository = $em->getRepository('RPZDiscussionBundle:Comment');
        $comments = $commentRepository->whereArticle($id);
        $entity = $em->getRepository($this->entityNameSpace)->find($id);

        foreach ($comments as $comment) {
            $em->remove($comment);
        }
        $em->remove($entity);
        $em->flush();
        $request->getSession()->getFlashBag()->add('danger', 'Article supprimé.');
        return $this->redirect($this->generateUrl('rpz_discussion_article'));
    }
}
