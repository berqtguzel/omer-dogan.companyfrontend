# Aşama 4 — Kurumsal ana sayfa

Aşama 4 sırasında geçici bir demo veri katmanı kullanılmıştı. Bu katman Aşama 9'da tamamen kaldırıldı; `resources/demo` kayıtları ve `DemoContent` artık uygulamada bulunmaz.

Kurumsal arayüz yalnızca doğrulanmış panel sayfalarını `config/corporate_home.php` içindeki açık slug eşleştirmeleri üzerinden gösterir. `companies`, `projects`, `metrics`, `operations` ve `career` için bağımsız bir panel sözleşmesi varsayılmaz. Eşleşen gerçek sayfa bulunmadığında ilgili koleksiyon boş kalır ve arayüz güvenli boş durumunu kullanır.

Yeni tenant ve kurumsal içerik doğrulanana kadar:

```env
CORPORATE_CONTENT_READY=false
```

Bu durumda uygulama eski tenant verisini çağırmaz veya yayınlamaz, robots tüm taramayı engeller, sitemap 503 döner, canonical üretilmez ve SEO/AI kimlik dosyaları 404 döner. Tenant, canonical alan adı, panel slug eşleştirmeleri, iletişim formu ve SEO dosyaları doğrulandıktan sonra değer `true` yapılabilir.

Ana sayfa veri akışı `HomeController`, `CorporateContentService`, `HomePageData`, `GlobalSiteDataService` ve `SiteShellData` üzerinden normalize edilir. React bileşenleri yalnızca normalize edilmiş Inertia props kullanır.
