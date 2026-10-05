<?php

namespace App\Controller;

use App\Entity\ContactRequest;
use App\Form\ContactRequestType;
use App\Repository\PageRepository;
use App\Service\ContactNotifier;
use App\Service\PublicContent;
use App\Service\RecaptchaVerifier;
use App\Service\SeoFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PublicContent $publicContent,
        private readonly SeoFactory $seoFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly ContactNotifier $contactNotifier,
        private readonly RateLimiterFactory $contactFormLimiter,
        private readonly RecaptchaVerifier $recaptchaVerifier,
    ) {
    }

    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        $page = $this->pageRepository->findPublishedBySlug('contact');
        $contactRequest = new ContactRequest();
        $form = $this->createForm(ContactRequestType::class, $contactRequest);
        $form->handleRequest($request);

        // CSRF et validation Symfony, puis honeypot, rate limiter, reCAPTCHA, enregistrement, e-mails.
        if ($form->isSubmitted() && $form->isValid()) {
            $honeypot = trim((string) $form->get('website')->getData());

            if ($honeypot !== '') {
                $this->addFlash('success', 'Votre message a bien été envoyé.');

                return $this->redirectToRoute('contact');
            }

            $limiter = $this->contactFormLimiter->create($request->getClientIp() ?? 'unknown');
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Trop de messages ont été envoyés. Merci de réessayer dans quelques minutes.');
            } else {
                $decision = $this->recaptchaVerifier->verify(
                    (string) $form->get('recaptchaToken')->getData(),
                    $request->getClientIp(),
                );

                if (!$decision->isAccepted()) {
                    $this->addFlash('error', 'La vérification anti-spam a échoué. Merci de réessayer.');
                } else {
                    $this->entityManager->persist($contactRequest);
                    $this->entityManager->flush();
                    $this->contactNotifier->notify($contactRequest, $this->publicContent->settings());
                    $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons dès que possible.');

                    return $this->redirectToRoute('contact');
                }
            }
        }

        return $this->render('page/contact.html.twig', [
            'page' => $page,
            'form' => $form,
            'seo' => $this->seoFactory->forPage($page, '/contact'),
            'header_overlay' => false,
            'recaptcha_site_key' => $this->recaptchaVerifier->siteKey(),
        ] + $this->publicContent->sectionData());
    }
}
