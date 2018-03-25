<?php

namespace RPZ\DiscussionBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\HttpFoundation\Cookie;

use RPZ\UserBundle\Entity\User;

use \Datetime;

class AdminController extends Controller
{
    public $entityNameSpace = 'RPZDiscussionBundle:Admin';
    public function registerAction(Request $request){
        $authorization = $request->query->get('authorization');
        $username = $request->query->get('username');
        $password = $request->query->get('password');

        if($authorization != $this->getParameter('admin_auth')) {
            $data = ['output' => 'Action forbidden.'];
            return new JsonResponse($data);
        }
        // Retrieve the security encoder of symfony
        $factory = $this->get('security.encoder_factory');

        // Retrieve the user by its username:
        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository("RPZUserBundle:User")
                ->findOneBy(array('username' => $username));

        // If user doesn't exist, we create it.
        if(!$user){
            $em = $this->getDoctrine()->getManager();
            $repository = $em->getRepository('RPZUserBundle:User');
            $user = new User;
            $user->setUsername($username);
            $user->setPassword($password);
            $user->setFirstname('');
            $user->setSurname('');
            $user->setSalt('');
            $user->setRoles(array('ROLE_USER'));
            $em->persist($user);
            $em->flush();
            $opt_message = 'New user created.';
        } else {
          $opt_message = 'User already registered.';
        }



        // Proceed to set the user in session

        // Handle getting or creating the user entity likely with a posted form
        // "main" is name of your firewall in security.yml
        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->get('security.token_storage')->setToken($token);

        // $this->get('session')->set('_security_XXXFIREWALLNAMEXXX', serialize($token));
        $this->get('session')->set('_security_main', serialize($token));

        // Fire the login event manually
        $event = new InteractiveLoginEvent($request, $token);
        $this->get("event_dispatcher")->dispatch("security.interactive_login", $event);

        // Now the user is authenticated
        $data = [
          'output' => 'Welcome ! '.$opt_message,
        ];
        return new JsonResponse($data);
    }
}
