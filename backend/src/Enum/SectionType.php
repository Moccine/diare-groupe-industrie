<?php

namespace App\Enum;

enum SectionType: string
{
    case Hero = 'hero';
    case TextImage = 'text_image';
    case Products = 'products';
    case Statistics = 'statistics';
    case Mission = 'mission';
    case Vision = 'vision';
    case Values = 'values';
    case Distribution = 'distribution';
    case Partners = 'partners';
    case News = 'news';
    case Cta = 'cta';
    case Gallery = 'gallery';
    case Quality = 'quality';
    case Activities = 'activities';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Bannière',
            self::TextImage => 'Texte et image',
            self::Products => 'Produits',
            self::Statistics => 'Chiffres clés',
            self::Mission => 'Mission',
            self::Vision => 'Vision',
            self::Values => 'Valeurs',
            self::Distribution => 'Distribution',
            self::Partners => 'Partenaires',
            self::News => 'Actualités',
            self::Cta => 'Appel à l’action',
            self::Gallery => 'Galerie',
            self::Quality => 'Engagement qualité',
            self::Activities => 'Activités',
            self::Custom => 'Contenu libre',
        };
    }

    public function guide(): string
    {
        return match ($this) {
            self::Hero => 'Grand bandeau en haut de page. S’il existe des bannières actives avec image, ce sont elles qui s’affichent.',
            self::TextImage => 'Texte accompagné d’une image.',
            self::Products => 'Cartes des produits mis en avant.',
            self::Statistics => 'Chiffres clés publiés dans le menu Chiffres clés.',
            self::Mission => 'Texte de la mission.',
            self::Vision => 'Texte de la vision.',
            self::Values => 'Liste de valeurs. Chaque valeur est une ligne.',
            self::Distribution => 'Présentation du réseau, avec un bouton et des lignes de détail.',
            self::Partners => 'Logos des partenaires publiés.',
            self::News => 'Dernières actualités publiées, avec un bouton facultatif.',
            self::Cta => 'Bandeau qui invite le visiteur à cliquer sur un bouton.',
            self::Gallery => 'Grille d’images : l’image principale et les lignes qui ont une image.',
            self::Quality => 'Texte d’engagement qualité, éventuellement posé sur une image de fond.',
            self::Activities => 'Liste d’activités. Chaque activité est une ligne.',
            self::Custom => 'Texte libre, avec des lignes facultatives.',
        };
    }
}
