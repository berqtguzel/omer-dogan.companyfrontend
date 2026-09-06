<?php

namespace App\Support;

final class CorporatePreviewContent
{
    private static function copy(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'group' => 'Strength through connection', 'groupText' => 'We unite entrepreneurial experience, responsible growth and new ideas under one roof.',
                'areas' => ['Hospitality' => 'Distinctive hospitality concepts and operational excellence.', 'Real Estate' => 'Properties with lasting value and a clear perspective.', 'Facility & Industry' => 'Reliable processes for complex sites and operations.', 'Trade & Technology' => 'Digital solutions and partnerships for tomorrow.'],
                'companies' => [['Dogan Hospitality', 'Hospitality', 'Berlin'], ['ODC Real Estate', 'Real Estate', 'Germany'], ['Dogan Services', 'Facility', 'DACH'], ['Nexa Commerce', 'Technology', 'Europe']],
                'projects' => [['Urban Stay', 'Hospitality', 'Berlin'], ['Riverside Quarter', 'Real Estate', 'Hamburg'], ['Smart Operations', 'Technology', 'DACH']],
                'values' => ['Act responsibly', 'Build lasting partnerships', 'Shape progress'],
                'countries' => [['DE', 'Germany'], ['AT', 'Austria'], ['TR', 'Türkiye']],
                'nav' => ['Group', 'Business areas', 'Companies', 'Projects', 'Careers', 'Contact'],
            ],
            'tr' => [
                'group' => 'Bağlantıdan doğan güç', 'groupText' => 'Girişimcilik deneyimini, sorumlu büyümeyi ve yeni fikirleri aynı çatı altında buluşturuyoruz.',
                'areas' => ['Konaklama' => 'Özgün konaklama konseptleri ve operasyonel mükemmellik.', 'Gayrimenkul' => 'Kalıcı değere ve net bir bakış açısına sahip yapılar.', 'Tesis & Endüstri' => 'Karmaşık tesisler için güvenilir operasyon süreçleri.', 'Ticaret & Teknoloji' => 'Yarının dünyası için dijital çözümler ve ortaklıklar.'],
                'companies' => [['Dogan Hospitality', 'Konaklama', 'Berlin'], ['ODC Real Estate', 'Gayrimenkul', 'Almanya'], ['Dogan Services', 'Tesis Yönetimi', 'DACH'], ['Nexa Commerce', 'Teknoloji', 'Avrupa']],
                'projects' => [['Urban Stay', 'Konaklama', 'Berlin'], ['Riverside Quarter', 'Gayrimenkul', 'Hamburg'], ['Smart Operations', 'Teknoloji', 'DACH']],
                'values' => ['Sorumlulukla hareket etmek', 'Kalıcı ortaklıklar kurmak', 'Gelişime yön vermek'],
                'countries' => [['DE', 'Almanya'], ['AT', 'Avusturya'], ['TR', 'Türkiye']],
                'nav' => ['Grup', 'Faaliyet alanları', 'Şirketler', 'Projeler', 'Kariyer', 'İletişim'],
            ],
            default => [
                'group' => 'Stärke durch Verbindung', 'groupText' => 'Wir verbinden unternehmerische Erfahrung, verantwortungsvolles Wachstum und neue Ideen unter einem Dach.',
                'areas' => ['Hospitality' => 'Besondere Gastkonzepte und operative Exzellenz.', 'Immobilien' => 'Räume mit dauerhaftem Wert und klarer Perspektive.', 'Facility & Industrie' => 'Verlässliche Prozesse für komplexe Standorte und Betriebe.', 'Handel & Technologie' => 'Digitale Lösungen und Partnerschaften für morgen.'],
                'companies' => [['Dogan Hospitality', 'Hospitality', 'Berlin'], ['ODC Real Estate', 'Immobilien', 'Deutschland'], ['Dogan Services', 'Facility', 'DACH'], ['Nexa Commerce', 'Technologie', 'Europa']],
                'projects' => [['Urban Stay', 'Hospitality', 'Berlin'], ['Riverside Quarter', 'Immobilien', 'Hamburg'], ['Smart Operations', 'Technologie', 'DACH']],
                'values' => ['Verantwortlich handeln', 'Partnerschaften gestalten', 'Fortschritt ermöglichen'],
                'countries' => [['DE', 'Deutschland'], ['AT', 'Österreich'], ['TR', 'Türkiye']],
                'nav' => ['Unternehmensgruppe', 'Geschäftsbereiche', 'Unternehmen', 'Projekte', 'Karriere', 'Kontakt'],
            ],
        };
    }

    public static function settings(string $locale): array
    {
        $copy = self::copy($locale);
        return ['general' => ['site_name' => 'Ömer Dogan Company GmbH', 'site_description' => $copy['groupText']], 'footer' => ['footer_description' => $copy['groupText']]];
    }

    public static function menus(string $locale): array
    {
        $n = self::copy($locale)['nav'];
        $items = [
            ['label' => $n[0], 'url' => '/ueber-uns'], ['label' => $n[1], 'url' => '/geschaeftsbereiche'],
            ['label' => $n[2], 'url' => '/unternehmen'], ['label' => $n[3], 'url' => '/projekte'],
            ['label' => $n[4], 'url' => '/karriere'], ['label' => $n[5], 'url' => '/kontakt'],
        ];
        return ['header' => $items, 'footer' => $items];
    }

    public static function home(string $locale, bool $prefixed): array
    {
        $c = self::copy($locale); $prefix = $prefixed ? '/'.$locale : '';
        $link = fn (string $path) => ['href' => $prefix.$path, 'external' => false, 'newTab' => false];
        $areaIds = ['hospitality', 'real-estate', 'facility', 'trade-technology'];
        $areas = []; $i = 0;
        foreach ($c['areas'] as $name => $description) { $id = $areaIds[$i++]; $areas[] = ['id' => $id, 'title' => $name, 'description' => $description, 'body' => $description, 'image' => '', 'count' => null, 'alternates' => [], 'link' => $link('/geschaeftsbereiche/'.$id)]; }
        $cards = function (array $rows, string $path) use ($link) { return array_map(fn ($row) => ['id' => strtolower(str_replace(' ', '-', $row[0])), 'name' => $row[0], 'description' => $row[0].' — '.$row[1], 'body' => $row[0].' — '.$row[1], 'sector' => $row[1], 'location' => $row[2], 'image' => '', 'alternates' => [], 'link' => $link('/'.$path.'/'.strtolower(str_replace(' ', '-', $row[0])))], $rows); };
        return [
            'seo' => ['title' => 'Ömer Dogan Company GmbH', 'description' => $c['groupText'], 'keywords' => '', 'image' => '', 'ogTitle' => '', 'ogDescription' => ''],
            'hero' => ['title' => 'Ömer Dogan Company GmbH', 'description' => $c['groupText'], 'image' => '', 'video' => '/videos/SliderVideo.mp4', 'primary' => $link('/ueber-uns'), 'primaryLabel' => ''],
            'businessAreas' => $areas, 'companies' => $cards($c['companies'], 'unternehmen'), 'projects' => $cards($c['projects'], 'projekte'),
            'countries' => array_map(fn ($row) => ['code' => $row[0], 'name' => $row[1], 'description' => $c['groupText']], $c['countries']),
            'metrics' => [['value' => '4', 'label' => $c['nav'][1]], ['value' => '3', 'label' => $c['nav'][3]], ['value' => '3', 'label' => $c['countries'][0][1]], ['value' => '1', 'label' => $c['group']]],
            'vision' => ['title' => $c['group'], 'description' => $c['groupText'], 'image' => '', 'values' => $c['values'], 'link' => $link('/ueber-uns')],
            'careerLink' => $link('/karriere'), 'contactLink' => $link('/kontakt'),
        ];
    }

    public static function document(string $locale, string $kind): array
    {
        $c = self::copy($locale); $title = match ($kind) { 'karriere' => $c['nav'][4], 'impressum' => 'Impressum', 'datenschutz' => $locale === 'tr' ? 'Gizlilik' : ($locale === 'en' ? 'Privacy' : 'Datenschutz'), default => $c['group'] };
        return ['title' => $title, 'content' => '<p>'.e($c['groupText']).'</p>', 'alternates' => [], 'image' => '', 'available' => true, 'seo' => ['title' => $title, 'description' => $c['groupText'], 'keywords' => '']];
    }
}
