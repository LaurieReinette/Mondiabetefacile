<?php

namespace App\Controller;

use App\Entity\Bloodsugar;
use App\Entity\User;
use App\Form\BloodsugarType;
use App\Form\UserType;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserController extends AbstractController
{
    /**
     * @Route("/user/mon-suivi", name="followup")
     */
    public function followup(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder)
    {
        $user = $this->getUser();


        // $user = $this->getUser();

        return $this->render('user/followup.html.twig', ["user" => $user]);
    }

    /**
     * @Route("/user/ajouter-une-glycemie", name="bloodsugarAdd")
     */
    public function bloodsugarAdd(Request $request, EntityManagerInterface $em)
    {
        $user = $this->getUser();

        $bloodsugar = new Bloodsugar;

        $form = $this->createForm(BloodsugarType::class, $bloodsugar);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentRate = $bloodsugar->getRate();

            $bloodsugar->setUser($user);

            if ($currentRate > $user->getTageMin() && $currentRate < $user->getTargetMax()){

                $bloodsugar->setScore(1);
            }
            if ($currentRate < $user->getTagetMin()){

                $bloodsugar->setScore(0);

            }
            if ($currentRate > $user->getTagetMax()){
                
                $bloodsugar->setScore(2);
            }

            $em->persist($bloodsugar);

            $em->flush();

            return $this->redirectToRoute('followup', ['user' => $user]);
        }

        // $user = $this->getUser();
        return $this->render('user/bloodsugarAdd.html.twig', [
            "formView" => $form->createView()
        ]);
    }
}
