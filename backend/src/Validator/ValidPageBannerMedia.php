<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ValidPageBannerMedia extends Constraint
{
    public string $message = 'Image de bannière invalide.';
}
