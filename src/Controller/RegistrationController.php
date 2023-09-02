<?php
/*
 * Kimengumi Library
 *
 * Licensed under the EUPL, Version 1.2 or – as soon they will be approved by
 * the European Commission - subsequent versions of the EUPL (the "Licence");
 * You may not use this work except in compliance with the Licence.
 * You may obtain a copy of the Licence at:
 *
 * https://joinup.ec.europa.eu/software/page/eupl
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the Licence is distributed on an "AS IS" basis,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the Licence for the specific language governing permissions and
 * limitations under the Licence.
 *
 * @author Antonio Rossetti <antonio@rossetti.fr>
 * @copyright since 2023 Antonio Rossetti
 * @license <https://joinup.ec.europa.eu/software/page/eupl> EUPL
 */

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    private EmailVerifier $emailVerifier;

    public function __construct( EmailVerifier $emailVerifier )
    {
        $this->emailVerifier = $emailVerifier;
    }

    #[Route( '/register', name: 'app_register' )]
    public function register( Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager ): Response
    {
        $user = new User();
        $form = $this->createForm( RegistrationFormType::class, $user );
        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get( 'plainPassword' )->getData()
                )
            );

            $entityManager->persist( $user );
            $entityManager->flush();

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation( 'app_verify_email', $user,
                ( new TemplatedEmail() )
                    ->from( new Address( 'register@library.localhost', 'Kimengumi Library' ) )
                    ->to( $user->getEmail() )
                    ->subject( 'Please Confirm your Email' )
                    ->htmlTemplate( 'registration/confirmation_email.html.twig' )
            );
            // do anything else you need here, like send an email

            return $this->redirectToRoute( 'app_login' );
        }

        return $this->render( 'registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ] );
    }

    #[Route( '/verify/email', name: 'app_verify_email' )]
    public function verifyUserEmail( Request $request, TranslatorInterface $translator ): Response
    {
        $this->denyAccessUnlessGranted( 'IS_AUTHENTICATED_FULLY' );

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation( $request, $this->getUser() );
        } catch ( VerifyEmailExceptionInterface $exception ) {
            $this->addFlash( 'verify_email_error', $translator->trans( $exception->getReason(), [], 'VerifyEmailBundle' ) );

            return $this->redirectToRoute( 'app_register' );
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash( 'success', 'Your email address has been verified.' );

        return $this->redirectToRoute( 'app_register' );
    }
}
