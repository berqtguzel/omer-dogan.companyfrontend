/**
 * Görsel optimizasyon utility'leri
 * WebP desteği, lazy loading threshold, ve boyut optimizasyonu
 */

/**
 * WebP format desteğini kontrol eder
 */
export function supportsWebP() {
    if (typeof window === 'undefined') return false;
    
    const canvas = document.createElement('canvas');
    canvas.width = 1;
    canvas.height = 1;
    return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
}

/**
 * Görsel URL'ini WebP formatına dönüştürür (eğer destekleniyorsa)
 * @param {string} url - Orijinal görsel URL'i
 * @returns {string} - WebP formatında URL veya orijinal URL
 */
export function getWebPUrl(url) {
    if (!url) return url;

    // Eğer zaten WebP formatındaysa, değiştirme
    if (url.includes('.webp')) return url;

    // SSR ve hydration sırasında sunucu/istemci img src uyumsuzluğu olmasın diye
    // render aşamasında orijinal URL'i koru. WebP dönüşümü yalnızca istemci tarafı
    // yardımcılarında (useEffect vb.) supportsWebP() ile yapılmalı.
    if (typeof window === 'undefined') return url;

    return url;
}

/**
 * Image CDN optimizasyonu - boyut ve kalite parametreleri ekler
 * @param {string} url - Görsel URL'i
 * @param {Object} options - Optimizasyon seçenekleri
 * @returns {string} - Optimize edilmiş URL
 */
export function optimizeImageForCDN(url, options = {}) {
    if (!url) return url;
    
    const {
        width = null,
        height = null,
        quality = 85,
        format = 'webp',
        fit = 'cover',
    } = options;
    
    // Eğer URL zaten query parametreleri içeriyorsa
    const urlObj = new URL(url.startsWith('http') ? url : `https://example.com${url}`);
    
    // Format
    if (format === 'webp' && supportsWebP()) {
        urlObj.searchParams.set('format', 'webp');
    }
    
    // Boyut
    if (width) urlObj.searchParams.set('w', width);
    if (height) urlObj.searchParams.set('h', height);
    
    // Kalite
    urlObj.searchParams.set('q', quality);
    
    // Fit mode
    urlObj.searchParams.set('fit', fit);
    
    // Return relative URL if original was relative
    if (!url.startsWith('http')) {
        return urlObj.pathname + urlObj.search;
    }
    
    return urlObj.toString();
}

/**
 * Responsive görsel için srcset oluşturur
 * @param {string} baseUrl - Temel görsel URL'i
 * @param {number[]} widths - İstenen genişlikler (örn: [400, 800, 1200])
 * @returns {Object} - { srcset: string, srcsetWebP: string }
 */
export function generateSrcSet(baseUrl, widths = [400, 800, 1200, 1920]) {
    if (!baseUrl) return { srcset: '', srcsetWebP: '' };
    
    const webPUrl = getWebPUrl(baseUrl);
    const originalUrl = baseUrl;
    
    // WebP srcset
    const srcsetWebP = widths.map(width => {
        // URL'de width parametresi ekle veya dosya adına ekle
        if (webPUrl.includes('?')) {
            return `${webPUrl}&w=${width} ${width}w`;
        }
        // Dosya adına width ekle
        const webP = webPUrl.replace(/(\.webp|\.jpg|\.jpeg|\.png)(\?.*)?$/i, `_${width}w$1`);
        return `${webP} ${width}w`;
    }).join(', ');
    
    // Fallback srcset (orijinal format)
    const srcset = widths.map(width => {
        if (originalUrl.includes('?')) {
            return `${originalUrl}&w=${width} ${width}w`;
        }
        const fallback = originalUrl.replace(/(\.jpg|\.jpeg|\.png|\.webp)(\?.*)?$/i, `_${width}w$1`);
        return `${fallback} ${width}w`;
    }).join(', ');
    
    return { srcset, srcsetWebP };
}

/**
 * Lazy loading için optimize edilmiş Intersection Observer ayarları
 * @param {number} threshold - Görünürlük threshold'u (0-1 arası)
 * @param {string} rootMargin - Root margin (örn: "100px" veya "50%")
 * @returns {Object} - IntersectionObserver options
 */
export function getLazyLoadOptions(threshold = 0.1, rootMargin = '400px') {
    return {
        root: null,
        rootMargin,
        threshold,
    };
}

/**
 * Görsel boyutunu optimize eder (responsive için)
 * @param {string} url - Görsel URL'i
 * @param {number} maxWidth - Maksimum genişlik
 * @returns {string} - Optimize edilmiş URL
 */
export function optimizeImageUrl(url, maxWidth = 1920) {
    if (!url) return url;
    
    // Eğer URL zaten query parametreleri içeriyorsa
    if (url.includes('?')) {
        return `${url}&w=${maxWidth}`;
    }
    
    // WebP formatına dönüştür
    const webPUrl = getWebPUrl(url);
    
    // Eğer WebP destekleniyorsa ve URL değiştiyse
    if (webPUrl !== url && supportsWebP()) {
        return `${webPUrl}?w=${maxWidth}`;
    }
    
    return `${url}?w=${maxWidth}`;
}

/**
 * Deterministic URL params for SSR-safe image resizing.
 * This does not depend on browser feature checks, so server/client markup stays equal.
 */
export function withImageParams(url, options = {}) {
    if (!url || url.startsWith("data:") || url.startsWith("blob:")) return url;

    const {
        width = null,
        height = null,
        quality = 80,
        format = "webp",
        fit = null,
    } = options;

    try {
        const isAbsolute = /^https?:\/\//i.test(url);
        const urlObj = new URL(isAbsolute ? url : `https://local.invalid${url}`);

        if (format) urlObj.searchParams.set("format", format);
        if (width) urlObj.searchParams.set("w", String(width));
        if (height) urlObj.searchParams.set("h", String(height));
        if (quality) urlObj.searchParams.set("q", String(quality));
        if (fit) urlObj.searchParams.set("fit", fit);

        return isAbsolute
            ? urlObj.toString()
            : `${urlObj.pathname}${urlObj.search}`;
    } catch (error) {
        const params = new URLSearchParams();
        if (format) params.set("format", format);
        if (width) params.set("w", String(width));
        if (height) params.set("h", String(height));
        if (quality) params.set("q", String(quality));
        if (fit) params.set("fit", fit);

        return `${url}${url.includes("?") ? "&" : "?"}${params.toString()}`;
    }
}

/**
 * Builds deterministic responsive image attributes for SSR and the browser.
 * Local media-cache files are already resized WebP variants and can opt out
 * of URL transformation while still retaining intrinsic dimensions.
 */
export function buildResponsiveImage(url, options = {}) {
    if (!url) return { src: "", srcSet: "" };

    const {
        widths = [480, 800, 1200],
        width = widths[widths.length - 1] || null,
        quality = 75,
        format = "webp",
        fit = "cover",
        transform = true,
    } = options;
    const source = String(url);
    const isLocalMediaCache =
        /^\/?(?:storage\/)?media-cache\//i.test(source) ||
        /\/storage\/media-cache\//i.test(source);
    const canTransform =
        transform &&
        !isLocalMediaCache &&
        !source.startsWith("data:") &&
        !source.startsWith("blob:") &&
        !/\.svg(?:\?|$)/i.test(source);

    if (!canTransform) {
        return { src: source, srcSet: "" };
    }

    const normalizedWidths = [...new Set(widths)]
        .map(Number)
        .filter((value) => Number.isFinite(value) && value > 0)
        .sort((a, b) => a - b);
    const params = { quality, format, fit };
    const src = withImageParams(source, { ...params, width });
    const srcSet = normalizedWidths
        .map(
            (candidateWidth) =>
                `${withImageParams(source, {
                    ...params,
                    width: candidateWidth,
                })} ${candidateWidth}w`,
        )
        .join(", ");

    return { src, srcSet };
}

/**
 * Görsel yükleme durumunu yönetir
 */
export class ImageLoader {
    constructor(options = {}) {
        this.options = {
            threshold: 0.1,
            rootMargin: '400px',
            ...options,
        };
    }
    
    /**
     * Lazy loading için Intersection Observer oluşturur
     * @param {HTMLElement} element - Gözlemlenecek element
     * @param {Function} callback - Görünür olduğunda çağrılacak callback
     * @returns {IntersectionObserver} - Observer instance
     */
    observe(element, callback) {
        if (!element) return null;
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    callback(entry);
                    observer.unobserve(entry.target);
                }
            });
        }, this.options);
        
        observer.observe(element);
        return observer;
    }
}
