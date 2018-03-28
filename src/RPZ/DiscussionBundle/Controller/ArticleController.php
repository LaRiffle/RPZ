<?php

namespace RPZ\DiscussionBundle\Controller;

use RPZ\DiscussionBundle\Entity\Article;
use RPZ\DiscussionBundle\Entity\Comment;
use RPZ\DiscussionBundle\Entity\Log;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\File;
use \Doctrine\Common\Collections\Criteria;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;

use RPZ\UserBundle\Entity\User;

use \Datetime;

class ArticleController extends Controller
{
    public $entityNameSpace = 'RPZDiscussionBundle:Article';

    public $authorNames = [];
    public function getAuthorName($username) {
      if(array_key_exists($username, $this->authorNames)){
        return $this->authorNames[$username];
      }
      $em = $this->getDoctrine()->getManager();
      $repository = $em->getRepository('RPZUserBundle:User');
      $users = $repository->findBy(array('username' => $username));
      if($users != null){
        $user = $users[0];
        $authorName = ($user->getFirstname() != '') ? $user->getFirstname() : $username;
      } else {
        $authorName = $username;
      }
      $this->authorNames[$username] = $authorName;
      return $authorName;
    }
    public function time_since($since) {
        if($since < 60){
          return "à l'instant";
        }
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
        $print = ($count <= 1 || $name == 'mois') ? $count.' '.$name : "$count {$name}s";
        return "il y a ".$print;
    }
    public function is_author($user, $article) {
      $is_author = false;
      foreach($article->getAuthors() as $author) {
        if($user == $author){
          $is_author = true;
        }
      }
      return $is_author;
    }
    public function syncAction(Request $request) {
      if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
        return $this->redirect($this->generateUrl('login'));
      }
      $em = $this->getDoctrine()->getManager();
      $repository = $em->getRepository('RPZDiscussionBundle:Article');
      $commentRepository = $em->getRepository('RPZDiscussionBundle:Comment');

      $push = $request->request->get('push');
      if(count($push) > 0){
        foreach ($push as $data) {
          $articleId = $data[0];
          $comment_data = $data[1];
          $date =  DateTime::createFromFormat('U', $comment_data['date']); //new DateTime($comment_data['date_format']);
          $comment = new Comment();
          $comment->setAuthor($comment_data['author']);
          $comment->setDate($date);
          $comment->setText($comment_data['content']);
          $article = $repository->find($articleId);
          $comment->setArticle($article);
          if($article->getType() == 'message'){
              $date_now = new DateTime();
              $article->setDate($date_now);
              $em->persist($article);
          }
          $em->persist($comment);
        }
      }
      $em->flush();

      $pull = $request->request->get('pull');
      $responses = [];
      $date_now = new DateTime();
      if(count($pull) > 0){
        foreach ($pull as $data) {
          $comments = $commentRepository->whereArticle($data['articleId'], $data['limit'], $data['offset']);
          // There is no check if user has write to see these comment (can be of any message)
          $response = [];
          foreach ($comments as $comment) {
            $diff = $date_now->getTimestamp() - $comment->getDate()->getTimestamp();
            $response[] = array(
              'articleId' => $data['articleId'],
              'author' => $this->getAuthorName($comment->getAuthor()),
              'date' => $comment->getDate()->getTimestamp(),
              'time_since' => $this->time_since($diff),
              'content' => $comment->getText(),
            );
          }
          $responses[$data['articleId']] = $response;
        }
      }

      $bound = $request->request->get('bound');
      $criteria = new Criteria();
      $criteria->where($criteria->expr()->gt('id', intval($bound)));
      $comments = $commentRepository->matching($criteria);
      $lastcomment = $commentRepository->findBy([], array('id'=>'DESC'),1)[0];
      $bound = $lastcomment->getId();
      $comments_data = [];
      $date_now = new DateTime();
      $user = $this->getUser();
      foreach ($comments as $comment) {
        $diff = $date_now->getTimestamp() - $comment->getDate()->getTimestamp();
        $article = $comment->getArticle();
        if($article->getType() != 'message' || $this->is_author($user, $article)){
          $comments_data[] = array(
            'articleId' => $article->getId(),
            'author' => $this->getAuthorName($comment->getAuthor()),
            'date' => $comment->getDate()->getTimestamp(),
            'time_since' => $this->time_since($diff),
            'content' => $comment->getText(),
          );
        }
      }
      $data = array(
        'bound' => $bound,
        'comments' => $comments_data,
        'responses' => $responses,
      );
      return new JsonResponse($data);
    }
    public function indexAction($page = 1) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();
        // We save user activity
        $log = new Log();
        $username = $this->getUser()->getUsername();
        $log->setUsername($username);
        $em->persist($log);
        $em->flush();


        // Load articles
        $repository = $em->getRepository($this->entityNameSpace);
        $nbPerPage = 7;

        $qb = $em->createQueryBuilder();
        $qb->select('count(article.id)');
        $qb->from($this->entityNameSpace,'article');
        $nbArticles = $qb->getQuery()->getSingleScalarResult();

        $nbPages = ceil($nbArticles / $nbPerPage);
        $articles = $repository->findBy(
            array(),
            array('date'=>'desc'),
            $limit = $nbPerPage,
            $offset = $nbPerPage * ($page - 1)
        );

        // And the comments
        $commentRepository = $em->getRepository('RPZDiscussionBundle:Comment');
        $lastcomment = $commentRepository->findBy([], array('id'=>'DESC'),1)[0];
        $bound = $lastcomment->getId();
        $date_now = new DateTime();
        $relevant_articles = [];
        foreach ($articles as $article) {
            if($article->getAuthor()) {
              $article->users = [$this->getAuthorName($article->getAuthor())];
            } else {
              $article->users = [];
              foreach($article->getAuthors() as $author){
                $article->users[] = ($author->getFirstname() != '') ? $author->getFirstname() : $author->getUsername();
              }
            }
            $id = $article->getId();
            $diff = $date_now->getTimestamp() - $article->getDate()->getTimestamp();
            $article->time_since = $this->time_since($diff);
            $article->comments = $commentRepository->whereArticle($id, $limit=10);
            foreach ($article->comments as $comment) {
              $comment->user = $this->getAuthorName($comment->getAuthor());
              $diff = $date_now->getTimestamp() - $comment->getDate()->getTimestamp();
              $comment->time_since = $this->time_since($diff);
            }
            if($article->getType() == 'message'){
              if($this->is_author($user, $article)){
                $relevant_articles[] = $article;
              }
            } else {
              $relevant_articles[] = $article;
            }
        }

        return $this->render($this->entityNameSpace.':index.html.twig', array(
                'articles' => $relevant_articles,
                'page' => $page,
                'nbPages' => $nbPages,
                'username' => $this->getAuthorName($user->getUsername()),
                'bound' => $bound,
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
        $oldFileName = null;
        if($id == 0) {
            $article = new Article();
            $article->setType('article');
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
        $form = $this->get('form.factory')->createBuilder(FormType::class, $article)
        ->add('title', TextType::class, array('required' => False))
        ->add('content', TextareaType::class, array('required' => False))
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
              // Check orientation
              $path = $this->getParameter('img_dir').'/'.$fileName;
              $this->image_fix_orientation($path);

              // Update the 'image' property to store the file name
              // instead of its contents
              $article->setImage($fileName);
            } elseif($oldFileName != null) {
              $article->setImage($oldFileName);
            } else {
              $article->setImage('');
            }
            if($id == 0) {
                $user = $this->getUser();
                $article->setAuthor($user->getUsername());
                $article->addAuthor($user);
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
