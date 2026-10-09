<?php

namespace App\Controller;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Exception\JobApplicationCvException;
use App\Form\JobApplicationType;
use App\Repository\JobOfferRepository;
use App\Service\JobApplicationSubmitter;
use App\Service\PublicContent;
use App\Service\RecaptchaVerifier;
use App\Service\SeoFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class JobApplicationController extends AbstractController
{
    private const RECEIPT = 'job_application_receipt';

    public function __construct(
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly JobApplicationSubmitter $submitter,
        private readonly PublicContent $publicContent,
        private readonly SeoFactory $seoFactory,
        private readonly RecaptchaVerifier $recaptchaVerifier,
        private readonly RateLimiterFactory $jobApplicationLimiter,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/nous-rejoindre/candidature-spontanee', name: 'job_apply_spontaneous', methods: ['GET', 'POST'])]
    public function spontaneous(Request $request): Response
    {
        return $this->handle($request, null);
    }

    #[Route('/nous-rejoindre/candidature-spontanee/confirmation', name: 'job_apply_spontaneous_confirmation', methods: ['GET'])]
    public function spontaneousConfirmation(Request $request): Response
    {
        return $this->confirmation($request, null);
    }

    #[Route('/nous-rejoindre/{slug}/postuler', name: 'job_apply', requirements: ['slug' => '(?!candidature-spontanee$)[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET', 'POST'])]
    public function apply(Request $request, string $slug): Response
    {
        $offer = $this->jobOfferRepository->findOpenBySlug($slug);
        if ($offer === null) {
            return $this->closed();
        }

        if ($offer->isExternalApplication()) {
            $url = (string) $offer->getApplicationUrl();
            if (preg_match('#^https?://#i', $url)) {
                return $this->redirect($url);
            }

            return $this->redirectToRoute('job_show', ['slug' => $offer->getSlug()]);
        }

        return $this->handle($request, $offer);
    }

    #[Route('/nous-rejoindre/{slug}/postuler/confirmation', name: 'job_apply_confirmation', requirements: ['slug' => '(?!candidature-spontanee$)[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function applyConfirmation(Request $request, string $slug): Response
    {
        return $this->confirmation($request, $slug);
    }

    private function handle(Request $request, ?JobOffer $offer): Response
    {
        $application = new JobApplication();
        $form = $this->createForm(JobApplicationType::class, $application, [
            'spontaneous' => $offer === null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $response = $this->accept($request, $form, $application, $offer);
            if ($response !== null) {
                return $response;
            }
        }

        return $this->render('page/job_apply.html.twig', [
            'form' => $form,
            'offer' => $offer,
            'seo' => $this->seoFactory->forJobApplication($offer, false),
            'header_overlay' => false,
            'recaptcha_site_key' => $this->recaptchaVerifier->siteKey(),
        ] + $this->publicContent->sectionData());
    }

    private function accept(Request $request, FormInterface $form, JobApplication $application, ?JobOffer $offer): ?Response
    {
        if (trim((string) $form->get('website')->getData()) !== '') {
            $application->captureContext($offer);
            $this->rememberReceipt($request, $application);

            return $this->redirectToConfirmation($offer);
        }

        $cv = $form->get('cv')->getData();
        if (!$cv instanceof UploadedFile) {
            $form->get('cv')->addError(new FormError('Le CV est obligatoire.'));

            return null;
        }

        $limiter = $this->jobApplicationLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Trop de candidatures ont été envoyées depuis cette connexion. Merci de réessayer dans quelques minutes.');

            return null;
        }

        $decision = $this->recaptchaVerifier->verify(
            (string) $form->get('recaptchaToken')->getData(),
            $request->getClientIp(),
            'apply',
        );
        if (!$decision->isAccepted()) {
            $this->addFlash('error', 'La vérification anti-spam a échoué. Merci de réessayer.');

            return null;
        }

        if ($offer !== null && !$offer->isOpenAt(new \DateTimeImmutable())) {
            return $this->closed();
        }

        $application->captureContext($offer);
        $application->recordConsent(new \DateTimeImmutable());

        try {
            $this->submitter->submit($application, $cv);
        } catch (JobApplicationCvException $exception) {
            $form->get('cv')->addError(new FormError($exception->getMessage()));

            return null;
        } catch (\Throwable $exception) {
            $this->logger->error('Candidature non enregistrée.', [
                'error' => $exception::class,
            ]);
            if (!$this->entityManager->isOpen()) {
                return new Response(
                    $this->renderView('page/job_apply_failure.html.twig'),
                    Response::HTTP_SERVICE_UNAVAILABLE,
                );
            }

            $this->addFlash('error', 'Votre candidature n’a pas pu être enregistrée. Merci de réessayer. Sélectionnez à nouveau votre CV.');

            return null;
        }

        $this->rememberReceipt($request, $application);

        return $this->redirectToConfirmation($offer);
    }

    private function confirmation(Request $request, ?string $slug): Response
    {
        $receipt = $request->getSession()->get(self::RECEIPT);
        $matches = is_array($receipt)
            && (($receipt['spontaneous'] ?? false) === ($slug === null))
            && ($slug === null || ($receipt['slug'] ?? null) === $slug);

        return $this->render('page/job_apply_confirmation.html.twig', [
            'receipt' => $matches ? $receipt : null,
            'offerSlug' => $slug,
            'seo' => $this->seoFactory->forJobApplication(
                $slug !== null ? $this->jobOfferRepository->findOpenBySlug($slug) : null,
                true,
                $slug,
            ),
            'header_overlay' => false,
        ] + $this->publicContent->sectionData());
    }

    private function closed(): Response
    {
        return $this->render('page/job_apply_closed.html.twig', [
            'seo' => $this->seoFactory->forClosedJobApplication(),
            'header_overlay' => false,
        ] + $this->publicContent->sectionData(), new Response('', Response::HTTP_NOT_FOUND));
    }

    private function rememberReceipt(Request $request, JobApplication $application): void
    {
        $request->getSession()->set(self::RECEIPT, [
            'firstName' => $application->getFirstName(),
            'title' => $application->getJobTitleSnapshot(),
            'slug' => $application->getJobOffer()?->getSlug(),
            'spontaneous' => $application->isSpontaneous(),
        ]);
    }

    private function redirectToConfirmation(?JobOffer $offer): Response
    {
        if ($offer === null) {
            return $this->redirectToRoute('job_apply_spontaneous_confirmation');
        }

        return $this->redirectToRoute('job_apply_confirmation', ['slug' => $offer->getSlug()]);
    }
}
