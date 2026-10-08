<?php

namespace App\Controller\Admin;

use App\Form\ForgotPasswordFormType;
use App\Form\ResetPasswordFormType;
use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use App\Service\PublicContent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.password_reset')]
        private readonly RateLimiterFactory $passwordResetLimiter,
        private readonly PublicContent $publicContent,
    ) {
    }

    #[Route('/administration/connexion', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin');
        }

        return $this->render('admin/security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/administration/deconnexion', name: 'admin_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode est interceptée par le firewall.');
    }

    #[Route('/administration/mot-de-passe-oublie', name: 'admin_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        PasswordResetService $passwordResetService,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin');
        }

        $form = $this->createForm(ForgotPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (trim((string) $form->get('website')->getData()) !== '') {
                $this->addFlash('success', 'Si un compte correspond à cette adresse, un email de réinitialisation vient d’être envoyé.');

                return $this->redirectToRoute('admin_login');
            }

            $limiter = $this->passwordResetLimiter->create($request->getClientIp() ?? 'unknown');
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Trop de demandes ont été envoyées. Merci de réessayer dans quelques minutes.');
            } else {
                $email = mb_strtolower(trim((string) $form->get('email')->getData()));
                $user = $userRepository->findOneByEmail($email);
                if ($user !== null) {
                    $token = $passwordResetService->generateResetToken($user);
                    $passwordResetService->sendResetEmail($user, $token, $this->publicContent->settings());
                }

                $this->addFlash('success', 'Si un compte correspond à cette adresse, un email de réinitialisation vient d’être envoyé.');

                return $this->redirectToRoute('admin_login');
            }
        }

        return $this->render('admin/security/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/administration/reinitialisation/{token}', name: 'admin_reset_password', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function resetPassword(
        #[\SensitiveParameter]
        string $token,
        Request $request,
        PasswordResetService $passwordResetService,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin');
        }

        $user = $passwordResetService->findUserByToken($token);
        if ($user === null) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré.');

            return $this->redirectToRoute('admin_forgot_password');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $passwordResetService->resetPassword($user, (string) $form->get('plainPassword')->getData());

            $this->addFlash('success', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');

            return $this->redirectToRoute('admin_login');
        }

        return $this->render('admin/security/reset_password.html.twig', [
            'form' => $form,
        ]);
    }
}
