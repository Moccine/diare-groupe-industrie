<?php

namespace App\Admin\Help;

use App\Controller\Admin\Crud\ContactRequestCrudController;
use App\Controller\Admin\Crud\HeroSlideCrudController;
use App\Controller\Admin\Crud\JobOfferCrudController;
use App\Controller\Admin\Crud\MediaCrudController;
use App\Controller\Admin\Crud\NewsCrudController;
use App\Controller\Admin\Crud\PageCrudController;
use App\Controller\Admin\Crud\PartnerCrudController;
use App\Controller\Admin\Crud\ProductCategoryCrudController;
use App\Controller\Admin\Crud\ProductCrudController;
use App\Controller\Admin\Crud\SectionCrudController;
use App\Controller\Admin\Crud\SiteSettingsCrudController;
use App\Controller\Admin\Crud\StatisticCrudController;
use App\Controller\Admin\Crud\UserCrudController;
use App\Controller\Admin\DashboardController;

/**
 * Aides longues des écrans d’administration.
 * Le même texte sert pour la liste, la création, la modification et le détail.
 */
final class AdminHelpRegistry
{
    public function getHelpFor(string $crudClass, string $pageName): ?AdminHelp
    {
        if ($pageName === '') {
            return null;
        }

        return $this->catalog()[$crudClass] ?? null;
    }

    /**
     * @return array<class-string, AdminHelp>
     */
    private function catalog(): array
    {
        return [
            DashboardController::class => $this->dashboard(),
            PageCrudController::class => $this->pages(),
            SectionCrudController::class => $this->sections(),
            HeroSlideCrudController::class => $this->banners(),
            MediaCrudController::class => $this->media(),
            SiteSettingsCrudController::class => $this->settings(),
            ProductCrudController::class => $this->products(),
            ProductCategoryCrudController::class => $this->categories(),
            StatisticCrudController::class => $this->statistics(),
            NewsCrudController::class => $this->news(),
            JobOfferCrudController::class => $this->jobs(),
            PartnerCrudController::class => $this->partners(),
            ContactRequestCrudController::class => $this->messages(),
            UserCrudController::class => $this->users(),
        ];
    }

    private function dashboard(): AdminHelp
    {
        return $this->help('Tableau de bord', [
            $this->section('À quoi sert cet écran ?', [
                'C’est la page d’accueil de l’administration. Elle ne modifie pas le site toute seule : elle indique quoi faire et où cliquer.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Comment ça fonctionne ?', [
                'Les grandes cartes ouvrent les tâches les plus courantes : pages, produit, actualité, offre, image, messages et site public.',
                'Les chiffres comptent les messages, les produits publiés, les actualités, les offres, les partenaires et les pages. Cliquez sur un chiffre pour ouvrir l’écran correspondant.',
            ], [
                '« À vérifier » signale ce qui manque encore pour que le site soit complet.',
                'La configuration indique si le logo, les couleurs, le contact et la carte sont renseignés.',
                'Les messages récents montrent les dernières demandes. Ceux qui ne sont pas encore lus sont mis en évidence.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Le compteur « Produits publiés » affiche 0. Ce n’est pas une panne : aucun produit n’est encore visible sur le site. Vous pouvez d’abord ajouter une image, puis créer le produit.',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('À savoir', [
                'Un chiffre à zéro n’est pas une erreur. Il peut simplement n’y avoir aucun élément publié.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function pages(): AdminHelp
    {
        return $this->help('Pages du site', [
            $this->section('À quoi sert cet écran ?', [
                'Une page est comme une rubrique du site : Accueil, À propos, Contact, Recrutement, et les rubriques que vous ajoutez.',
                'Ici, vous choisissez son titre, sa place dans le menu, son adresse et si les visiteurs peuvent l’ouvrir. Les blocs visibles à l’intérieur se gèrent ensuite dans « Contenus des pages ».',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Avant de commencer', [
                'Le texte du milieu de la page ne se rédige pas ici. Créez d’abord la page, puis ajoutez ses blocs dans « Contenus des pages ».',
            ], icon: 'fa-list-check', tone: 'prepare'),
            $this->section('Comment ça fonctionne ?', [
                'Le titre est le grand titre de la page. Si vous remplissez le titre de menu, c’est ce libellé qui apparaît dans la navigation.',
                '« Dans le menu » et la position décident si la page apparaît dans le menu, et dans quel ordre. 1 apparaît avant 2, puis 3.',
            ], [
                'Une page non publiée reste enregistrée, mais les visiteurs ne peuvent plus l’ouvrir.',
                'L’adresse se remplit toute seule à partir du titre. Sur une page déjà enregistrée, elle ne change que si vous ouvrez le cadenas.',
                'La page d’accueil utilise l’adresse « accueil ».',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Titre : Nos produits',
                'Adresse : nos-produits',
                'Lien : https://.../nos-produits',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('Résultat dans Google', [
                'Ces champs permettent de choisir le titre et le texte qui peuvent apparaître dans Google.',
                'Si vous les laissez vides, le site utilise automatiquement les informations principales de la page.',
            ], [
                'Titre Google : Diaré Groupe Industrie | Produits laitiers',
                'Description Google : Découvrez nos produits laitiers fabriqués en Guinée.',
            ], icon: 'fa-magnifying-glass', tone: 'result'),
            $this->section('À savoir', [
                'Évitez de modifier cette adresse après avoir partagé la page, car les anciens liens pourraient ne plus fonctionner. Après l’enregistrement, « Voir sur le site » permet de contrôler le résultat.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function sections(): AdminHelp
    {
        return $this->help('Contenus des pages', [
            $this->section('À quoi sert cet écran ?', [
                'La page est comme une feuille vide. Les contenus sont les blocs que l’on empile à l’intérieur.',
                'La page elle-même, son adresse, son menu et sa publication se gèrent dans « Pages du site ». Ici, vous décidez ce que le visiteur voit, et dans quel ordre.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Avant de commencer', [
                'La page doit déjà exister. Les images se choisissent dans la bibliothèque. Les grandes photos du bandeau se créent dans « Bannières d’accueil », puis se rattachent à un contenu de type Bannière.',
            ], icon: 'fa-list-check', tone: 'prepare'),
            $this->section('Dépendances', [
                'Certains blocs n’ont pas leur propre liste ici. Ils affichent ce qui est déjà publié ailleurs : produits, actualités, chiffres clés ou partenaires.',
            ], icon: 'fa-link', tone: 'link'),
            $this->section('Comment ça fonctionne ?', [
                'Choisissez la page, puis le type. Le type décide de la présentation et masque les champs inutiles. Un champ masqué n’est pas effacé : il réapparaît si vous changez de type.',
            ], [
                'La position place le bloc sur la page. 1 apparaît avant 2, puis 3.',
                '« Visible » retire le bloc du site sans le supprimer. Cela ne publie pas la page : la publication se règle sur la page.',
                'Si un type n’est pas reconnu, ce bloc n’est pas affiché. La page reste ouverte.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Page : Accueil',
            ], [
                'Bannière',
                'Présentation',
                'Produits',
                'Chiffres clés',
                'Actualités',
                'Partenaires',
            ], icon: 'fa-lightbulb', tone: 'example', ordered: true),
            $this->section('Résultat sur le site', [
                'Le visiteur voit ces blocs les uns sous les autres, dans l’ordre des positions. Après l’enregistrement, ouvrez la page publique pour contrôler le résultat.',
            ], icon: 'fa-eye', tone: 'result'),
            $this->section('À savoir', [
                'Pour une bannière, le titre et l’image du bloc ne servent qu’en secours, tant qu’aucune bannière active avec image n’est enregistrée.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function banners(): AdminHelp
    {
        return $this->help('Bannières d’accueil', [
            $this->section('À quoi sert cet écran ?', [
                'Une bannière est une grande photo affichée en haut d’une page, avec un titre et parfois un bouton. Elle n’apparaît que si la page possède un contenu de type Bannière, et si cette bannière y est rattachée.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Avant de commencer', [
                'Créez d’abord le bloc Bannière dans « Contenus des pages ». Ajoutez les images dans la bibliothèque : la photo pour grand écran est obligatoire.',
            ], icon: 'fa-list-check', tone: 'prepare'),
            $this->section('Comment ça fonctionne ?', [
                'L’ordre règle le passage des photos. Seules les bannières actives, avec une image, sont montrées. Une bannière inactive reste enregistrée.',
                'Le surtitre, le titre et le texte se posent sur la photo. Laissez vide ce que vous ne voulez pas afficher. Le bouton n’apparaît que si son texte est rempli.',
            ], [
                'L’image téléphone est facultative. Si elle est vide, la photo de grand écran est utilisée.',
                'La place du texte et l’assombrissement servent à garder le texte lisible sur la photo.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Vous voulez afficher une grande photo avec le texte :',
                'Diaré Groupe Industrie',
                'Du lait 100 % naturel',
                'et un bouton « Découvrir nos produits ».',
                'Créez une bannière, choisissez l’image, remplissez le titre et ajoutez le bouton.',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('Résultat sur le site', [
                'La photo, le titre et le bouton apparaissent en haut de la page qui contient le bloc Bannière.',
            ], icon: 'fa-eye', tone: 'result'),
            $this->section('À savoir', [
                'Sans photo de grand écran, la bannière est ignorée. Le bloc peut alors afficher son propre titre et son image de secours, s’ils sont renseignés dans le contenu de page.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function media(): AdminHelp
    {
        return $this->help('Bibliothèque d’images', [
            $this->section('À quoi sert cet écran ?', [
                'La bibliothèque d’images fonctionne comme un dossier central. Vous ajoutez une image une seule fois, puis vous pouvez la réutiliser dans plusieurs endroits du site.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Avant de commencer', [
                'Préparez un fichier JPEG, PNG, WebP, GIF ou AVIF, de 8 Mo au maximum. Donnez-lui un titre clair pour le retrouver dans les listes.',
            ], icon: 'fa-list-check', tone: 'prepare'),
            $this->section('Comment ça fonctionne ?', [
                'Décrivez l’image en une phrase, pour les personnes qui ne la voient pas. Exemple : « Ligne de production de lait Diaré Groupe Industrie ».',
                'La légende n’est affichée que sous l’image d’un bloc Texte et image. Le fichier est allégé automatiquement lorsque c’est possible.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'La photo « Usine Conakry » peut être utilisée à la fois dans une page, une actualité et un produit.',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('À savoir', [
                'Supprimer une image la retire de tous les endroits qui l’utilisent : produit, article, bannière, logo. L’écran indique ces utilisations avant la suppression. Remplacez le fichier si vous voulez garder les mêmes emplacements.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function settings(): AdminHelp
    {
        return $this->help('Coordonnées et réglages', [
            $this->section('À quoi sert cet écran ?', [
                'Cet écran rassemble l’identité du site : nom, logo, couleurs, contact, carte, réseaux, bouton du menu et pied de page. Il n’existe qu’une seule fiche. Un changement ici se voit sur tout le site.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Avant de commencer', [
                'Ajoutez le logo et la petite icône d’onglet dans la bibliothèque d’images avant de les choisir ici. N’inventez ni adresse, ni téléphone, ni position sur la carte.',
            ], icon: 'fa-list-check', tone: 'prepare'),
            $this->section('Comment ça fonctionne ?', [
                'Le nom, le logo et les couleurs se retrouvent partout. L’email, le téléphone et l’adresse alimentent le pied de page et la page contact.',
                'La carte reste masquée tant que la latitude et la longitude ne sont pas renseignées. Un réseau social vide n’affiche pas de lien. Le bouton du menu et le texte de pied de page sont facultatifs.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Résultat dans Google', [
                'Le titre et la description servent aux pages qui n’ont pas les leurs. Ils choisissent le titre et le texte qui peuvent apparaître dans Google.',
                'Si une page a déjà son propre titre ou son propre texte, ce sont ceux de la page qui sont utilisés.',
            ], [
                'Titre Google : Diaré Groupe Industrie | Produits laitiers',
                'Description Google : Découvrez nos produits laitiers fabriqués en Guinée.',
            ], icon: 'fa-magnifying-glass', tone: 'result'),
            $this->section('Exemple', [
                'Vous changez le téléphone ici. Le nouveau numéro apparaît dans le pied de page et sur la page Contact, sans modifier chaque page une par une.',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('À savoir', [
                'Vérifiez la page d’accueil et une page intérieure après l’enregistrement : en-tête, pied de page, couleurs et coordonnées changent partout.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function products(): AdminHelp
    {
        return $this->help('Gestion des produits', [
            $this->section('À quoi sert cet écran ?', [
                'Cet écran permet d’ajouter et de modifier les produits visibles dans le catalogue du site.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Avant de commencer', [
                'Vérifiez que la catégorie existe dans « Catégories de produits ». Les photos doivent déjà être dans la bibliothèque d’images.',
            ], icon: 'fa-list-check', tone: 'prepare'),
            $this->section('Comment ça fonctionne ?', [
                'Le nom et la catégorie identifient le produit. L’accroche apparaît sur la carte du catalogue. La description est le texte de la fiche. La photo principale sert dans la liste et sur la fiche ; les autres photos ajoutent des vues.',
            ], [
                'Un produit non publié reste enregistré ici, mais il n’est pas visible sur le site.',
                '« Mis en avant » l’affiche aussi dans les blocs produits de la page d’accueil.',
                'La position règle l’ordre. 1 apparaît avant 2, puis 3.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Vous souhaitez ajouter « Lait entier 1 L ».',
            ], [
                'Vérifiez que la catégorie « Produits laitiers » existe.',
                'Ajoutez la photo du produit dans la bibliothèque d’images.',
                'Créez le produit.',
                'Sélectionnez sa catégorie et son image.',
                'Activez « Publié ».',
                'Cliquez sur « Voir sur le site » pour contrôler le résultat.',
            ], icon: 'fa-lightbulb', tone: 'example', ordered: true),
            $this->section('Résultat dans Google', [
                'Le titre et le texte Google sont facultatifs. S’ils sont vides, le site utilise le nom du produit et son accroche.',
            ], icon: 'fa-magnifying-glass', tone: 'result'),
            $this->section('À savoir', [
                'L’adresse de la fiche se crée à partir du nom. Évitez de la modifier après avoir partagé le produit : les anciens liens pourraient ne plus fonctionner.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function categories(): AdminHelp
    {
        return $this->help('Catégories de produits', [
            $this->section('À quoi sert cet écran ?', [
                'Une catégorie range les produits ensemble et devient un filtre du catalogue. Elle apparaît aussi comme pastille sur les cartes produits.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Avant de commencer', [
                'Créez la catégorie avant les produits qui doivent y appartenir. La description saisie ici est une note interne : elle n’est pas affichée dans le catalogue.',
            ], icon: 'fa-list-check', tone: 'prepare'),
            $this->section('Comment ça fonctionne ?', [
                'Le nom est le libellé du filtre. L’icône et la couleur distinguent les pastilles. La position règle l’ordre des filtres. 1 apparaît avant 2, puis 3.',
            ], [
                'Une catégorie masquée disparaît des filtres.',
                'Ses produits peuvent rester visibles dans « Tout », s’ils sont eux-mêmes publiés.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Si vous vendez du lait, du yaourt et du beurre, vous pouvez créer une catégorie « Produits laitiers » et y ranger ces produits.',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('À savoir', [
                'Masquer une catégorie ne retire pas ses produits du site. Modifier l’adresse casse les liens de filtre déjà partagés.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function statistics(): AdminHelp
    {
        return $this->help('Chiffres clés', [
            $this->section('À quoi sert cet écran ?', [
                'Un chiffre clé est un nombre mis en avant dans les blocs Chiffres clés des pages. N’écrivez un nombre que s’il est confirmé.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Avant de commencer', [
                'La page doit contenir un contenu de type Chiffres clés. Sinon, le nombre reste enregistré ici et n’apparaît nulle part.',
            ], icon: 'fa-list-check', tone: 'prepare'),
            $this->section('Comment ça fonctionne ?', [
                'La valeur est le nombre affiché en grand. Le libellé est le texte juste en dessous. La précision, facultative, ajoute une courte phrase. La position règle l’ordre dans le bandeau.',
                'Pour afficher un signe comme +, écrivez-le dans la valeur, juste après le nombre.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Valeur : 25+',
                'Libellé : Années d’expérience',
                'Résultat affiché sur le site :',
                '25+',
                'Années d’expérience',
                'Si le nombre n’est pas confirmé, laissez la valeur « À renseigner ».',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('Résultat sur le site', [
                'Seuls les chiffres publiés sont visibles, et seulement dans les pages qui ont un bloc Chiffres clés.',
            ], icon: 'fa-eye', tone: 'result'),
            $this->section('À savoir', [
                'Si vous décochez « Publié », le chiffre disparaît du site mais reste enregistré ici. Le bloc de la page continue d’afficher les autres chiffres publiés.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function news(): AdminHelp
    {
        return $this->help('Actualités', [
            $this->section('À quoi sert cet écran ?', [
                'Une actualité est un article de la rubrique Actualités. Elle peut aussi apparaître dans un bloc Actualités d’une page.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Avant de commencer', [
                'Ajoutez l’illustration dans la bibliothèque d’images. Le chapô est le résumé de la liste. Le contenu est le texte complet de l’article.',
            ], icon: 'fa-list-check', tone: 'prepare'),
            $this->section('Comment ça fonctionne ?', [
                'L’article est visible seulement s’il est publié et si sa date de publication est atteinte. Une date future le laisse prêt, mais invisible jusque-là.',
            ], [
                'L’adresse se remplit à partir du titre. Évitez de la changer si l’article est déjà partagé.',
                'Le titre et le texte Google sont facultatifs. S’ils sont vides, le titre de l’article et le chapô sont utilisés.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Vous préparez un article « Nouvelle collecte de lait » pour le 12 octobre. Mettez cette date, cochez « Publiée » : l’article n’apparaît qu’à partir de ce jour. « Voir sur le site » n’est proposé que lorsqu’il est réellement visible.',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('À savoir', [
                'Décocher « Publiée » retire l’article du site tout de suite, même si la date est passée.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function jobs(): AdminHelp
    {
        return $this->help('Offres d’emploi', [
            $this->section('À quoi sert cet écran ?', [
                'Une offre décrit un poste sur la page recrutement : intitulé, lieu, contrat, missions et façon de candidater.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Comment ça fonctionne ?', [
                'L’offre est visible seulement si elle est publiée, si sa date de publication est atteinte et si elle n’est pas expirée. Une date future la prépare sans l’afficher. Après la date d’expiration, elle disparaît du site même si elle reste marquée publiée.',
            ], [
                'L’accroche est le résumé de la carte. La description et le profil recherché sont sur la fiche.',
                'Laissez vides le service, l’expérience ou la rémunération s’ils ne sont pas confirmés.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Si vous renseignez un lien de candidature, le candidat sera envoyé vers ce lien.',
                'Si aucun lien n’est renseigné, le bouton utilisera l’adresse email de candidature.',
                'Lien de candidature : https://exemple.com/candidature',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('Résultat sur le site', [
                'Le bouton Postuler ouvre le lien s’il est rempli. Sinon, il prépare un email vers l’adresse indiquée. « Voir sur le site » n’apparaît que pour une offre réellement ouverte.',
            ], icon: 'fa-eye', tone: 'result'),
            $this->section('À savoir', [
                'N’inventez pas d’adresse email. Une offre expirée reste dans l’administration, mais les visiteurs ne la voient plus.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function partners(): AdminHelp
    {
        return $this->help('Partenaires', [
            $this->section('À quoi sert cet écran ?', [
                'Un partenaire est un logo affiché dans les blocs Partenaires des pages qui en ont un. Il n’a pas de page à lui.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Avant de commencer', [
                'Ajoutez le logo dans la bibliothèque d’images. Le nom sert aussi de texte de remplacement si le logo est absent.',
            ], icon: 'fa-list-check', tone: 'prepare'),
            $this->section('Dépendances', [
                'Le partenaire n’apparaît pas tout seul. La page doit contenir un contenu de type Partenaires. Ce bloc reprend tous les partenaires publiés.',
            ], icon: 'fa-link', tone: 'link'),
            $this->section('Comment ça fonctionne ?', [
                'Si le site du partenaire est renseigné, le logo devient un lien. Laissez l’adresse vide pour afficher le logo sans lien. La position règle l’ordre des logos. 1 apparaît avant 2, puis 3.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Le logo « Partenaire local » peut apparaître sur l’accueil, si la page Accueil contient un contenu Partenaires et si ce partenaire est publié.',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('À savoir', [
                'Un partenaire non publié reste enregistré et n’apparaît plus dans les blocs. Les autres partenaires publiés continuent d’être affichés.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function messages(): AdminHelp
    {
        return $this->help('Messages reçus', [
            $this->section('À quoi sert cet écran ?', [
                'Cet écran consulte les messages envoyés depuis le formulaire de contact du site. Il ne sert pas à modifier les pages, les produits ou les réglages.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Comment ça fonctionne ?', [
                'Chaque ligne est une demande reçue : identité, société, email, téléphone, objet et message. Ces informations viennent du visiteur. Elles ne sont pas publiées sur le site.',
            ], [
                'Un message non lu est mis en évidence et compte dans le badge du menu.',
                'Cochez « Lu » lorsqu’il a été traité : le badge diminue.',
                'Répondez avec l’adresse email indiquée. L’administration n’envoie pas la réponse à votre place.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Une personne demande un devis. Vous lisez le message, vous lui répondez depuis votre messagerie, puis vous cochez « Lu ». Le message reste ici, mais il n’est plus compté comme nouveau.',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('À savoir', [
                'Vous pouvez supprimer un message de l’administration. Cela ne change rien au site public. Il n’est pas possible d’en créer un depuis cet écran.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    private function users(): AdminHelp
    {
        return $this->help('Administrateurs', [
            $this->section('À quoi sert cet écran ?', [
                'Un administrateur est une personne autorisée à se connecter à cette interface. Ce compte n’apparaît pas sur le site public.',
            ], icon: 'fa-compass', tone: 'info'),
            $this->section('Comment ça fonctionne ?', [
                'Le nom est affiché dans l’administration, par exemple sur le tableau de bord. L’email sert à se connecter.',
                'À la création, le mot de passe est obligatoire, confirmé, et doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial. Le bouton « Générer » remplit les deux champs. Lors d’une modification, laissez les deux champs vides pour conserver l’actuel.',
            ], icon: 'fa-gears', tone: 'flow'),
            $this->section('Exemple', [
                'Vous créez le compte d’un collègue, vous lui transmettez l’email et le mot de passe par un moyen sûr, puis vous lui demandez de le changer à la première connexion en rouvrant sa fiche.',
            ], icon: 'fa-lightbulb', tone: 'example'),
            $this->section('À savoir', [
                'Le mot de passe n’est jamais réaffiché après l’enregistrement. Choisissez-en un long. Chaque personne qui gère le site doit avoir son propre compte.',
                'En cas d’oubli, la page « Mot de passe oublié » envoie un lien de réinitialisation à l’adresse du compte, puis affiche une confirmation. Ce lien reste valable une heure.',
            ], icon: 'fa-triangle-exclamation', tone: 'warning'),
        ]);
    }

    /**
     * @param list<AdminHelpSection> $sections
     */
    private function help(string $title, array $sections): AdminHelp
    {
        return new AdminHelp($title, $sections);
    }

    /**
     * @param list<string> $paragraphs
     * @param list<string> $items
     */
    private function section(
        string $heading,
        array $paragraphs,
        array $items = [],
        string $icon = 'fa-circle-info',
        string $tone = 'info',
        bool $ordered = false,
    ): AdminHelpSection {
        return new AdminHelpSection($heading, $paragraphs, $items, $icon, $tone, $ordered);
    }
}
