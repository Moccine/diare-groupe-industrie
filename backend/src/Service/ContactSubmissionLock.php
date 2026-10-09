<?php

namespace App\Service;

use App\Entity\ContactRequest;

final class ContactSubmissionLock
{
    public function exclusive(ContactRequest $request, callable $callback): mixed
    {
        $handle = $this->open($request);

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Verrou de contact indisponible.');
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return resource */
    private function open(ContactRequest $request)
    {
        $directory = sys_get_temp_dir().'/dgi-contact-locks';
        if (!is_dir($directory) && !@mkdir($directory, 01777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Verrou de contact indisponible.');
        }
        @chmod($directory, 01777);

        $path = $directory.DIRECTORY_SEPARATOR.$this->name($request).'.lock';
        $handle = fopen($path, 'c');
        if ($handle === false) {
            throw new \RuntimeException('Verrou de contact indisponible.');
        }
        @chmod($path, 0666);

        return $handle;
    }

    private function name(ContactRequest $request): string
    {
        return hash('sha256', implode("\0", [
            mb_strtolower(trim($request->getEmail())),
            trim($request->getSubject()),
            trim($request->getMessage()),
        ]));
    }
}
