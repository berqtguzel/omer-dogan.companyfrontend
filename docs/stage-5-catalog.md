# Aşama 5 — Kurumsal katalog

Faaliyet alanları, şirketler ve projeler için liste/detay sayfaları tamamlandı.
`/geschaeftsbereiche`, `/unternehmen`, `/projekte` ve bunların `/{id}`
detayları dil önekli ve öneksiz çalışır. Bilinmeyen detaylar 404 döner.

Listelerde metin araması, mevcut verilere göre sektör/konum filtreleri,
sonuç sayısı, boş sonuç ve filtre temizleme bulunur. Detaylarda sayfa yolu,
açıklama, uzun içerik, odak noktaları, konum/sektör ve iletişim bağlantısı vardır.
İlişkili içerik açık `areaId` eşleşmesiyle seçilir; şirket adından ilişki tahmin edilmez.

Mevcut `StaticPageController::getPages` cache/API yolu ve
`HomePageData` kullanılır. `config/corporate_home.php` içindeki
`business_area_pages`, `company_pages`, `project_pages` mevcut panel sayfa
sluglarına eşlenir. Şirket/proje detay URL kimliği bu yapılandırılmış slugdır.
Yayımlanmamış veya ilgili dilde çevirisi olmayan kayıtlar gösterilmez.
İçerik güvenli düz metin olarak sunulur. Panelin gerçek ilişki, şirket web sitesi
ve proje sonuç alanları henüz doğrulanmadığından bu alanlar için API tahmini
yapılmadı; genel normalizer çalışması Aşama 7'de devam edecek.

Kontroller:

- `php vendor/pestphp/pest/bin/pest tests/Unit/CorporateHomeTest.php tests/Unit/NavigationDataTest.php --compact`
- `npm.cmd run build`
- `node tests/browser/corporate-catalog.mjs` (yerel sunucu 8124, Chrome CDP 9333)

Tarayıcı testi üç dil, 33 detay bağlantısı, filtre/arama/temizleme, 390/768/1440
genişlikleri, tek h1, yatay taşma, 404 ve konsol hatalarını denetler.
Eski demo önizleme Aşama 9'da kaldırılmıştır; gerçek veri yoksa listeler boş durum gösterir.
