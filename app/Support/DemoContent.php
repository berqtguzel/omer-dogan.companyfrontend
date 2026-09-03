<?php

namespace App\Support;

/**
 * API'de henüz içerik yokken ana sayfanın boş kalmaması için yer tutucu içerik.
 *
 * Panele gerçek kayıt girildiği anda devre dışı kalır — sadece gelen liste
 * boşsa devreye girer. Canlıda tamamen kapatmak için: OMR_DEMO_CONTENT=false
 *
 * Dönen payload'larda `is_demo => true` bulunur; arayüz bunu rozet olarak
 * gösterir, böylece demo içerik gerçek içerikle karıştırılmaz.
 */
final class DemoContent
{
    public static function enabled(): bool
    {
        return (bool) config('services.omr.demo_content', true);
    }

    /** Gelen yorum listesi boşsa demo yorumlarla doldurur. */
    public static function reviews(array $payload, string $locale = 'de'): array
    {
        if (! empty($payload['items']) || ! self::enabled()) {
            return $payload;
        }

        $items = self::reviewItems($locale);
        $ratings = array_column($items, 'rating');

        return [
            'items' => $items,
            'summary' => [
                'count' => count($items),
                'average' => round(array_sum($ratings) / count($ratings), 1),
            ],
            'is_demo' => true,
        ];
    }

    /** Gelen SSS listesi boşsa demo sorularla doldurur. */
    public static function faq(array $payload, string $locale = 'de'): array
    {
        if (! empty($payload['items']) || ! self::enabled()) {
            return $payload;
        }

        return [
            'title' => '',
            'items' => self::faqItems($locale),
            'is_demo' => true,
        ];
    }

    private static function reviewItems(string $locale): array
    {
        $sets = [
            'de' => [
                ['Sabine K.', 'Hotel · Wien', 5, 'Das Team übernimmt unser komplettes Housekeeping. Die Zimmer sind pünktlich fertig und die Abstimmung mit der Rezeption läuft reibungslos.'],
                ['Markus T.', 'Facility Management', 5, 'Kurzfristige Bauendreinigung über mehrere Etagen – termingerecht erledigt, ohne dass wir nachbessern mussten.'],
                ['Elif Y.', 'Arztpraxis', 4, 'Zuverlässig, diskret und immer freundlich. Die Hygienestandards werden konsequent eingehalten.'],
            ],
            'en' => [
                ['Sabine K.', 'Hotel · Vienna', 5, 'The team handles our entire housekeeping. Rooms are always ready on time and coordination with reception is effortless.'],
                ['Markus T.', 'Facility management', 5, 'A short-notice post-construction clean across several floors, finished on schedule with nothing to redo.'],
                ['Elif Y.', 'Medical practice', 4, 'Reliable, discreet and always friendly. Hygiene standards are followed consistently.'],
            ],
            'tr' => [
                ['Sabine K.', 'Otel · Viyana', 5, 'Tüm kat hizmetlerimizi ekip yürütüyor. Odalar her zaman zamanında hazır oluyor ve resepsiyonla koordinasyon sorunsuz ilerliyor.'],
                ['Markus T.', 'Tesis yönetimi', 5, 'Birkaç katı kapsayan kısa süreli inşaat sonrası temizlik, söz verilen tarihte ve eksiksiz tamamlandı.'],
                ['Elif Y.', 'Muayenehane', 4, 'Güvenilir, ölçülü ve her zaman güler yüzlü. Hijyen standartları tutarlı biçimde uygulanıyor.'],
            ],
        ];

        $rows = $sets[$locale] ?? $sets['de'];

        return array_values(array_map(static fn ($row, $index) => [
            'id' => 'demo-review-'.($index + 1),
            'name' => $row[0],
            'role' => $row[1],
            'rating' => $row[2],
            'comment' => $row[3],
            'date' => '',
            'avatar' => null,
            'source' => '',
        ], $rows, array_keys($rows)));
    }

    private static function faqItems(string $locale): array
    {
        $sets = [
            'de' => [
                ['Wie schnell können Sie mit der Reinigung starten?', 'Nach der Besichtigung erhalten Sie in der Regel innerhalb von 24 Stunden ein Angebot. Der Start ist je nach Objektgröße meist innerhalb weniger Werktage möglich.'],
                ['Arbeiten Sie auch außerhalb der Geschäftszeiten?', 'Ja. Büros, Praxen und Ladenflächen reinigen wir auf Wunsch früh morgens, abends oder am Wochenende, damit Ihr Betrieb nicht unterbrochen wird.'],
                ['Sind Reinigungsmittel und Geräte im Preis enthalten?', 'Ja, sämtliche Mittel, Maschinen und Verbrauchsmaterialien bringen wir mit. Auf Wunsch arbeiten wir ausschließlich mit ökologisch zertifizierten Produkten.'],
                ['Wie wird die Qualität kontrolliert?', 'Jedes Objekt hat eine feste Ansprechperson. Regelmäßige Kontrollbegehungen und ein kurzer Mängelmeldeweg sorgen dafür, dass Abweichungen sofort behoben werden.'],
                ['Sind Sie versichert?', 'Wir sind vollständig haftpflichtversichert. Alle Mitarbeitenden sind angemeldet, geschult und arbeiten nach festen Hygiene- und Sicherheitsvorgaben.'],
                ['Kann der Vertrag flexibel angepasst werden?', 'Ja. Umfang und Intervalle lassen sich jederzeit anpassen — von der einmaligen Grundreinigung bis zur täglichen Unterhaltsreinigung.'],
            ],
            'en' => [
                ['How quickly can you start?', 'After a site visit you normally receive a quote within 24 hours. Depending on the size of the property, work can usually begin within a few working days.'],
                ['Do you work outside business hours?', 'Yes. Offices, practices and retail spaces can be cleaned early in the morning, in the evening or at weekends so your operation is never interrupted.'],
                ['Are supplies and equipment included?', 'Yes, we bring all products, machines and consumables. On request we work exclusively with ecologically certified products.'],
                ['How is quality monitored?', 'Every property has a dedicated contact person. Regular inspections and a short reporting path mean any issue is corrected straight away.'],
                ['Are you insured?', 'We carry full liability insurance. All staff are registered, trained and work to fixed hygiene and safety standards.'],
                ['Can the contract be adjusted?', 'Yes. Scope and intervals can be changed at any time — from a one-off deep clean to daily maintenance cleaning.'],
            ],
            'tr' => [
                ['Temizliğe ne kadar sürede başlayabilirsiniz?', 'Keşiften sonra genellikle 24 saat içinde teklifinizi iletiyoruz. Tesisin büyüklüğüne göre çalışma çoğunlukla birkaç iş günü içinde başlayabiliyor.'],
                ['Mesai saatleri dışında da çalışıyor musunuz?', 'Evet. Ofis, muayenehane ve mağazaları talebe göre sabah erken, akşam veya hafta sonu temizliyoruz; böylece işleyişiniz aksamıyor.'],
                ['Malzeme ve ekipman fiyata dahil mi?', 'Evet, tüm ürünleri, makineleri ve sarf malzemelerini biz getiriyoruz. Talep hâlinde yalnızca ekolojik sertifikalı ürünlerle çalışıyoruz.'],
                ['Kalite nasıl denetleniyor?', 'Her tesisin sabit bir sorumlusu var. Düzenli kontrol ziyaretleri ve kısa bir bildirim hattı sayesinde aksaklıklar anında gideriliyor.'],
                ['Sigortanız var mı?', 'Tam kapsamlı sorumluluk sigortamız bulunuyor. Tüm çalışanlar kayıtlı, eğitimli ve belirlenmiş hijyen ile güvenlik kurallarına göre çalışıyor.'],
                ['Sözleşme sonradan değiştirilebilir mi?', 'Evet. Kapsam ve sıklık istediğiniz zaman güncellenebilir — tek seferlik detaylı temizlikten günlük düzenli temizliğe kadar.'],
            ],
        ];

        $rows = $sets[$locale] ?? $sets['de'];

        return array_values(array_map(static fn ($row, $index) => [
            'id' => 'demo-faq-'.($index + 1),
            'question' => $row[0],
            'answer' => $row[1],
            'order' => $index,
        ], $rows, array_keys($rows)));
    }
}
