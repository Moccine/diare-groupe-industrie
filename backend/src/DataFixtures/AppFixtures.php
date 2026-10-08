<?php

namespace App\DataFixtures;

use App\Entity\HeroSlide;
use App\Entity\Media;
use App\Entity\Page;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Section;
use App\Entity\SectionItem;
use App\Entity\SiteSettings;
use App\Entity\Statistic;
use App\Enum\CategoryAccent;
use App\Enum\CategoryIcon;
use App\Enum\HeroContentPosition;
use App\Enum\HeroOverlay;
use App\Enum\SectionTheme;
use App\Enum\SectionType;
use App\Service\AdminUserProvisioner;
use App\Service\MediaUploader;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly MediaUploader $mediaUploader,
        private readonly AdminUserProvisioner $adminUserProvisioner,
        private readonly EditorialDemoSeeder $editorialDemoSeeder,
        private readonly string $fixtureMediaDir,
        private readonly string $mediaUploadDir,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->adminUserProvisioner->provision();
        $this->clearUploadedMedia();

        $logo = $this->importMedia($manager, 'logo.png', 'Logo Diaré Groupe Industrie', 'Logo DGI');
        $laitImage = $this->importMedia($manager, 'lait-et-derives.png', 'Lait et dérivés', 'Lait et dérivés');
        $fraisImage = $this->importMedia($manager, 'produits-laitiers-frais.png', 'Produits laitiers frais', 'Produits laitiers frais');
        $derivesImage = $this->importMedia($manager, 'produits-derives.png', 'Produits dérivés', 'Produits dérivés');
        $miniMais = $this->importMedia($manager, 'biscuit-mini-mais.png', 'Biscuit Mini Maïs', 'Biscuit Mini Maïs');
        $miniKansy = $this->importMedia($manager, 'biscuit-mini-kansy.png', 'Biscuit Mini Kansy', 'Biscuit Mini Kansy');
        $biscuitAbc = $this->importMedia($manager, 'biscuit.png', 'Biscuit ABC', 'Biscuit ABC');
        $sugar = $this->importMedia($manager, 'sucre.png', 'Sucre raffiné', 'Sucre raffiné');
        $vanilla = $this->importMedia($manager, 'biscuits-vanille.png', 'Biscuits vanille', 'Biscuits vanille');

        $settings = (new SiteSettings())
            ->setCompanyName('Diaré Groupe Industrie')
            ->setLogo($logo)
            ->setFavicon($logo)
            ->setPrimaryColor(SiteSettings::PRIMARY)
            ->setSecondaryColor(SiteSettings::SECONDARY)
            ->setAccentColor(SiteSettings::ACCENT)
            ->setPhone('+224 612 67 25 25')
            ->setAddress('Dubréka, Carrefour Kaléma')
            ->setFooterText('Diaré Groupe Industrie fabrique et commercialise des produits alimentaires tels que le lait, les biscuits, le sucre et d’autres produits de grande consommation.')
            ->setCopyright('Tous droits réservés.')
            ->setHeaderCtaLabel('Contact')
            ->setHeaderCtaUrl('/contact')
            ->setMetaTitle('Diaré Groupe Industrie')
            ->setMetaDescription('Diaré Groupe Industrie est une société industrielle engagée dans la fabrication et la commercialisation de produits alimentaires tels que le lait, les biscuits, le sucre et d’autres produits de grande consommation.');
        $manager->persist($settings);

        $lait = $this->category($manager, 'Lait', 'lait', 'Lait et produits laitiers.', 1, CategoryIcon::Milk, CategoryAccent::Forest);
        $biscuits = $this->category($manager, 'Biscuits', 'biscuits', 'Gammes de biscuits.', 2, CategoryIcon::Biscuit, CategoryAccent::Biscuit);
        $sucre = $this->category($manager, 'Sucre', 'sucre', 'Sucre raffiné.', 3, CategoryIcon::Sugar, CategoryAccent::Rose);

        $this->product($manager, 'Lait et dérivés', 'lait-et-derives', $lait, 'Un lait frais, riche et nutritif, produit selon des normes strictes.', 'Un lait frais, riche et nutritif, produit selon des normes strictes, qui apporte douceur et santé dans chaque verre.', $laitImage, true, 1);
        $this->product($manager, 'Produits laitiers frais', 'produits-laitiers-frais', $lait, 'Lait pasteurisé, lait UHT et lait en poudre.', 'Lait pasteurisé, lait UHT et lait en poudre, pour les foyers, les commerces et les distributeurs.', $fraisImage, false, 2);
        $this->product($manager, 'Produits dérivés', 'produits-derives', $lait, 'Yaourts, fromages, beurre et crème fraîche.', 'Yaourts, fromages, beurre et crème fraîche complètent la gamme laitière.', $derivesImage, false, 3);
        $this->product($manager, 'Biscuit Mini Maïs', 'biscuit-mini-mais', $biscuits, 'Biscuit sucré et croquant au maïs cultivé localement.', 'Le biscuit « Mini Maïs » est une gourmandise sucrée, croquante et savoureuse, fabriquée à partir de maïs cultivé localement. Il offre une expérience authentique et chaleureuse, évoquant la simplicité et la douceur du terroir, idéal pour une pause gourmande.', $miniMais, true, 4);
        $this->product($manager, 'Biscuit Mini Kansy', 'biscuit-mini-kansy', $biscuits, 'Biscuit à la pâte d’arachide locale.', 'Ce biscuit à la pâte d’arachide locale est une création savoureuse au goût authentique, croquante et riche en traditions. Il offre une expérience gustative unique qui met en valeur la diversité et la richesse des saveurs de la région.', $miniKansy, true, 5);
        $this->product($manager, 'Biscuit ABC', 'biscuit-abc', $biscuits, 'Biscuit en forme de lettres, peu sucré, pour les tout-petits.', 'Ce biscuit en forme de lettres de l’alphabet allie plaisir et apprentissage. Confectionné avec du lait, de la farine et des ingrédients de qualité, il est croquant, peu sucré et idéal pour les tout-petits. Un goûter à la fois ludique, sain et savoureux.', $biscuitAbc, true, 6);
        $this->product($manager, 'Biscuits vanille', 'biscuits-vanille', $biscuits, 'Biscuits sandwich à la crème vanille.', 'Quatre biscuits sandwich à la crème vanille, de la gamme Good Morning.', $vanilla, true, 7);
        $this->product($manager, 'Sucre raffiné', 'sucre-raffine', $sucre, 'Sucre blanc en poudre pour la cuisine de tous les jours.', 'Sucre blanc en poudre Good Morning, conditionné pour les usages culinaires, de la pâtisserie aux boissons.', $sugar, true, 8);

        $manager->persist((new Statistic())->setValue('À renseigner')->setLabel('Années d’expérience')->setDescription('Libellé présent sur le site actuel. Le chiffre n’est pas repris tant qu’il n’est pas confirmé.')->setPosition(1));
        $manager->persist((new Statistic())->setValue('À renseigner')->setLabel('Satisfaction client')->setDescription('Libellé présent sur le site actuel. Le pourcentage n’est pas repris tant qu’il n’est pas confirmé.')->setPosition(2));

        $company = 'Diaré Groupe Industrie est une société industrielle engagée dans la fabrication et la commercialisation de produits alimentaires tels que le lait, les biscuits, le sucre et d’autres produits de grande consommation.';
        $mission = 'Notre mission est d’offrir aux consommateurs des produits de qualité supérieure, accessibles et répondant aux normes les plus exigeantes du marché.';
        $vision = 'Notre vision est de devenir un acteur pionnier et incontournable du secteur agroalimentaire, contribuant activement au développement industriel et économique de notre région et au-delà.';
        $industrial = 'Diaré Groupe Industrie s’engage à fabriquer et commercialiser des produits tels que le lait, les biscuits et le sucre, dans le respect des normes les plus exigeantes. Notre mission est d’apporter goût, sécurité et confiance à nos consommateurs, tout en soutenant le développement industriel et économique de la Guinée.';
        $philosophy = 'Chez Diaré Groupe, notre philosophie repose sur l’excellence, l’innovation et l’intégrité. Nous nous engageons à créer une valeur durable pour nos clients et partenaires à travers une production responsable et une amélioration continue.';
        $quality = 'La qualité est au cœur de toutes nos actions. De la sélection des matières premières à la production finale, nous respectons les normes les plus strictes pour garantir la satisfaction et la confiance de nos clients.';

        $home = $this->page($manager, 'Accueil', 'accueil', 'Accueil', 'Diaré Groupe Industrie', 'Fabrication et commercialisation de lait, de biscuits, de sucre et d’autres produits de grande consommation.', true, 1);
        $hero = $this->section($home, SectionType::Hero, 1, 'Diaré Groupe Industrie', 'Du Lait 100% Naturel, pour un Avenir en Santé !', null, 'Découvrez nos produits laitiers frais et transformés, fabriqués avec soin pour garantir qualité, goût et nutrition.', $vanilla, null, 'Découvrir nos produits', '/nos-produits', 'Nous contacter', '/contact', SectionTheme::Dark);
        $this->slide($hero, $vanilla, 'Diaré Groupe Industrie', 'Du Lait 100% Naturel, pour un Avenir en Santé !', 'Découvrez nos produits laitiers frais et transformés, fabriqués avec soin pour garantir qualité, goût et nutrition.', 'Découvrir nos produits', '/nos-produits', HeroContentPosition::Start, HeroOverlay::Medium, 1, 'Nous contacter', '/contact');
        $this->slide($hero, $biscuitAbc, 'Biscuits', 'Biscuit ABC', 'Ce biscuit en forme de lettres de l’alphabet allie plaisir et apprentissage. Confectionné avec du lait, de la farine et des ingrédients de qualité.', 'Voir le produit', '/nos-produits/biscuit-abc', HeroContentPosition::End, HeroOverlay::Soft, 2);
        $this->slide($hero, $sugar, 'Sucre', 'Sucre raffiné', 'Sucre pur, adapté à tous vos usages culinaires, de la pâtisserie aux boissons.', 'Voir le produit', '/nos-produits/sucre-raffine', HeroContentPosition::Center, HeroOverlay::Strong, 3);
        $this->section($home, SectionType::TextImage, 2, 'Notre société', 'Une industrie alimentaire ancrée en Guinée', null, $company, $sugar, null, 'À propos', '/a-propos', null, null, SectionTheme::Default);
        $activities = $this->section($home, SectionType::Activities, 3, 'Nos activités', 'Des solutions agroalimentaires complètes, alliant qualité, innovation et authenticité.', null, null, null, null, null, null, null, null, SectionTheme::Light);
        $this->item($activities, 'Production et conditionnement', 'Production et conditionnement de lait et dérivés', 1);
        $this->item($activities, 'Fabrication', 'Fabrication de différentes gammes de biscuits', 2);
        $this->item($activities, 'Raffinage et distribution', 'Raffinage et distribution de sucre', 3);
        $this->item($activities, 'Nouvelles gammes', 'Développement de nouvelles gammes de produits alimentaires adaptés aux besoins du marché', 4);
        $this->section($home, SectionType::Statistics, 4, 'Chiffres clés', 'Des indicateurs à confirmer', null, 'Les chiffres du site actuel ne sont pas repris tant qu’ils ne sont pas confirmés. Complétez-les depuis le back-office.', null, null, null, null, null, null, SectionTheme::Dark);
        $this->section($home, SectionType::TextImage, 5, 'Présentation industrielle', 'Offrir à chaque foyer des produits alimentaires de qualité', 'Savoir-faire local', $industrial, $biscuitAbc, $vanilla, null, null, null, null, SectionTheme::ImageLeft);
        $this->section($home, SectionType::Mission, 6, 'Mission', 'Offrir aux consommateurs des produits de qualité', null, $mission, null, $sugar, null, null, null, null, SectionTheme::Default);
        $this->section($home, SectionType::Vision, 7, 'Vision', 'Devenir un acteur de référence', null, $vision.' Notre vision est de devenir un acteur de référence dans le secteur agroalimentaire national et régional, reconnu pour son innovation, sa qualité et son impact positif sur la communauté.', null, $vanilla, null, null, null, null, SectionTheme::Light);
        $values = $this->section($home, SectionType::Values, 8, 'Philosophie', 'Excellence, innovation et intégrité', null, $philosophy, null, null, null, null, null, null, SectionTheme::Default);
        $this->item($values, 'Innovation et amélioration continue', 'Nous cherchons sans cesse à améliorer nos produits et nos procédés, en alliant technologie moderne et créativité afin de répondre aux besoins changeants des consommateurs.', 1);
        $this->item($values, 'Engagement envers la qualité', $quality, 2);
        $this->item($values, 'Service client exceptionnel', 'Nos clients sont notre priorité. Nous leur offrons un service personnalisé, réactif et fiable, afin de bâtir des relations durables fondées sur la confiance et l’excellence.', 3);
        $this->section($home, SectionType::Products, 9, 'Produits phares', 'Lait, biscuits et sucre', 'Catalogue', null, null, null, 'Tous les produits', '/nos-produits', null, null, SectionTheme::Default);
        $distribution = $this->section($home, SectionType::Distribution, 10, 'Distribution', 'Un réseau au service des foyers et des distributeurs', 'Réseau', 'Un réseau logistique efficace pour servir particuliers, supermarchés et distributeurs. Présence indiquée : Dubréka, Carrefour Kaléma.', null, $biscuitAbc, 'La distribution', '/distribution', null, null, SectionTheme::Light);
        $this->item($distribution, 'Particuliers', 'Un réseau logistique pour servir les particuliers.', 1);
        $this->item($distribution, 'Supermarchés', 'Un réseau logistique pour servir les supermarchés.', 2);
        $this->item($distribution, 'Distributeurs', 'Un réseau logistique pour servir les distributeurs.', 3);
        $this->section($home, SectionType::Quality, 11, 'Engagement qualité', 'Nous garantissons des produits sains, savoureux et conformes aux normes les plus strictes.', null, $quality, null, $sugar, null, null, null, null, SectionTheme::Default);
        $this->section($home, SectionType::News, 12, 'Actualités', 'La vie du groupe', null, null, null, null, 'Toutes les actualités', '/actualites', null, null, SectionTheme::Light);
        $this->section($home, SectionType::Partners, 13, 'Partenaires', 'Ils nous accompagnent', null, null, null, null, null, null, null, null, SectionTheme::Default);
        $this->section($home, SectionType::Cta, 14, 'Contact', 'Échanger avec Diaré Groupe Industrie', null, 'Pour une demande commerciale ou une information, écrivez-nous.', null, $vanilla, 'Nous contacter', '/contact', null, null, SectionTheme::Dark);

        $about = $this->page($manager, 'À propos', 'a-propos', 'À propos', 'À propos — Diaré Groupe Industrie', $company, true, 2);
        $this->section($about, SectionType::TextImage, 1, 'Diaré Groupe Industrie', 'Présentation de la société', null, $company, $logo, null, null, null, null, null, SectionTheme::Default);
        $this->section($about, SectionType::Mission, 2, 'Mission', 'Produits de qualité, accessibles et exigeants', null, $mission, null, $sugar, null, null, null, null, SectionTheme::Dark);
        $this->section($about, SectionType::Vision, 3, 'Vision', 'Un acteur pionnier de l’agroalimentaire', null, $vision, null, $vanilla, null, null, null, null, SectionTheme::Light);
        $aboutValues = $this->section($about, SectionType::Values, 4, 'Philosophie', 'Excellence, innovation et intégrité', null, $philosophy, null, null, null, null, null, null, SectionTheme::Default);
        $this->item($aboutValues, 'Innovation et amélioration continue', 'Nous cherchons sans cesse à améliorer nos produits et nos procédés, en alliant technologie moderne et créativité afin de répondre aux besoins changeants des consommateurs.', 1);
        $this->item($aboutValues, 'Engagement envers la qualité', $quality, 2);
        $this->item($aboutValues, 'Service client exceptionnel', 'Nos clients sont notre priorité. Nous leur offrons un service personnalisé, réactif et fiable, afin de bâtir des relations durables fondées sur la confiance et l’excellence.', 3);

        $activityPage = $this->page($manager, 'Nos activités', 'nos-activites', 'Nos activités', 'Nos activités — Diaré Groupe Industrie', 'Production de lait et dérivés, fabrication de biscuits, raffinage et distribution de sucre.', true, 3);
        $activityItems = $this->section($activityPage, SectionType::Activities, 1, 'Nos activités', 'Lait, biscuits et sucre', null, $company, null, null, null, null, null, null, SectionTheme::Default);
        $this->item($activityItems, 'Production et conditionnement', 'Production et conditionnement de lait et dérivés', 1);
        $this->item($activityItems, 'Fabrication', 'Fabrication de différentes gammes de biscuits', 2);
        $this->item($activityItems, 'Raffinage et distribution', 'Raffinage et distribution de sucre', 3);
        $this->item($activityItems, 'Nouvelles gammes', 'Développement de nouvelles gammes de produits alimentaires adaptés aux besoins du marché', 4);
        $this->section($activityPage, SectionType::TextImage, 2, 'Savoir-faire', 'Des produits de grande consommation', null, $industrial, $biscuitAbc, $sugar, 'Voir les produits', '/nos-produits', null, null, SectionTheme::ImageLeft);

        $this->page($manager, 'Nos produits', 'nos-produits', 'Nos produits', 'Nos produits — Diaré Groupe Industrie', 'Lait et dérivés, biscuits et sucre raffiné.', true, 4);

        $distributionPage = $this->page($manager, 'Distribution', 'distribution', 'Distribution', 'Distribution — Diaré Groupe Industrie', 'Un réseau logistique pour les particuliers, les supermarchés et les distributeurs.', true, 5);
        $distributionPageSection = $this->section($distributionPage, SectionType::Distribution, 1, 'Distribution', 'Particuliers, supermarchés et distributeurs', 'Dubréka', 'Un réseau logistique efficace pour servir particuliers, supermarchés et distributeurs. Adresse indiquée : Dubréka, Carrefour Kaléma.', null, $biscuitAbc, 'Nous contacter', '/contact', null, null, SectionTheme::Default);
        $this->item($distributionPageSection, 'Particuliers', 'Un réseau logistique pour servir les particuliers.', 1);
        $this->item($distributionPageSection, 'Supermarchés', 'Un réseau logistique pour servir les supermarchés.', 2);
        $this->item($distributionPageSection, 'Distributeurs', 'Un réseau logistique pour servir les distributeurs.', 3);

        $this->page($manager, 'Actualités', 'actualites', 'Actualités', 'Actualités — Diaré Groupe Industrie', 'Les actualités de Diaré Groupe Industrie.', true, 6);

        $this->page($manager, 'Contact', 'contact', 'Contact', 'Contact — Diaré Groupe Industrie', 'Contacter Diaré Groupe Industrie à Dubréka, Carrefour Kaléma.', true, 7);
        $this->page($manager, 'Rejoignez DGI', 'nous-rejoindre', 'Nous rejoindre', 'Rejoignez DGI — Diaré Groupe Industrie', 'Les offres d’emploi de Diaré Groupe Industrie.', true, 8);

        $legal = $this->page($manager, 'Mentions légales', 'mentions-legales', null, 'Mentions légales', 'À renseigner depuis le back-office.', true, 20);
        $this->section($legal, SectionType::Custom, 1, null, 'Mentions légales', null, 'À renseigner depuis le back-office.', null, null, null, null, null, null, SectionTheme::Default);
        $legal->setShowInMenu(false);

        $privacy = $this->page($manager, 'Politique de confidentialité', 'politique-de-confidentialite', null, 'Politique de confidentialité', 'À renseigner depuis le back-office.', true, 21);
        $this->section($privacy, SectionType::Custom, 1, null, 'Politique de confidentialité', null, 'À renseigner depuis le back-office.', null, null, null, null, null, null, SectionTheme::Default);
        $privacy->setShowInMenu(false);

        $manager->flush();
        $this->editorialDemoSeeder->seed($manager);
    }

    private function clearUploadedMedia(): void
    {
        if (!is_dir($this->mediaUploadDir)) {
            return;
        }

        $this->deleteUploadedFiles($this->mediaUploadDir);
        $this->deleteUploadedFiles($this->mediaUploadDir.'/thumbnails');
    }

    private function deleteUploadedFiles(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                continue;
            }
            $path = $directory.'/'.$file;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function importMedia(ObjectManager $manager, string $file, string $alt, string $title): Media
    {
        $media = $this->mediaUploader->upload($this->fixtureMediaDir.'/'.$file, $alt, $title);
        $manager->persist($media);

        return $media;
    }

    private function category(ObjectManager $manager, string $name, string $slug, string $description, int $position, CategoryIcon $icon, CategoryAccent $accent): ProductCategory
    {
        $category = (new ProductCategory())
            ->setName($name)
            ->setSlug($slug)
            ->setDescription($description)
            ->setIcon($icon)
            ->setAccentColor($accent)
            ->setPosition($position)
            ->setIsPublished(true);
        $manager->persist($category);

        return $category;
    }

    private function product(ObjectManager $manager, string $name, string $slug, ProductCategory $category, string $short, string $description, ?Media $image, bool $featured, int $position): void
    {
        $product = (new Product())
            ->setName($name)
            ->setSlug($slug)
            ->setCategory($category)
            ->setShortDescription($short)
            ->setDescription($description)
            ->setMainImage($image)
            ->setIsFeatured($featured)
            ->setIsPublished(true)
            ->setPosition($position)
            ->setMetaTitle($name.' — Diaré Groupe Industrie')
            ->setMetaDescription($short);
        $manager->persist($product);
    }

    private function page(ObjectManager $manager, string $title, string $slug, ?string $menuTitle, string $metaTitle, string $metaDescription, bool $inMenu, int $position): Page
    {
        $page = (new Page())
            ->setTitle($title)
            ->setSlug($slug)
            ->setMenuTitle($menuTitle)
            ->setMetaTitle($metaTitle)
            ->setMetaDescription($metaDescription)
            ->setIsPublished(true)
            ->setShowInMenu($inMenu)
            ->setMenuPosition($position);
        $manager->persist($page);

        return $page;
    }

    private function section(
        Page $page,
        SectionType $type,
        int $position,
        ?string $eyebrow,
        ?string $title,
        ?string $subtitle,
        ?string $content,
        ?Media $image,
        ?Media $background,
        ?string $buttonLabel,
        ?string $buttonUrl,
        ?string $secondaryLabel,
        ?string $secondaryUrl,
        SectionTheme $theme,
    ): Section {
        $section = (new Section())
            ->setPage($page)
            ->setType($type)
            ->setPosition($position)
            ->setIsVisible(true)
            ->setEyebrow($eyebrow)
            ->setTitle($title)
            ->setSubtitle($subtitle)
            ->setContent($content)
            ->setImage($image)
            ->setBackgroundImage($background)
            ->setButtonLabel($buttonLabel)
            ->setButtonUrl($buttonUrl)
            ->setSecondaryButtonLabel($secondaryLabel)
            ->setSecondaryButtonUrl($secondaryUrl)
            ->setTheme($theme);
        $page->addSection($section);

        return $section;
    }

    private function slide(
        Section $section,
        Media $image,
        string $eyebrow,
        string $title,
        string $description,
        string $buttonLabel,
        string $buttonUrl,
        HeroContentPosition $position,
        HeroOverlay $overlay,
        int $order,
        ?string $secondaryLabel = null,
        ?string $secondaryUrl = null,
    ): void {
        $slide = (new HeroSlide())
            ->setImage($image)
            ->setEyebrow($eyebrow)
            ->setTitle($title)
            ->setDescription($description)
            ->setButtonLabel($buttonLabel)
            ->setButtonUrl($buttonUrl)
            ->setSecondaryButtonLabel($secondaryLabel)
            ->setSecondaryButtonUrl($secondaryUrl)
            ->setContentPosition($position)
            ->setOverlay($overlay)
            ->setPosition($order)
            ->setIsActive(true);
        $section->addSlide($slide);
    }

    private function item(Section $section, string $title, string $text, int $position): void
    {
        $item = (new SectionItem())
            ->setTitle($title)
            ->setText($text)
            ->setPosition($position);
        $section->addItem($item);
    }
}
