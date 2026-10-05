<?php

namespace App\Validator;

use App\Entity\Media;
use App\Media\PageBannerSpec;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidPageBannerMediaValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidPageBannerMedia) {
            throw new UnexpectedTypeException($constraint, ValidPageBannerMedia::class);
        }

        if ($value === null) {
            return;
        }

        if (!$value instanceof Media) {
            throw new UnexpectedValueException($value, Media::class);
        }

        $message = PageBannerSpec::violation($value);
        if ($message === null) {
            return;
        }

        $this->context->buildViolation($message)
            ->addViolation();
    }
}
