<?php

namespace RPZ\DiscussionBundle\Controller;

use RPZ\DiscussionBundle\Entity\Article;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ArticleController extends Controller
{
    public $entityNameSpace = 'RPZDiscussionBundle:Article';
    public function indexAction() {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $articles = $repository->findBy(array(), array('date'=>'desc'));

        $commentRepository = $em->getRepository('RPZDiscussionBundle:Comment');
        //$comments = $commentRepository->findBy(array(), array('date'=>'desc'));

        foreach ($articles as $article) {
            $id = $article->getId();
            $article->comments = $commentRepository->whereArticle($id);
        }

        return $this->render($this->entityNameSpace.':index.html.twig', array(
                'articles' => $articles
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
    public function addAction(Request $request, $id = 0) {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        if($id == 0) {
            $article = new Article();
        } else {
            $repository = $this->getDoctrine()
              ->getManager()
              ->getRepository($this->entityNameSpace)
            ;
            $article = $repository->find($id);
        }
        $user = $this->getUser();
        $article->setAuthor($user->getUsername());
        $form = $this->get('form.factory')->createBuilder(FormType::class, $article)
        ->add('title', TextType::class)
        ->add('content', TextareaType::class)
        ->add('save',	SubmitType::class)
        ->getForm();

        $form->handleRequest($request);
        if($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();
            $request->getSession()->getFlashBag()->add('notice', 'Article bien publié.');
            return $this->redirect($this->generateUrl('rpz_discussion_article'));
        }
        return $this->render($this->entityNameSpace.':add.html.twig', array(
            'form' => $form->createView(),
            'articleId' => $id
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
        $request->getSession()->getFlashBag()->add('notice', 'Article supprimé.');
        return $this->redirect($this->generateUrl('rpz_discussion_article'));
    }
}
