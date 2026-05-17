<?php
declare(strict_types=1);

namespace Modules\Marketing\Controllers;

use App\Controllers\BaseController;
use Core\Config;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Marketing\Services\DemoRequestService;
use Modules\Marketing\Services\MarketingBrandService;
use Modules\Marketing\Services\MarketingSeoService;
use Modules\Platform\Repositories\PackRepository;

final class MarketingController extends BaseController
{
    private MarketingSeoService $seo;

    public function __construct()
    {
        $this->seo = new MarketingSeoService();
    }

    public function home(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId !== null) {
            return Response::redirect('dashboard');
        }

        $desc = 'Pilora centralise devis, factures, chantiers, planning et rentabilité pour les entreprises du bâtiment. Essai et démo disponibles.';

        return $this->marketingPage('marketing/home.php', [
            'pageTitle' => 'Pilora — ERP BTP pour artisans et PME',
            'metaDescription' => $desc,
            'canonicalPath' => '/',
            'bodyClass' => 'marketing-page marketing-page--home',
            'jsonLd' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => 'Pilora',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'description' => $desc,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function tarifs(Request $request, UserContext $userContext): Response
    {
        $packs = [];
        try {
            $packs = (new PackRepository())->listAll();
        } catch (\Throwable) {
        }

        return $this->marketingPage('marketing/tarifs.php', [
            'pageTitle' => 'Tarifs Pilora — Offres ERP BTP',
            'metaDescription' => 'Comparez les packs Pilora : nombre d\'utilisateurs, prix mensuel et essai gratuit pour votre entreprise du bâtiment.',
            'canonicalPath' => '/tarifs',
            'bodyClass' => 'marketing-page marketing-page--tarifs',
            'packs' => $packs,
        ]);
    }

    public function fonctionnalitesIndex(Request $request, UserContext $userContext): Response
    {
        return $this->marketingPage('marketing/fonctionnalites_index.php', [
            'pageTitle' => 'Fonctionnalités Pilora — ERP BTP',
            'metaDescription' => 'Découvrez les modules Pilora : devis, facturation, chantiers, planning, rentabilité et RH.',
            'canonicalPath' => '/fonctionnalites',
            'bodyClass' => 'marketing-page marketing-page--features',
            'features' => $this->seo->featurePages(),
        ]);
    }

    public function fonctionnaliteShow(Request $request, UserContext $userContext): Response
    {
        $parts = explode('/', trim($request->getPath(), '/'));
        $slug = trim((string) ($parts[1] ?? ''));
        $feature = $this->seo->featureBySlug($slug);
        if ($feature === null) {
            return Response::redirect('fonctionnalites');
        }

        $all = $this->seo->featurePages();
        $related = [];
        foreach ((array) ($feature['relatedSlugs'] ?? []) as $relSlug) {
            foreach ($all as $f) {
                if ($f['slug'] === $relSlug) {
                    $related[] = $f;
                    break;
                }
            }
        }

        return $this->marketingPage('marketing/fonctionnalite_show.php', [
            'pageTitle' => $feature['title'] . ' — Pilora ERP BTP',
            'metaDescription' => (string) $feature['metaDescription'],
            'canonicalPath' => '/fonctionnalites/' . $slug,
            'bodyClass' => 'marketing-page marketing-page--feature',
            'feature' => $feature,
            'relatedFeatures' => $related,
            'jsonLd' => $this->seo->breadcrumbJsonLd((string) $feature['title'], $slug),
        ]);
    }

    public function faq(Request $request, UserContext $userContext): Response
    {
        $faq = $this->seo->faqItems();
        $faqLd = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ], $faq),
        ];

        return $this->marketingPage('marketing/faq.php', [
            'pageTitle' => 'FAQ Pilora — Questions fréquentes',
            'metaDescription' => 'Réponses aux questions sur Pilora : essai, sécurité des données, démonstration et adaptation au BTP.',
            'canonicalPath' => '/faq',
            'bodyClass' => 'marketing-page marketing-page--faq',
            'faqItems' => $faq,
            'jsonLd' => json_encode($faqLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function demo(Request $request, UserContext $userContext): Response
    {
        return $this->marketingPage('marketing/demo.php', [
            'pageTitle' => 'Demander une démo Pilora',
            'metaDescription' => 'Planifiez une démonstration personnalisée de Pilora pour votre entreprise du bâtiment.',
            'canonicalPath' => '/demo',
            'bodyClass' => 'marketing-page marketing-page--demo',
            'csrfToken' => Csrf::token(),
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function demoSubmit(Request $request, UserContext $userContext): Response
    {
        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('demo?err=Requete%20invalide');
        }
        try {
            (new DemoRequestService())->submit(
                name: (string) $request->getBodyParam('name', ''),
                email: (string) $request->getBodyParam('email', ''),
                company: (string) $request->getBodyParam('company', ''),
                message: (string) $request->getBodyParam('message', ''),
            );
        } catch (\InvalidArgumentException) {
            return Response::redirect('demo?err=Informations%20incompletes');
        } catch (\Throwable) {
            return Response::redirect('demo?err=Enregistrement%20impossible.%20Reessayez%20ou%20contactez-nous%20par%20email.');
        }
        Csrf::rotate();

        return Response::redirect('demo?msg=Demande%20enregistree.%20Nous%20vous%20recontactons%20sous%2048h.');
    }

    public function robots(Request $request, UserContext $userContext): Response
    {
        $base = $this->seo->appUrl();
        $sitemap = $base !== '' ? $base . '/sitemap.xml' : '/sitemap.xml';
        $body = "User-agent: *\nAllow: /\nDisallow: /platform/\nDisallow: /settings\nDisallow: /dashboard\nSitemap: {$sitemap}\n";

        return new Response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function sitemap(Request $request, UserContext $userContext): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($this->seo->sitemapEntries() as $e) {
            $xml .= '  <url><loc>' . htmlspecialchars($e['loc'], ENT_XML1) . '</loc>';
            $xml .= '<changefreq>' . $e['changefreq'] . '</changefreq>';
            $xml .= '<priority>' . $e['priority'] . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>';

        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function marketingPage(string $view, array $data): Response
    {
        $canonicalPath = (string) ($data['canonicalPath'] ?? '/');
        $data['canonicalUrl'] = $this->seo->canonical($canonicalPath);
        $data['metaDescription'] = (string) ($data['metaDescription'] ?? '');
        $data['ogTitle'] = (string) ($data['pageTitle'] ?? 'Pilora');
        $data['appName'] = 'Pilora';
        $data['demoVideoUrl'] = trim((string) (Config::env('MARKETING_DEMO_VIDEO_URL', '') ?? ''));
        $data['analyticsId'] = trim((string) (Config::env('MARKETING_GA_MEASUREMENT_ID', '') ?? ''));
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $bp = rtrim(str_replace('\\', '/', (string) dirname($scriptName)), '/');
        if ($bp === '.' || $bp === '\\') {
            $bp = '';
        }
        $data['brandLogoUrl'] = (new MarketingBrandService())->brandLogoUrl($bp);

        $response = $this->renderPage($view, $data, 'layouts/marketing.php');

        return $response->withHeaders([
            'Cache-Control' => 'public, max-age=600, stale-while-revalidate=3600',
        ]);
    }
}
