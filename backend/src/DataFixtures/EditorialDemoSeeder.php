<?php

namespace App\DataFixtures;

use App\Entity\JobOffer;
use App\Entity\Media;
use App\Entity\News;
use App\Entity\Page;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Section;
use App\Enum\CategoryAccent;
use App\Enum\ContractType;
use App\Enum\SectionTheme;
use App\Enum\SectionType;
use App\Service\MediaUploader;
use Doctrine\Persistence\ObjectManager;

/**
 * Complète un catalogue déjà installé sans effacer les demandes de contact.
 * Les ajouts sont ignorés si le slug existe déjà.
 */
final class EditorialDemoSeeder
{
    public function __construct(
        private readonly MediaUploader $mediaUploader,
        private readonly string $fixtureMediaDir,
    ) {
    }

    public function seed(ObjectManager $manager): void
    {
        $this->refreshCategoryAccents($manager);
        $this->refreshPlaceholderProducts($manager);
        $this->ensureChocolateProduct($manager);
        $this->ensureNews($manager);
        $this->ensureJobs($manager);
        $this->tightenSparsePages($manager);
        $this->assignDefaultPageBanners($manager);
        $manager->flush();
    }

    private function refreshCategoryAccents(ObjectManager $manager): void
    {
        $categories = $manager->getRepository(ProductCategory::class);
        $biscuits = $categories->findOneBy(['slug' => 'biscuits']);
        if ($biscuits instanceof ProductCategory && $biscuits->getAccentColor() === CategoryAccent::Lime) {
            $biscuits->setAccentColor(CategoryAccent::Biscuit);
        }

        $sugar = $categories->findOneBy(['slug' => 'sucre']);
        if ($sugar instanceof ProductCategory && $sugar->getAccentColor() === CategoryAccent::Deep) {
            $sugar->setAccentColor(CategoryAccent::Rose);
        }
    }

    private function refreshPlaceholderProducts(ObjectManager $manager): void
    {
        $copy = [
            'produits-laitiers-frais' => [
                'Lait pasteurisé, lait UHT et lait en poudre.',
                'Lait pasteurisé, lait UHT et lait en poudre, pour les foyers, les commerces et les distributeurs.',
            ],
            'produits-derives' => [
                'Yaourts, fromages, beurre et crème fraîche.',
                'Yaourts, fromages, beurre et crème fraîche complètent la gamme laitière.',
            ],
            'biscuits-vanille' => [
                'Biscuits sandwich à la crème vanille.',
                'Quatre biscuits sandwich à la crème vanille, de la gamme Good Morning.',
            ],
            'sucre-raffine' => [
                'Sucre blanc en poudre pour la cuisine de tous les jours.',
                'Sucre blanc en poudre Good Morning, conditionné pour les usages culinaires, de la pâtisserie aux boissons.',
            ],
        ];

        foreach ($copy as $slug => [$short, $description]) {
            $product = $manager->getRepository(Product::class)->findOneBy(['slug' => $slug]);
            if (!($product instanceof Product) || (!$this->isPlaceholder($product->getDescription()) && !$this->isPlaceholder($product->getShortDescription()))) {
                continue;
            }

            $product->setShortDescription($short)->setDescription($description);
        }
    }

    private function ensureChocolateProduct(ObjectManager $manager): void
    {
        if ($manager->getRepository(Product::class)->findOneBy(['slug' => 'biscuits-chocolat']) instanceof Product) {
            return;
        }

        $category = $manager->getRepository(ProductCategory::class)->findOneBy(['slug' => 'biscuits']);
        if (!($category instanceof ProductCategory)) {
            return;
        }

        $image = $this->mediaByTitle($manager, 'Biscuits chocolat');
        if (!($image instanceof Media)) {
            $source = $this->fixtureMediaDir.'/biscuits-vanille.png';
            if (!is_file($source)) {
                $image = $this->mediaByTitle($manager, 'Biscuits vanille');
            } else {
                $image = $this->mediaUploader->upload($source, 'Biscuits chocolat Good Morning', 'Biscuits chocolat');
                $manager->persist($image);
            }
        }

        $product = (new Product())
            ->setName('Biscuits chocolat')
            ->setSlug('biscuits-chocolat')
            ->setCategory($category)
            ->setShortDescription('Biscuits sandwich à la crème chocolat.')
            ->setDescription('Quatre biscuits sandwich à la crème chocolat, de la gamme Good Morning. Le visuel réunit les formats vanille et chocolat de la même famille.')
            ->setMainImage($image instanceof Media ? $image : null)
            ->setIsFeatured(true)
            ->setIsPublished(true)
            ->setPosition(8)
            ->setMetaTitle('Biscuits chocolat — Diaré Groupe Industrie')
            ->setMetaDescription('Biscuits sandwich à la crème chocolat, de la gamme Good Morning.');
        $manager->persist($product);
    }

    private function ensureNews(ObjectManager $manager): void
    {
        $vanilla = $this->mediaByTitle($manager, 'Biscuits vanille');
        $sugar = $this->mediaByTitle($manager, 'Sucre raffiné');
        $biscuit = $this->mediaByTitle($manager, 'Biscuit ABC');

        $articles = [
            ['Good Morning : les sandwichs vanille et chocolat', 'sandwichs-vanille-et-chocolat', 'Deux biscuits sandwich à la crème, vanille et chocolat, sont proposés dans la gamme Good Morning.', 'Les formats sandwich vanille et chocolat appartiennent à la même famille Good Morning. Chaque paquet présente quatre biscuits à la crème, pour une pause gourmande au rayon biscuits.', $vanilla, '2026-09-18'],
            ['Le sucre blanc en poudre Good Morning', 'sucre-blanc-en-poudre', 'Le sucre blanc en poudre est conditionné pour les usages culinaires du quotidien.', 'Le sucre blanc en poudre Good Morning accompagne la pâtisserie et les boissons. Il est présenté dans un emballage pensé pour préserver la qualité du produit jusqu’au foyer ou au commerce.', $sugar, '2026-08-22'],
            ['Biscuit ABC, un goûter en lettres', 'biscuit-abc-un-gouter-en-lettres', 'Le biscuit en forme de lettres reste un goûter peu sucré, pensé pour les tout-petits.', 'Le biscuit ABC reprend les lettres de l’alphabet. Confectionné avec du lait, de la farine et des ingrédients de qualité, il est croquant et peu sucré. Le goûter reste à la fois ludique et simple.', $biscuit, '2026-07-04'],
            ['Le réseau de distribution s’appuie sur Dubréka', 'reseau-de-distribution-a-dubreka', 'Particuliers, supermarchés et distributeurs sont servis depuis Dubréka, Carrefour Kaléma.', 'Diaré Groupe Industrie s’appuie sur un réseau logistique pour servir les particuliers, les supermarchés et les distributeurs. La présence indiquée du groupe est à Dubréka, Carrefour Kaléma.', $sugar, '2026-06-15'],
            ['La qualité au cœur des gammes alimentaires', 'qualite-des-gammes-alimentaires', 'De la matière première au produit fini, la qualité reste le repère des gammes DGI.', 'La qualité guide la fabrication et la commercialisation du lait, des biscuits et du sucre. DGI rappelle une exigence simple : des produits sains, savoureux et conformes aux normes qu’elle s’impose, sans chiffre ni certification tant qu’ils ne sont pas confirmés.', $biscuit, '2026-05-09'],
            ['DGI publie ses offres à Dubréka', 'offres-emploi-a-dubreka', 'Production, qualité, maintenance, distribution et logistique : les postes ouverts sont sur le site.', 'Les métiers industriels et commerciaux du groupe sont proposés à Dubréka. Les fiches précisent le lieu, le type de contrat et le profil recherché. La candidature se fait depuis la page de contact.', $biscuit, '2026-04-12'],
        ];

        foreach ($articles as [$title, $slug, $excerpt, $content, $image, $date]) {
            if ($manager->getRepository(News::class)->findOneBy(['slug' => $slug]) instanceof News) {
                continue;
            }

            $news = (new News())
                ->setTitle($title)
                ->setSlug($slug)
                ->setExcerpt($excerpt)
                ->setContent($content)
                ->setImage($image instanceof Media ? $image : null)
                ->setPublishedAt(new \DateTimeImmutable($date))
                ->setIsPublished(true)
                ->setMetaTitle($title.' — Diaré Groupe Industrie')
                ->setMetaDescription($excerpt);
            $manager->persist($news);
        }
    }

    private function ensureJobs(ObjectManager $manager): void
    {
        $jobs = [
            ['Commercial terrain', 'commercial-terrain', 'Commercial', ContractType::Cdi, 'Expérience terrain', 'Développer la présence des gammes DGI auprès des commerces et des distributeurs.', 'Vous présentez les biscuits, le lait et le sucre aux points de vente, vous suivez les commandes et vous remontez les besoins du terrain.', 'Vous aimez le contact client et vous pouvez vous déplacer à partir de Dubréka. Une première expérience de vente est un plus.'],
            ['Responsable distribution', 'responsable-distribution', 'Distribution', ContractType::Cdi, 'Expérience confirmée', 'Organiser la distribution vers les particuliers, les supermarchés et les distributeurs.', 'Vous coordonnez les livraisons et la relation avec le réseau, depuis le site de Dubréka, Carrefour Kaléma.', 'Vous savez planifier des tournées, suivre des stocks et dialoguer avec des partenaires commerciaux.'],
            ['Technicien de maintenance', 'technicien-de-maintenance', 'Maintenance', ContractType::Cdi, 'Expérience technique', 'Assurer le suivi des équipements de production alimentaire.', 'Vous intervenez sur le parc machines du site pour limiter les arrêts et garder des conditions de fabrication régulières.', 'Vous avez une pratique de la maintenance industrielle et le sens des priorités en production.'],
            ['Agent de production', 'agent-de-production', 'Production', ContractType::Cdd, 'Débutant accepté', 'Participer à la fabrication et au conditionnement des gammes alimentaires.', 'Vous travaillez sur une ligne de production, dans le respect des consignes d’hygiène et de qualité du site.', 'Vous êtes à l’aise avec un travail d’équipe et des horaires de production.'],
            ['Responsable qualité', 'responsable-qualite', 'Qualité', ContractType::Cdi, 'Expérience confirmée', 'Suivre la conformité des produits, du lait aux biscuits et au sucre.', 'Vous contrôlez les étapes de fabrication et vous formalisez les écarts, sans inventer de certification qui ne serait pas encore confirmée.', 'Vous connaissez les exigences d’un site agroalimentaire et vous savez expliquer un contrôle à une équipe.'],
            ['Chauffeur-livreur', 'chauffeur-livreur', 'Logistique', ContractType::Cdi, 'Permis adapté', 'Livrer les commandes du réseau à partir de Dubréka.', 'Vous assurez les tournées vers les commerces et les distributeurs, avec un suivi simple des bons de livraison.', 'Vous conduisez régulièrement et vous soignez la relation au moment de la livraison.'],
            ['Assistant logistique', 'assistant-logistique', 'Logistique', ContractType::Cdd, 'Première expérience', 'Appuyer la préparation des commandes et le suivi des expéditions.', 'Vous préparez les sorties de marchandises, vous vérifiez les quantités et vous tenez à jour les mouvements du jour.', 'Vous êtes organisé et à l’aise avec un suivi écrit des commandes.'],
        ];

        foreach ($jobs as [$title, $slug, $department, $contract, $experience, $short, $description, $requirements]) {
            if ($manager->getRepository(JobOffer::class)->findOneBy(['slug' => $slug]) instanceof JobOffer) {
                continue;
            }

            $job = (new JobOffer())
                ->setTitle($title)
                ->setSlug($slug)
                ->setDepartment($department)
                ->setLocation('Dubréka')
                ->setContractType($contract)
                ->setExperienceLevel($experience)
                ->setShortDescription($short)
                ->setDescription($description)
                ->setRequirements($requirements)
                ->setPublishedAt(new \DateTimeImmutable('2026-08-01'))
                ->setExpiresAt(new \DateTimeImmutable('2027-06-30'))
                ->setIsPublished(true);
            $manager->persist($job);
        }
    }

    private function tightenSparsePages(ObjectManager $manager): void
    {
        $pages = $manager->getRepository(Page::class);
        $products = $pages->findOneBy(['slug' => 'nos-produits']);
        if ($products instanceof Page) {
            foreach ($products->getSections() as $section) {
                if ($section->getType() === SectionType::TextImage && str_contains((string) $section->getContent(), 'fabrique et commercialise notamment')) {
                    $section->setIsVisible(false);
                }
            }
        }

        $news = $pages->findOneBy(['slug' => 'actualites']);
        if ($news instanceof Page) {
            foreach ($news->getSections() as $section) {
                if (str_contains((string) $section->getContent(), 'Aucun article')) {
                    $section->setIsVisible(false);
                }
            }
        }

        $distribution = $pages->findOneBy(['slug' => 'distribution']);
        if (!($distribution instanceof Page)) {
            return;
        }

        foreach ($distribution->getSections() as $section) {
            if ($section->getType() !== SectionType::Distribution) {
                continue;
            }

            if ($section->getTheme() === SectionTheme::Default) {
                $section->setTheme(SectionTheme::Light);
            }

            // Le paquet sert de visuel dans la section suivante, pas de fond derrière le texte.
            $section->setBackgroundImage(null);
        }

        if ($this->pageHasTitle($distribution, 'Une présence à Dubréka')) {
            return;
        }

        $image = $this->mediaByTitle($manager, 'Biscuit ABC');
        $presence = (new Section())
            ->setType(SectionType::TextImage)
            ->setPosition(2)
            ->setIsVisible(true)
            ->setEyebrow('Réseau')
            ->setTitle('Une présence à Dubréka')
            ->setContent('Le groupe sert les particuliers, les supermarchés et les distributeurs depuis Dubréka, Carrefour Kaléma. Le réseau relie la fabrication aux points de vente, sans étape inventée au-delà de cette présence.')
            ->setImage($image instanceof Media ? $image : null)
            ->setButtonLabel('Nous contacter')
            ->setButtonUrl('/contact')
            ->setTheme(SectionTheme::ImageLeft);
        $distribution->addSection($presence);

        $cta = (new Section())
            ->setType(SectionType::Cta)
            ->setPosition(3)
            ->setIsVisible(true)
            ->setEyebrow('Distribution')
            ->setTitle('Référencer les gammes DGI')
            ->setContent('Pour une demande de distribution ou une information commerciale, écrivez à Diaré Groupe Industrie.')
            ->setButtonLabel('Nous contacter')
            ->setButtonUrl('/contact')
            ->setTheme(SectionTheme::Dark);
        $distribution->addSection($cta);
    }

    /**
     * Photos Unsplash (licence libre, usage commercial) : lignes agroalimentaires,
     * fabrication, logistique. Elles ne remplacent pas une bannière déjà choisie.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const DEFAULT_BANNERS = [
        'a-propos' => ['banners/a-propos.jpg', 'Équipe sur une ligne de production agroalimentaire', 'Bannière — À propos'],
        'nos-activites' => ['banners/nos-activites.jpg', 'Ligne industrielle de conditionnement alimentaire', 'Bannière — Nos activités'],
        'nos-produits' => ['banners/nos-produits.jpg', 'Produits de boulangerie en cours de fabrication', 'Bannière — Nos produits'],
        'distribution' => ['banners/distribution.jpg', 'Poids lourd de livraison', 'Bannière — Distribution'],
        'actualites' => ['banners/actualites.jpg', 'Équipe au travail dans une usine agroalimentaire', 'Bannière — Actualités'],
        'contact' => ['banners/contact.jpg', 'Entrepôt logistique', 'Bannière — Contact'],
        'nous-rejoindre' => ['banners/nous-rejoindre.jpg', 'Contrôle de produits alimentaires', 'Bannière — Nous rejoindre'],
    ];

    public function assignDefaultPageBanners(ObjectManager $manager): void
    {
        foreach (self::DEFAULT_BANNERS as $slug => [$file, $alt, $title]) {
            $page = $manager->getRepository(Page::class)->findOneBy(['slug' => $slug]);
            if (!$page instanceof Page || $page->getBannerImage() instanceof Media) {
                continue;
            }

            $media = $this->mediaByTitle($manager, $title);
            if (!$media instanceof Media) {
                $path = $this->fixtureMediaDir.'/'.$file;
                if (!is_file($path)) {
                    continue;
                }

                $media = $this->mediaUploader->upload($path, $alt, $title);
                $manager->persist($media);
            }

            $page->setBannerImage($media);
        }

        $this->detachAboutPresentationLogo($manager);
    }

    private function detachAboutPresentationLogo(ObjectManager $manager): void
    {
        $about = $manager->getRepository(Page::class)->findOneBy(['slug' => 'a-propos']);
        if (!$about instanceof Page) {
            return;
        }

        foreach ($about->getSections() as $section) {
            if ($section->getType() !== SectionType::TextImage || $section->getTitle() !== 'Présentation de la société') {
                continue;
            }

            $image = $section->getImage();
            if (!$image instanceof Media) {
                continue;
            }

            $label = mb_strtolower(trim($image->getTitle().' '.$image->getAlt()));
            if (str_contains($label, 'logo')) {
                $section->setImage(null);
            }
        }
    }

    private function pageHasTitle(Page $page, string $title): bool
    {
        foreach ($page->getSections() as $section) {
            if ($section->getTitle() === $title) {
                return true;
            }
        }

        return false;
    }

    private function mediaByTitle(ObjectManager $manager, string $title): ?Media
    {
        $media = $manager->getRepository(Media::class)->findOneBy(['title' => $title]);

        return $media instanceof Media ? $media : null;
    }

    private function isPlaceholder(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $text = mb_strtolower($value);

        return str_contains($text, 'renseigner') || str_contains($text, 'visuel présent');
    }
}
