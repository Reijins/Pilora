<?php
declare(strict_types=1);

namespace Modules\Marketing\Services;

use Core\Config;
use Modules\Marketing\Helpers\MarketingUi;

final class MarketingSeoService
{
    public function appUrl(): string
    {
        return rtrim((string) (Config::env('APP_URL', '') ?? ''), '/');
    }

    public function canonical(string $path): string
    {
        $base = $this->appUrl();
        $path = '/' . ltrim($path, '/');
        if ($path === '/') {
            return $base !== '' ? $base . '/' : '/';
        }

        return $base !== '' ? $base . $path : $path;
    }

    /**
     * @return list<array{slug:string, title:string, description:string, teaser:string}>
     */
    public function featurePages(): array
    {
        $out = [];
        foreach ($this->featureCatalog() as $f) {
            $out[] = [
                'slug' => $f['slug'],
                'title' => $f['title'],
                'description' => $f['metaDescription'],
                'teaser' => $f['teaser'],
                'icon' => (string) ($f['icon'] ?? MarketingUi::featureIcon($f['slug'])),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function featureBySlug(string $slug): ?array
    {
        foreach ($this->featureCatalog() as $f) {
            if ($f['slug'] === $slug) {
                return $f;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function featureCatalog(): array
    {
        return [
            $this->buildFeature(
                slug: 'devis',
                title: 'Devis BTP',
                metaDescription: 'Logiciel de devis BTP : création rapide, envoi PDF, relances clients et suivi commercial. Pilora pour artisans et PME du bâtiment.',
                teaser: 'Créez et envoyez vos devis en quelques clics, avec relances et lien en ligne.',
                h1: 'Devis BTP : du chiffrage à la signature, sans ressaisie',
                intro: 'Avec Pilora, vos devis sont reliés à vos clients et à vos affaires. Vous gagnez du temps sur la rédaction, l\'envoi et le suivi, tout en gardant une vision claire des propositions en cours et des relances à faire.',
                benefits: [
                    'Bibliothèque de prix et lignes réutilisables pour chiffrer plus vite',
                    'PDF professionnel et envoi par email avec lien de consultation',
                    'Relances devis et tableau de bord commercial',
                    'Transformation en chantier ou facture sans ressaisir les données',
                ],
                forWho: 'Artisans, PME du second œuvre, entreprises générales et bureaux d\'études qui éditent plusieurs devis par semaine.',
                related: ['factures', 'chantiers', 'rentabilite'],
            ),
            $this->buildFeature(
                slug: 'factures',
                title: 'Facturation BTP',
                metaDescription: 'Facturation BTP en ligne : factures, paiements Stripe, relances et export comptable. Solution Pilora pour entreprises du bâtiment.',
                teaser: 'Factures, encaissements et exports comptables dans le même outil que vos devis.',
                h1: 'Facturation BTP : encaissez plus vite, restez conforme',
                intro: 'Pilora relie vos factures aux devis et aux chantiers. Vous émettez, envoyez et suivez les paiements depuis un espace unique, avec des modèles d\'email adaptés à vos clients professionnels.',
                benefits: [
                    'Factures issues des devis ou saisies directement, avec TVA et comptes configurables',
                    'Paiement en ligne sécurisé (Stripe) et accusés de réception automatiques',
                    'Suivi des impayés et export CSV pour votre expert-comptable',
                    'PDF aux couleurs de votre entreprise',
                ],
                forWho: 'Entreprises qui veulent réduire les délais de paiement et centraliser la facturation terrain / bureau.',
                related: ['devis', 'chantiers', 'rentabilite'],
            ),
            $this->buildFeature(
                slug: 'chantiers',
                title: 'Gestion de chantiers',
                metaDescription: 'Suivi de chantier BTP : planning équipes, photos, comptes rendus et affectations. Pilora pour conducteurs de travaux et chefs d\'entreprise.',
                teaser: 'Pilotez chaque chantier : équipe, documents terrain et avancement.',
                h1: 'Chantiers & projets : le terrain et le bureau synchronisés',
                intro: 'Chaque affaire devient un espace de travail partagé : adresse, équipe affectée, photos de progression et comptes rendus. Les conducteurs de travaux et les dirigeants voient la même information, à jour.',
                benefits: [
                    'Fiches chantier avec client, adresse et statut d\'avancement',
                    'Affectation des salariés et visibilité par profil',
                    'Photos et rapports de chantier horodatés',
                    'Lien direct avec devis et facturation de l\'affaire',
                ],
                forWho: 'Entreprises avec plusieurs chantiers simultanés et besoin de traçabilité terrain.',
                related: ['planning', 'rentabilite', 'devis'],
            ),
            $this->buildFeature(
                slug: 'planning',
                title: 'Planning chantiers',
                metaDescription: 'Planning BTP : affectation des équipes, tâches et charge par chantier. Pilora pour optimiser l\'organisation de vos interventions.',
                teaser: 'Organisez vos équipes et vos tâches sur le calendrier des affaires.',
                h1: 'Planning : visualisez la charge de vos équipes',
                intro: 'Le planning Pilora vous aide à répartir les ressources sur vos chantiers et à anticiper les conflits de disponibilité. Les informations sont liées aux projets et aux utilisateurs de votre espace.',
                benefits: [
                    'Vue planning par période et par chantier',
                    'Tâches et interventions associées aux affaires',
                    'Coordination avec les affectations projet',
                    'Moins d\'allers-retours entre le bureau et le terrain',
                ],
                forWho: 'Chefs d\'entreprise et conducteurs qui planifient plusieurs équipes en parallèle.',
                related: ['chantiers', 'rh', 'rentabilite'],
            ),
            $this->buildFeature(
                slug: 'rentabilite',
                title: 'Rentabilité chantiers',
                metaDescription: 'Rentabilité chantier BTP : marge, coûts et suivi financier par affaire. Tableaux de bord Pilora pour dirigeants et conducteurs.',
                teaser: 'Mesurez la marge réelle de chaque chantier avant la fin du travaux.',
                h1: 'Rentabilité : pilotez vos marges chantier par chantier',
                intro: 'Pilora agrège les données de vos affaires pour mettre en avant ce qui est rentable — et ce qui nécessite un arbitrage. Vous identifiez les chantiers à risque avant qu\'ils ne consomment toute la marge.',
                benefits: [
                    'Indicateurs par chantier : facturé, payé, coûts saisis',
                    'Vue des affaires à renseigner et historique',
                    'Aide à la décision pour les dirigeants et la direction',
                    'Croisement avec le suivi commercial (devis) et la production',
                ],
                forWho: 'Dirigeants et responsables financiers qui veulent une vision terrain + comptable simplifiée.',
                related: ['factures', 'chantiers', 'devis'],
            ),
            $this->buildFeature(
                slug: 'rh',
                title: 'Congés & RH',
                metaDescription: 'Gestion des congés BTP : demandes, validation et visibilité équipe dans Pilora. Module RH léger pour PME du bâtiment.',
                teaser: 'Demandes de congés et validation en ligne, intégrées à votre organisation.',
                h1: 'Congés & RH : un module simple pour vos équipes',
                intro: 'Les salariés déposent leurs demandes de congés ; les responsables valident en quelques clics. Le tout reste dans le même outil que le planning et les chantiers, sans multiplier les logiciels.',
                benefits: [
                    'Workflow demande / approbation configurable par rôle',
                    'Visibilité pour les managers sur les absences à venir',
                    'Cohérence avec les profils utilisateurs Pilora',
                    'Moins de paperasse et d\'emails dispersés',
                ],
                forWho: 'PME qui gèrent aujourd\'hui les congés par email ou tableur.',
                related: ['planning', 'chantiers'],
            ),
        ];
    }

    /**
     * @param list<string> $benefits
     * @param list<string> $related
     * @return array<string, mixed>
     */
    private function buildFeature(
        string $slug,
        string $title,
        string $metaDescription,
        string $teaser,
        string $h1,
        string $intro,
        array $benefits,
        string $forWho,
        array $related,
    ): array {
        return [
            'slug' => $slug,
            'title' => $title,
            'icon' => MarketingUi::featureIcon($slug),
            'metaDescription' => $metaDescription,
            'description' => $metaDescription,
            'teaser' => $teaser,
            'h1' => $h1,
            'intro' => $intro,
            'benefits' => $benefits,
            'forWho' => $forWho,
            'relatedSlugs' => $related,
        ];
    }

    /**
     * @return array<int, array{question:string, answer:string}>
     */
    public function faqItems(): array
    {
        return [
            [
                'question' => 'Pilora convient-il aux artisans et PME du BTP ?',
                'answer' => 'Oui. Pilora est pensé pour les entreprises du bâtiment : devis, factures, chantiers, planning et indicateurs de rentabilité dans un seul outil, sans la complexité d\'un ERP généraliste.',
            ],
            [
                'question' => 'Puis-je essayer Pilora gratuitement ?',
                'answer' => 'Les offres d\'essai permettent de démarrer sans engagement. Consultez la page Tarifs pour les packs disponibles et la durée d\'essai.',
            ],
            [
                'question' => 'Mes données sont-elles isolées des autres clients ?',
                'answer' => 'Chaque entreprise dispose d\'un espace dédié (multi-tenant) avec utilisateurs, rôles et permissions propres. Vos données ne sont pas mélangées avec celles d\'un autre client.',
            ],
            [
                'question' => 'Pilora remplace-t-il mon logiciel de comptabilité ?',
                'answer' => 'Pilora couvre la gestion commerciale et opérationnelle (devis, factures, chantiers). Les exports facilitent la transmission à votre expert-comptable ; la comptabilité officielle reste chez votre cabinet ou votre outil comptable.',
            ],
            [
                'question' => 'Comment contacter l\'équipe pour une démonstration ?',
                'answer' => 'Utilisez le formulaire « Demander une démo » : nous vous recontactons sous 48 h ouvrées pour une présentation adaptée à votre métier.',
            ],
            [
                'question' => 'Les équipes terrain peuvent-elles utiliser Pilora sur mobile ?',
                'answer' => 'L\'interface web de Pilora est responsive : photos de chantier, consultation des affaires et saisies simples sont possibles depuis un smartphone ou une tablette.',
            ],
        ];
    }

    /**
     * @return list<array{loc:string, changefreq:string, priority:string}>
     */
    public function sitemapEntries(): array
    {
        $entries = [
            ['loc' => $this->canonical('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => $this->canonical('/tarifs'), 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $this->canonical('/fonctionnalites'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => $this->canonical('/faq'), 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $this->canonical('/demo'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => $this->canonical('/inscription'), 'changefreq' => 'weekly', 'priority' => '0.9'],
        ];
        foreach ($this->featurePages() as $f) {
            $entries[] = [
                'loc' => $this->canonical('/fonctionnalites/' . $f['slug']),
                'changefreq' => 'monthly',
                'priority' => '0.75',
            ];
        }

        return $entries;
    }

    public function breadcrumbJsonLd(string $featureTitle, string $featureSlug): string
    {
        $items = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => $this->canonical('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Fonctionnalités', 'item' => $this->canonical('/fonctionnalites')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $featureTitle, 'item' => $this->canonical('/fonctionnalites/' . $featureSlug)],
        ];

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
