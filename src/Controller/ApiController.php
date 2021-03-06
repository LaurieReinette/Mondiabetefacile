<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;

use App\Entity\Bloodsugar;
use App\Repository\BloodsugarRepository;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;






/**
 * @Route("/api")
 */
class ApiController extends AbstractController
{
    public function __construct(Security $security)
    {
        // Avoid calling getUser() in the constructor: auth may not
        // be complete yet. Instead, store the entire Security object.
        $this->security = $security;
    }
    /**
     * @Route("/login/check-email", name="check_mail", methods="POST")
     */
    public function checkEmail(Request $request, UserRepository $repository)
    {
        // on récupère le contenu du json envoyé par le front
        $jsonReceived = $request->getContent();

        $json = json_decode($jsonReceived);

        // on vérifie que le mail reçu est bien valide
        if (filter_var($json->email, FILTER_VALIDATE_EMAIL)) {
            // on va voir grace à la méthode checkEmailUnique si le mail est connu en bdd
            if ($repository->checkEmailUnique($json->email) == null) {
                // si le mail est connu on renvoie 
                $data = [ "known" => false];
                    return $this->json($data, 200, [], []);
                }
                $data = [ "known" => true];
                // si il est connu on renvoie une 200 afin d'afficher le reste du formulaire
                return $this->json($data, 200, [], []);
        }
        //si l'email est invalide on renvoie une 403 au front en leur expliquant

        $error = ["error" => "L'email entré par l'utilisateur n'est pas un email valide"];
        return $this->json($error, 403, $error, []);
    }


    /**
     * @Route("/login/signup", name="user_add", methods="POST")
     */
    public function userAdd(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder, UserRepository $repository)
    {
        // on récupère le contenu du json envoyé par le front
        $jsonReceived = $request->getContent();
        //dans le serializer le premier paramètre doit être une chaine de caractère
        //on crée un nouvel user
        $user = $serializer->deserialize($jsonReceived, User::class, 'json');
        
        // on stocke le json recu dans une nouvelle variable
        $json = json_decode($jsonReceived);


        // on vérifie que le mail reçu est bien valide
        if (filter_var($json->email, FILTER_VALIDATE_EMAIL)) {
            // on va voir grace à la méthode checkEmailUnique si le mail est connu en bdd
            if ($repository->checkEmailUnique($json->email) == null) {
                // on vérifie que le mot de pass et sa vérification correspondent
                if ($json->password == $json->checkPassword) {
                    //on crée une nouvelle date pour la création de l'utilisateur
                    $user->setCreatedAt(new \DateTime());
                    // on lui donne le rôle USER
                    $roles[] = 'ROLE_USER';
                    $user->setRoles($roles);
                    // on modifie son Username avec le mail
                    $user->setUsername($json->email);

                    $user->setLastname($json->lastname);

                    $user->setFirstname($json->firstname);

                    $user->setTargetMin($json->targetMin);

                    $user->setTargetMax($json->targetMax);

                    $user->setDoctorName($json->doctorName);

                    $user->setTreatment($json->treatment);

                    $user->setDoctorEmail($json->doctorEmail);

                    $user->setPassword($encoder->encodePassword($user, $json->password));
                    // on informe doctrine qu'on crée un nouvel user
                    $em->persist($user);
                     // et on l'envoie en bdd
                    $em->flush();

                    $userCreated =  $repository->find($user->getId());
                    
                       return $this->json($userCreated, 200, [], ['groups' => 'apiv0']);
                } else {
                    // sinon on précise l'rreur
                    $error = ["error" => "Les deux mots de pass ne coïcident pas"];

                    return $this->json($error, 403, $error, []);
                }
            }
            //si l'email est connu on renvoie une 403 au front en leur expliquant
            $error = ["error" => "L'email entré par l'utilisateur est déjà connu en base de données"];
            return $this->json($error, 403, $error, []);
        }
        //si l'email est invalide on renvoie une 403 au front en leur expliquant

        $error = ["error" => "L'email entré par l'utilisateur n'est pas un email valide"];
        return $this->json($error, 403, $error, []);
    }

    /**
     * @Route("/user", name="user_detail", methods="GET")
     */
    public function userView( )
    {
        $user = $this->getUser();

        if ($user == null) {
            $error = ["error" => "Utilisateur inconnu en base de données"];
            return $this->json($error, $status = 404, $error, $context = []);
        }
 
        return $this->json($user, 200, [], ['groups' => 'apiv0']);
    }
    /**
     * @Route("/user/fetch-bloodsugars", name="fetch_bloodsugars", methods="GET")
     */
    public function fetchBloosugars(UserRepository $userRepository)
    {
        $user = $this->getUser();

        if ($user == null) {
            $error = ["error" => "Utilisateur inconnu en base de données"];
            return $this->json($error, $status = 404, $error, $context = []);
        }
 
        return $this->json($userRepository->getAllBloodsugarsOrderByDateDes($user->getId()), 200, [], ['groups' => 'apiv0']);
    }

     /**
     * @Route("/user/bloodsugar/add", name="bloodsugar_add", methods="POST")
     */
    public function userProductManualAdd( Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UserRepository $userRepository)
    {
        // on récupère le user connecté
        $user = $this->getUser();
    

        // si il n'y en a pas on renvoie l'information au front
        if ($user == null) {
            $error = ["error" => "Utilisateur inconnu en base de données"];
            return $this->json($error, $status = 404, $error, $context = []);
        }
        // on récupère le json reçu de la requête
        $jsonReceived = $request->getContent();

        $json = json_decode($jsonReceived);

        $newBloodsugar = $serializer->deserialize($jsonReceived, BloodSugar::class, 'json');

        // on assigne à l'utilisateur
        $newBloodsugar->setUser($user);
        //on formate la date recu au format "Lundi 2 novembre 2020"

        if ($json->rate < $user->getTargetMin()){
            $newBloodsugar->setNormality("Hypoglycémie");
            $newBloodsugar->setLow(true);
            $newBloodsugar->setHigh(false);
            $newBloodsugar->setNormal(false);

        }
        elseif (($json->rate >= $user->getTargetMin()) && ($json->rate <= $user->getTargetMax())) {
            $newBloodsugar->setNormality("Dans la cible");
            $newBloodsugar->setNormal(true);
            $newBloodsugar->setHigh(false);
            $newBloodsugar->setLow(false);
            
        }
        else {
            $newBloodsugar->setNormality("Hyperglycémie");
            $newBloodsugar->setHigh(true);
            $newBloodsugar->setLow(false);
            $newBloodsugar->setNormal(false);
        }
        setlocale (LC_TIME, 'fr_FR.utf8','fra'); 
        $newBloodsugar->setDateSentence(strftime('%a%e %b %Y', strtotime($json->date)));
        $newBloodsugar->setTimeSentence(strftime('%Hh%M', strtotime($json->time)));
        $newBloodsugar->setDateString(strftime('%Y-%m-%d', strtotime($json->date)));
        $newBloodsugar->setTimeString(strftime('%H:%M', strtotime($json->time)));

        $em->persist($newBloodsugar);

        $em->flush();

        return $this->json($userRepository->getAllBloodsugarsOrderByDateDes($user->getId()), 200, [], ['groups' => 'apiv0']);
    }

    /**
     * @Route("/bloodsugar/edit/{id}", name="bloodsugar_edit", requirements={"id" = "\d+"}, methods="POST")
     */
    public function editBloodsugar($id, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, BloodsugarRepository $bloodsugarRepository, UserRepository $userRepository)
    {
        $user = $this->getUser();

        $bloodsugarFound= $bloodsugarRepository->find($id);
        
        if ($bloodsugarFound == null) {
            $error = ["error" => "La glycémie est inconnue en base de données"];
            return $this->json($error, $status = 404, $error, $context = []);
        }
        
        $jsonReceived = $request->getContent(); 
        
        $json = json_decode($jsonReceived);
        $bloodsugarToEdit = $serializer->deserialize($jsonReceived, BloodSugar::class, 'json');

        $bloodsugarFound->setCorrection($bloodsugarToEdit->getCorrection());
        $bloodsugarFound->setDate($bloodsugarToEdit->getDate());
        $bloodsugarFound->setTime($bloodsugarToEdit->getTime());
        $bloodsugarFound->setRate($bloodsugarToEdit->getRate());

        if ($json->rate < $user->getTargetMin()){
            $bloodsugarFound->setNormality("Hypoglycémie");
            $bloodsugarFound->setLow(true);
            $bloodsugarFound->setHigh(false);
            $bloodsugarFound->setNormal(false);

        }
        elseif (($json->rate >= $user->getTargetMin()) && ($json->rate <= $user->getTargetMax())) {
            $bloodsugarFound->setNormality("Dans la cible");
            $bloodsugarFound->setNormal(true);
            $bloodsugarFound->setHigh(false);
            $bloodsugarFound->setLow(false);
            
        }
        else {
            $bloodsugarFound->setNormality("Hyperglycémie");
            $bloodsugarFound->setHigh(true);
            $bloodsugarFound->setLow(false);
            $bloodsugarFound->setNormal(false);
        }
        
        setlocale (LC_TIME, 'fr_FR.utf8','fra'); 
        $bloodsugarFound->setDateSentence(strftime('%a%e %b %Y', strtotime($json->date)));
        $bloodsugarFound->setTimeSentence(strftime('%Hh%M', strtotime($json->time)));
        $bloodsugarFound->setDateString(strftime('%Y-%m-%d', strtotime($json->date)));
        $bloodsugarFound->setTimeString(strftime('%H:%M', strtotime($json->time)));

        $em->flush();

        return $this->json($userRepository->getAllBloodsugarsOrderByDateDes($user->getId()), 200, [], ['groups' => 'apiv0']);
    }
    /**
     * @Route("/bloodsugar/delete/{id}", name="bloosugar_delete", requirements={"id" = "\d+"}, methods="DELETE")
     */
    public function deleteBloodsugar($id, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, BloodsugarRepository $bloodsugarRepository, UserRepository $userRepository)
    {
        $user = $this->getUser();

        $bloodsugarToDelete= $bloodsugarRepository->find($id);
        
        if ($bloodsugarToDelete == null) {
            $error = ["error" => "La glycémie est inconnue en base de données"];
            return $this->json($error, $status = 404, $error, $context = []);
        }
        
        $jsonReceived = $request->getContent(); 
        
        $json = json_decode($jsonReceived);
        $em->remove($bloodsugarToDelete);
        $em->flush();

        return $this->json($userRepository->getAllBloodsugarsOrderByDateDes($user->getId()), 200, [], ['groups' => 'apiv0']);
    }

    /**
     * @Route("/bloodsugar/sendtodoctor", name="bloosugars_send", methods="GET")
     */
    public function sendBloodSugars(Request $request, UserRepository $userRepository, \Swift_Mailer $mailer)
    {
        $user = $this->getUser();
        $bloodsugars= $userRepository->getAllBloodsugarsOrderByDateDes($user->getId());
        
        $message = (new \Swift_Message('Les glycémies de votre patient '. $user->getFirstname() . ' ' . $user->getLastname()))
        ->setFrom('contact@diabetica.lauriereinette.fr')
        ->setTo($user->getDoctorEmail())
        ->setBody(
            $this->renderView(
                // templates/emails/registration.html.twig
                'emails/sendBloodsugars.html.twig',
                [
                    'bloodsugars' => $bloodsugars,
                    'user' => $user,
                ]
            ),
            'text/html'
        )
    ;

        $mailer->send($message);
     
            
        return $this->json([], 200, [], ['groups' => 'apiv0']);
    }
}
