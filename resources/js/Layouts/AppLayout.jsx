import React, { useEffect, useState, memo } from "react";
import { Head, usePage } from "@inertiajs/react";
import SeoHead from "@/Components/SeoHead";
import TrackingHead from "@/Components/TrackingHead";
import Header from "@/Components/Navigation/Header";
import ContactLanyard from '@/Components/Contact/ContactLanyard';
import Footer from "@/Components/Navigation/Footer";
import { corporateTheme } from "@/utils/corporateTheme";
import { navigationLabels } from "@/i18n/navigation";
import { useTranslation } from 'react-i18next';
import CookieBanner from "../Components/CookieBanner";
import Cookies from "js-cookie";
import WhatsAppWidget from "@/Components/WhatsAppWidget";
import Loading from "@/Components/Common/Loading";
import QuoteModal from "@/Components/Modals/QuoteModal";
import { renderText, renderUrl } from "@/utils/renderValue";

const AppLayout = memo(function AppLayout({ children }) {
    const { props, component } = usePage();
    const { t } = useTranslation();

    const locale = props.locale || "de";
    const settings = props.settings ?? {};
    const site = props.siteShell;
    const labels = navigationLabels(locale);
    const general = settings.general ?? {};
    const seo = settings.seo ?? {};
    const branding = settings.branding ?? {};
    const colors = settings.colors ?? {};
    const analytics = settings.analytics ?? {};

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
        "Corporate/Catalog",
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

    }, [colors]);

    const [isClient, setIsClient] = useState(false);
    useEffect(() => setIsClient(true), []);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return undefined;
        const targets = document.querySelectorAll('.corporate-main :is(.corporate-section, .catalog-heading, .corporate-document, .group-area-card, .group-company-card, .group-project-card)');
        targets.forEach((target, index) => {
            target.classList.add('motion-reveal');
            target.style.setProperty('--reveal-delay', `${Math.min(index % 4, 3) * 70}ms`);
        });
        const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        }), { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
        targets.forEach((target) => observer.observe(target));
        return () => observer.disconnect();
    }, [component, props.currentUrl]);

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

            <div className="corporate-site corporate-shell" style={{ ...rootStyles, ...corporateTheme(colors) }}>
                <a href="#main-content" className="corporate-skip">{labels.skip}</a>
                {site && <Header site={site} labels={labels} locale={locale} languages={props.languages || []} />}
                {site && <ContactLanyard />}
                <main id="main-content" tabIndex={-1} className="corporate-main">{children}</main>
                {site && <Footer site={site} labels={labels} year={props.currentYear} />}
            </div>

            {isClient && (
                <>
                    <Loading style={rootStyles} />
                    {component.startsWith('Services/') && <QuoteModal style={rootStyles} />}
                    <CookieBanner
                        style={rootStyles}
                        forceVisible={showCookieSettings}
                        onClose={() => setShowCookieSettings(false)}
                    />

                    {component !== 'Home' && <WhatsAppWidget style={rootStyles} data={whatsappData} />}
                </>
            )}
        </>
    );
});

export default AppLayout;
