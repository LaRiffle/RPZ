<?php

namespace RPZ\DiscussionBundle\Controller;

use RPZ\DiscussionBundle\Entity\Comment;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use \Datetime;

class CommentController extends Controller
{
    public $entityNameSpace = 'RPZDiscussionBundle:Comment';
    public function indexAction() {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $comments = $repository->findBy(array(), array('date'=>'desc'));

        return $this->render($this->entityNameSpace.':index.html.twig', array(
                'comments' => $comments
        ));
    }
    public function showAction($id) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $comment = $repository->find($id);
        return $this->render($this->entityNameSpace.':show.html.twig',array(
                'comment' => $comment
        ));
    }
    public function editAction($article_id, $id) {
      return $this->render($this->entityNameSpace.':edit.html.twig', array(
          'articleId' => $id,
          'commentId' => $article_id,
      ));
    }
    public function addAction(Request $request, $article_id, $id = 0, $type = "article") {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        if($id == 0) {
            $comment = new Comment();
        } else {
            $repository = $this->getDoctrine()
              ->getManager()
              ->getRepository($this->entityNameSpace)
            ;
            $comment = $repository->find($id);
        }
        $user = $this->getUser();
        $comment->setAuthor($user->getUsername());
        $form = $this->get('form.factory')->createBuilder(FormType::class, $comment)
        ->add('text', TextareaType::class)
        ->add('save',	SubmitType::class)
        ->getForm();

        $form->handleRequest($request);
        if($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $articleRepository = $em->getRepository('RPZDiscussionBundle:Article');
            $article = $articleRepository->find($article_id);
            if($article->getType() == 'message'){
                $date_now = new DateTime();
                $article->setDate($date_now);
                $em->persist($article);
            }
            $comment->setArticle($article);

            $em->persist($comment);
            $em->flush();
            $request->getSession()->getFlashBag()->add('success', 'Commentaire bien publié.');
            return $this->redirect($this->generateUrl('rpz_discussion_article'));
        }
        return $this->render($this->entityNameSpace.':add.html.twig', array(
            'form' => $form->createView(),
            'articleId' => $article_id,
            'commentId' => $id,
            'type' => $type,
        ));

    }
    public function removeAction(Request $request, $id = 0){
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository($this->entityNameSpace)->find($id);
        $em->remove($entity);
        $em->flush();
        $request->getSession()->getFlashBag()->add('danger', 'Comment supprimé.');
        return $this->redirect($this->generateUrl('rpz_discussion_article'));
    }
}
