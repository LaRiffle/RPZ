<?php

namespace RPZ\DiscussionBundle\Controller;

use RPZ\DiscussionBundle\Entity\Log;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use \Datetime;


class LogController extends Controller
{
    public $entityNameSpace = 'RPZDiscussionBundle:Log';
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
    public function indexAction() {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $logs = $repository->findBy(array(), array('date'=>'desc'));

        return $this->render($this->entityNameSpace.':index.html.twig', array(
            'logs' => $logs
        ));
    }
    public function showAction() {
        /* Show who was recently active */
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $lastActivity = $repository->lastActivity();
        $date_now = new DateTime();
        $username = $this->getUser()->getUsername();
        $username_index = -1;
        foreach ($lastActivity as $key => $activity) {
          if($activity['username'] == $username){
            $username_index = $key;
          }
          $date_log = new DateTime($activity['date']);
          $diff = $date_now->getTimestamp() - $date_log->getTimestamp();
          $lastActivity[$key]['when'] = $this->time_since($diff);
          $lastActivity[$key]['username'] = $this->getAuthorName($lastActivity[$key]['username']);
        }
        if($username_index >= 0){
          unset($lastActivity[$username_index]);
        }

        return $this->render($this->entityNameSpace.':show.html.twig', array(
                'lastActivity' => $lastActivity,
        ));
    }
    public function messagesAction() {
        /* Show last messages */
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository("RPZDiscussionBundle:Article");

        $messages = $repository->findBy(array('type' => 'message'), array('date' => 'desc'));

        $user = $this->getUser();
        $lastMessages = [];
        foreach ($messages as $message) {
          $date_now = new DateTime();
          $date_log = $message->getDate();
          $diff = $date_now->getTimestamp() - $date_log->getTimestamp();
          $message->when = $this->time_since($diff);
          if($this->is_author($user, $message)){
            $commentRepository = $em->getRepository('RPZDiscussionBundle:Comment');
            $comments = $commentRepository->whereArticle($message->getId(), $limit=50);
            $nb_new = 0;
            $username = $user->getUsername();
            $info = [];
            foreach ($comments as $comment) {
              $info[] = $comment->getAuthor();
              if($username != $comment->getAuthor() && $this->getAuthorName($username) != $comment->getAuthor()){
                $nb_new++;
              } else {
                $nb_new = 0;
              }
            }
            $message->nb_new = $nb_new;
            $lastMessages[] = $message;
          }
        }

        return $this->render($this->entityNameSpace.':messages.html.twig', array(
                'lastMessages' => $lastMessages,
        ));
    }
    public function notifyAction() {
        /* Show who was recently active */
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
          return $this->redirect($this->generateUrl('login'));
        }
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository($this->entityNameSpace);
        $username = $this->getUser()->getUsername();
        $date_now = new DateTime();

        // Compute the last time the user realy was active (min. 30 minutes)
        $lastConnexionDate = $repository->lastConnexionDate($username);

        // Load the updated data (comments and articles)
        $commentRepository = $em->getRepository('RPZDiscussionBundle:Comment');
        $lastComments = $commentRepository->getLastComments($lastConnexionDate, $username);
        $articleRepository = $em->getRepository('RPZDiscussionBundle:Article');
        $lastArticles = $articleRepository->getLastArticles($lastConnexionDate, $username);

        // See all the articles involved in updates
        $commentedArticlesId = $commentRepository->getArticleIdFromComments($lastComments);
        $lastArticlesId = [];
        foreach ($lastArticles as $article) {
          $lastArticlesId[] = $article->getId();
        }
        $updatedArticlesId = array_unique(array_merge($commentedArticlesId, $lastArticlesId));

        $updatedArticles = $articleRepository->findBy( array('id' => $updatedArticlesId) );

        // Look for every article if some comments are attached
        foreach ($updatedArticles as $article_key => $article) {
          $updatedArticles[$article_key]->new_comments = [];
          $selected_index = [];
          // Attach some comments if necessary
          foreach ($lastComments as $comment_key => $comment) {
            if($comment->getArticle()->getId() == $article->getId()){
              $updatedArticles[$article_key]->new_comments[] = $comment;
              $selected_index[] = $comment_key;
            }
          }
        }
        // Create the notifications : three cases
        $notifications = [];
        foreach ($updatedArticles as $article_key => $article) {
          // Case : New article no new comments
          if(count($article->new_comments) == 0){
              $text = $this->getAuthorName($article->getAuthor()). ' a publié un article';
              $notif_date = $article->getDate();
          // Case : New article and new comments
          } else if(in_array($article->getId(), $lastArticlesId)){
              $text = $this->getAuthorName($article->getAuthor()). ' a publié un article. ';
              $notif_date = $article->getDate();
              $users = [];
              foreach($article->new_comments as $comment){
                $users[] = $this->getAuthorName($comment->getAuthor());
                $notif_date = max($notif_date, $comment->getDate());
              }
              $users = array_unique($users);
              if(count($users) == 1){
                $text = $text.$users[0].' l\'a commenté.';
              } else {
                $list_users = join(' et ', array_filter(array_merge(array(join(', ', array_slice($users, 0, -1))), array_slice($users, -1)), 'strlen'));
                $text = $text.$list_users.' l\'ont commenté.';
              }
          // Case : Old article with new comments
          } else {
              $notif_date = $article->getDate(); // just a init for the max function
              $users = [];
              foreach($article->new_comments as $comment){
                $users[] = $this->getAuthorName($comment->getAuthor());
                $notif_date = max($notif_date, $comment->getDate());
              }
              $users = array_unique($users);
              if(count($users) == 1){
                $text = $users[0].' a commenté';
              } else {
                $list_users = join(' et ', array_filter(array_merge(array(join(', ', array_slice($users, 0, -1))), array_slice($users, -1)), 'strlen'));
                $text = $list_users.' ont commenté';
              }
              $author;
              if($article->getAuthor()) {
                $author = $this->getAuthorName($article->getAuthor());
              } else {
                $author = $article->getAuthors()[0]->getUsername();
              }
              $text = $text.' l\'article de '.$author.'.';
          }
          // Transform date to lapse
          $diff = $date_now->getTimestamp() - $notif_date->getTimestamp();
          $time_since = $this->time_since($diff);
          $notifications[] = [
            'time_since' => $time_since,
            'date' => $notif_date,
            'text' => $text,
            'article_id' => $article->getId(),
          ];
        }

        $expiration_date = new DateTime('2017-09-27 20:00:00');
        if($date_now < $expiration_date){
          $notifications[] = [
            'time_since' => 'Nouveau !',
            'date' => $expiration_date,
            'text' => 'Pfiou toujours plus de fun, voilà les notifications !',
            'article_id' => '',
          ];
        }

        // Sort notifications by date
        usort($notifications, function ($n1, $n2)
        {
            $d1 = $n1['date'];
            $d2 = $n2['date'];
            if ($d1 == $d2) {
                return 0;
            }
            return ($d1 > $d2) ? -1 : 1;
        });

        return $this->render($this->entityNameSpace.':notify.html.twig', array(
                'notifications' => $notifications,
        ));
    }
}
