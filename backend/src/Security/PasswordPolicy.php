<?php

namespace App\Security;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\When;

final class PasswordPolicy
{
    public const PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#\-_\.+=])[A-Za-z\d@$!%*?&#\-_\.+=]{8,}$/';

    /**
     * @return list<NotBlank|Length|Regex|When>
     */
    public static function constraints(bool $required): array
    {
        $rules = [
            new Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins 8 caractères.', max: 4096),
            new Regex(
                pattern: self::PATTERN,
                message: 'Le mot de passe doit contenir une minuscule, une majuscule, un chiffre et un caractère spécial.',
            ),
        ];

        if (!$required) {
            return [
                new When(
                    expression: 'value != null and value != ""',
                    constraints: $rules,
                ),
            ];
        }

        return [
            new NotBlank(message: 'Indiquez un mot de passe.'),
            ...$rules,
        ];
    }
}
