<?php

namespace App\Controller;

use App\Entity\ContactRequest;
use App\Entity\Product;
use App\Form\ContactRequestType;
use App\Repository\ContactRequestRepository;
use App\Repository\PageRepository;
use App\Repository\ProductRepository;
use App\Service\ContactNotifier;
use App\Service\ContactSubmissionLock;
use App\Service\PublicContent;
use App\Service\RecaptchaVerifier;
use App\Service\SeoFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
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
        private readonly ContactRequestRepository $contactRequests,
        private readonly ProductRepository $productRepository,
        private readonly ContactSubmissionLock $submissionLock,
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
        $contactProduct = $this->resolveContactProduct($request, $form);

        if (!$form->isSubmitted() && $contactProduct instanceof Product) {
            $form->get('productSlug')->setData($contactProduct->getSlug());
            if (trim((string) $form->get('subject')->getData()) === '') {
                $subject = $this->inquirySubject($contactProduct);
                $contactRequest->setSubject($subject);
                $form->get('subject')->setData($subject);
            }
        }

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
                    $postedProduct = $this->resolveContactProduct($request, $form);
                    if ($postedProduct instanceof Product) {
                        $this->identifyProduct($contactRequest, $postedProduct);
                    }

                    $stored = $this->submissionLock->exclusive($contactRequest, function () use ($contactRequest): bool {
                        $duplicate = $this->contactRequests->findRecentDuplicate(
                            $contactRequest->getEmail(),
                            $contactRequest->getSubject(),
                            $contactRequest->getMessage(),
                            new \DateTimeImmutable('-60 seconds'),
                        );
                        if ($duplicate !== null) {
                            return false;
                        }

                        $this->entityManager->persist($contactRequest);
                        $this->entityManager->flush();

                        return true;
                    });
                    if ($stored === true) {
                        $this->contactNotifier->notify($contactRequest, $this->publicContent->settings());
                    }
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
            'contact_product' => $contactProduct,
        ] + $this->publicContent->sectionData());
    }

    private function resolveContactProduct(Request $request, FormInterface $form): ?Product
    {
        $slug = $form->isSubmitted()
            ? trim((string) $form->get('productSlug')->getData())
            : trim($request->query->getString('produit'));

        if ($slug === '' || strlen($slug) > 180) {
            return null;
        }

        return $this->productRepository->findPublishedBySlug($slug);
    }

    private function inquirySubject(Product $product): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $product->getName()));
        $subject = 'Demande d\'informations — '.$name;
        if (mb_strlen($subject) <= 180) {
            return $subject;
        }

        return mb_substr($subject, 0, 179).'…';
    }

    private function identifyProduct(ContactRequest $contactRequest, Product $product): void
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $product->getName()));
        if ($name === '') {
            return;
        }

        $prefix = 'Produit concerné : '.$name.".\n\n";
        $message = $contactRequest->getMessage();
        if (str_starts_with($message, $prefix)) {
            return;
        }

        $room = 5000 - mb_strlen($prefix);
        if ($room < 10) {
            return;
        }

        if (mb_strlen($message) > $room) {
            $message = mb_substr($message, 0, $room);
        }

        $contactRequest->setMessage($prefix.$message);
    }
}
