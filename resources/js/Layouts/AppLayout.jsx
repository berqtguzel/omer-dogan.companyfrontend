import React, { useEffect, useState, memo } from "react";
import { Head, usePage } from "@inertiajs/react";
import SeoHead from "@/Components/SeoHead";
import TrackingHead from "@/Components/TrackingHead";
import Header from "../Components/Header";
import Footer from "../Components/Footer";
import CookieBanner from "../Components/CookieBanner";
import Cookies from "js-cookie";
import WhatsAppWidget from "@/Components/WhatsAppWidget";
import Loading from "@/Components/Common/Loading";
import QuoteModal from "@/Components/Modals/QuoteModal";
import { renderText, renderUrl } from "@/utils/renderValue";

/* ---------------------------------------------------------------------------
 * Panelden gelen header/footer zemin rengi her tenant'ta farklı olabiliyor
 * (beyaz da olabilir, koyu bir marka rengi de). Yazı rengini sabitlemek yerine
 * zeminin parlaklığından hesaplıyoruz; böylece her renkte okunur kalıyor.
 * ------------------------------------------------------------------------- */

function parseColor(value) {
    const input = String(value || "").trim();

    const hex = input.replace(/^#/, "");
    if (/^[0-9a-f]{3}$/i.test(hex)) {
        return hex.split("").map((c) => parseInt(c + c, 16));
    }
    if (/^[0-9a-f]{6}$/i.test(hex)) {
        return [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));
    }

    const rgb = input.match(/rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/i);
    if (rgb) {
        return [1, 2, 3].map((i) => Number(rgb[i]));
    }

    return null;
}

/** WCAG bağıl parlaklık (0 = siyah, 1 = beyaz). */
function relativeLuminance(rgb) {
    const [r, g, b] = rgb.map((channel) => {
        const c = Math.min(255, Math.max(0, channel)) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * Bir yüzey rengi için okunur yazı/çizgi/vurgu değişkenleri üretir.
 * Zemin açıksa marka rengi vurgu olarak kullanılabilir; koyuysa beyaza geçer.
 */
function surfaceInkVars(prefix, background, brand) {
    const rgb = parseColor(background);

    if (!rgb) return {};

    const isLight = relativeLuminance(rgb) > 0.45;

    return isLight
        ? {
              [`--${prefix}-ink`]: "",
              [`--${prefix}-ink-muted`]: "",
              [`--${prefix}-line`]: "",
              [`--${prefix}-soft`]: "",
              [`--${prefix}-accent`]: brand || "",
          }
        : {
              [`--${prefix}-ink`]: "#ffffff",
              [`--${prefix}-ink-muted`]: "rgba(255,255,255,.72)",
              [`--${prefix}-line`]: "rgba(255,255,255,.24)",
              [`--${prefix}-soft`]: "rgba(255,255,255,.16)",
              [`--${prefix}-accent`]: "#ffffff",
          };
}

const AppLayout = memo(function AppLayout({ children }) {
    const { props, component } = usePage();

    const locale = props.locale || "de";
    const tenantId = props.tenantId || "";
    const settings = props.settings ?? {};
    const menus = props.menus ?? {};
    const global = props.global ?? {};
    const general = settings.general ?? {};
    const seo = settings.seo ?? {};
    const branding = settings.branding ?? {};
    const colors = settings.colors ?? {};
    const analytics = settings.analytics ?? {};

    const headerMenu = Array.isArray(menus?.header?.items)
        ? menus.header.items
        : Array.isArray(menus?.header)
          ? menus.header
          : [];

    const footerMenu = Array.isArray(menus?.footer?.items)
        ? menus.footer.items
        : Array.isArray(menus?.footer)
          ? menus.footer
          : [];

    const siteTitle = renderText(
        seo.meta_title,
        renderText(general.site_name, "Website"),
    );
    const siteDescription = renderText(
        seo.meta_description,
        renderText(general.site_description),
    );
    const siteKeywords = renderText(seo.meta_keywords);
    const ogTitle = renderText(seo.og_title, siteTitle);
    const ogDescription = renderText(seo.og_description, siteDescription);
    const ogImage = renderUrl(seo.og_image);
    const pageOwnsSeo = [
        "Home",
        "StaticPage",
        "Services/Index",
        "Services/Show",
        "Blog/Index",
        "Blog/Show",
        "kontakt/index",
        "Errors/NotFound",
        "Errors/Show",
    ].includes(component);

    const faviconSource = renderUrl(
        branding.site_favicon_url ||
            settings.site_favicon_url ||
            branding.favicon_url ||
            branding.site_favicon ||
            settings.site_favicon ||
            branding.favicon,
    );
    const faviconVersion = faviconSource
        ? faviconSource.split("?")[0].split("/").filter(Boolean).pop()
        : "default";
    const faviconExtension = faviconSource
        ?.split("?")[0]
        .match(/\.(ico|png|jpe?g|gif|webp|svg)$/i)?.[1]
        ?.toLowerCase() || "webp";
    const favicon = `/favicon.${faviconExtension}?v=${encodeURIComponent(faviconVersion || "default")}`;

    const rootStyles = {
        "--primary-color": colors.site_primary_color,
        "--secondary-color": colors.site_secondary_color,
        "--accent-color": colors.site_accent_color,

        "--text-color": colors.text_color,
        "--heading-1-color": colors.h1_color,
        "--heading-2-color": colors.h2_color,
        "--heading-3-color": colors.h3_color,
        "--link-color": colors.link_color,

        "--background-color": colors.background_color,
        "--header-bg": colors.header_background_color,
        "--footer-bg": colors.footer_background_color,

        "--button-bg": colors.button_color,
    };

    useEffect(() => {
        if (!colors || typeof colors !== "object") return;
        const root = document.documentElement;
        Object.keys(colors).forEach((key) => {
            root.style.setProperty(`--${key.replace(/_/g, "-")}`, colors[key]);
        });

        // header/footer zeminine göre okunur yazı rengi
        const inkVars = {
            ...surfaceInkVars(
                "header",
                colors.header_background_color,
                colors.site_primary_color,
            ),
            ...surfaceInkVars(
                "footer",
                colors.footer_background_color,
                colors.site_primary_color,
            ),
        };

        Object.entries(inkVars).forEach(([name, value]) => {
            if (value) {
                root.style.setProperty(name, value);
            } else {
                root.style.removeProperty(name);
            }
        });
    }, [colors]);

    const [isClient, setIsClient] = useState(false);
    useEffect(() => setIsClient(true), []);

    const [showCookieSettings, setShowCookieSettings] = useState(false);
    useEffect(() => {
        if (!Cookies.get("cookie_consent")) {
            setShowCookieSettings(true);
        }
    }, []);

    const whatsappData = Array.isArray(props.widgets?.whatsapp)
        ? props.widgets.whatsapp
        : [];

    return (
        <>
            {!pageOwnsSeo && (
                <SeoHead
                    title={siteTitle}
                    description={siteDescription}
                    keywords={siteKeywords}
                    image={ogImage}
                    ogTitle={ogTitle}
                    ogDescription={ogDescription}
                    locale={locale}
                    canonical={props.tenantSeo?.canonicalUrl}
                    origin={props.tenantSeo?.canonicalBaseUrl}
                    favicon={favicon}
                />
            )}

            {favicon && <Head>
                <link
                    head-key="favicon"
                    rel="icon"
                    href={favicon}
                />
                <link
                    head-key="shortcut-icon"
                    rel="shortcut icon"
                    href={favicon}
                />
                <link
                    head-key="apple-touch-icon"
                    rel="apple-touch-icon"
                    href={favicon}
                />
            </Head>}
            <TrackingHead analytics={analytics} />

            <div className="site-shell min-h-screen flex flex-col" style={rootStyles}>
                <Header menu={headerMenu} settings={settings} />
                <main className="flex-grow relative z-10">{children}</main>
                <Footer menu={footerMenu} settings={settings} />
            </div>

            {isClient && (
                <>
                    <Loading style={rootStyles} />
                    <QuoteModal style={rootStyles} />

                    <CookieBanner
                        style={rootStyles}
                        forceVisible={showCookieSettings}
                        onClose={() => setShowCookieSettings(false)}
                    />

                    {/* 🔥 ARTIK ASLA PATLAMAZ */}
                    <WhatsAppWidget style={rootStyles} data={whatsappData} />
                </>
            )}
        </>
    );
});

export default AppLayout;
