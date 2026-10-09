<?php

namespace App\Controller\Admin;

use App\Entity\JobApplication;
use App\Exception\JobApplicationCvException;
use App\Repository\JobApplicationRepository;
use App\Service\JobApplicationCvStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class JobApplicationCvController extends AbstractController
{
    public function __construct(
        private readonly JobApplicationRepository $applications,
        private readonly JobApplicationCvStorage $storage,
    ) {
    }

    #[Route('/administration/candidatures/{id}/cv', name: 'admin_job_application_cv_view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function view(int $id): Response
    {
        return $this->deliver($id, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[Route('/administration/candidatures/{id}/cv/telecharger', name: 'admin_job_application_cv_download', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function download(int $id): Response
    {
        return $this->deliver($id, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    private function deliver(int $id, string $disposition): Response
    {
        $application = $this->applications->find($id);
        if (!$application instanceof JobApplication || !$application->isCvAvailable()) {
            throw $this->createNotFoundException();
        }

        try {
            $path = $this->storage->absolutePath($application->getCvStoredFilename());
        } catch (JobApplicationCvException) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition($disposition, $this->downloadName($application));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Content-Security-Policy', 'sandbox');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function downloadName(JobApplication $application): string
    {
        $name = basename(str_replace('\\', '/', $application->getCvOriginalFilename()));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? '';
        $name = trim($name, '.-');
        if ($name === '') {
            return 'cv.pdf';
        }

        if (!str_ends_with(strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }

        return $name;
    }
}
