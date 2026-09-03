import React, { memo, useEffect, useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import {
    FaArrowUp,
    FaEnvelope,
    FaFacebook,
    FaInstagram,
    FaMapMarkerAlt,
    FaPhoneAlt,
} from "react-icons/fa";
import { FaSquareXTwitter } from "react-icons/fa6";
import { useTranslation } from "react-i18next";
import "../../css/Footer.css";
import {
    normalizeExternalUrl,
    renderText,
    renderUrl,
} from "@/utils/renderValue";

const LOCATION_MENU_SEGMENT =
    /(^|\/)(locations?|standorte?|lokasyonlar?|emplacements?|ubicaciones?|lokacije|lokalizacje|locatii|lokacie)(\/|$)/i;

const Footer = memo(() => {
    const { t } = useTranslation();
    const { props } = usePage();
    const settings = props.settings ?? props.global?.settings ?? {};
    const branding = settings.branding ?? {};
    const general = settings.general ?? {};
    const footerSettings = settings.footer ?? {};
    const social = settings.social ?? {};
    const contact = Array.isArray(settings.contact?.contact_infos)
        ? (settings.contact.contact_infos[0] ?? {})
        : (settings.contact ?? {});
    const locale = props.locale || "de";
    const currentYear = props.currentYear || new Date().getFullYear();

    const siteName = renderText(
        general.site_name || branding.site_name,
        "Website",
    );
    const description = renderText(
        footerSettings.footer_description || general.site_description,
        t(
            "footer.description",
            "Professionelle Lösungen, persönlich geplant und zuverlässig umgesetzt.",
        ),
    );

    const logoCandidates = [
        branding.site_dark_logo_url,
        settings.site_dark_logo_url,
        branding.dark_logo_url,
        branding.site_logo_url,
        settings.site_logo_url,
        branding.logo_url,
    ]
        .map((logo) => renderUrl(logo))
        .filter((logo, index, logos) => logo && logos.indexOf(logo) === index);
    const logoCandidatesKey = logoCandidates.join("|");
    const [logoIndex, setLogoIndex] = useState(0);
    const logoUrl = logoCandidates[logoIndex] || "";

    useEffect(() => setLogoIndex(0), [logoCandidatesKey]);

    const localize = (rawUrl) => {
        if (/^https?:\/\//i.test(rawUrl)) return normalizeExternalUrl(rawUrl);
        if (rawUrl === `/${locale}` || rawUrl.startsWith(`/${locale}/`)) {
            return rawUrl;
        }
        return `/${locale}${rawUrl.startsWith("/") ? rawUrl : `/${rawUrl}`}`;
    };

    const rawMenu = Array.isArray(props.menus?.footer?.items)
        ? props.menus.footer.items
        : Array.isArray(props.menus?.footer)
          ? props.menus.footer
          : [];
    const footerLinks = rawMenu
        .filter((item) => !LOCATION_MENU_SEGMENT.test(String(item?.url || "")))
        .map((item) => ({
            label: renderText(item.label || item.name),
            url: localize(renderUrl(item.url)),
        }))
        .filter((item) => item.label && item.url);

    // en çok aranan hizmetler — API'den gelen kategoriler
    const categories = (
        Array.isArray(props.global?.categories) ? props.global.categories : []
    )
        .slice(0, 6)
        .map((category) => ({
            label: renderText(category.name),
            url: localize(`/${renderText(category.slug)}`),
        }))
        .filter((item) => item.label && item.url);

    const phone = renderText(contact.phone || settings.phone);
    const email = renderText(contact.email || settings.email);
    const address = renderText(
        contact.address ||
            [contact.street, contact.postal_code, contact.city]
                .filter(Boolean)
                .join(" "),
    );
    const cleanPhone = phone.replace(/[^+\d]/g, "");

    const socialLinks = [
        {
            name: "Facebook",
            url: normalizeExternalUrl(social.social_facebook),
            icon: FaFacebook,
        },
        {
            name: "Instagram",
            url: normalizeExternalUrl(social.social_instagram),
            icon: FaInstagram,
        },
        {
            name: "X",
            url: normalizeExternalUrl(social.social_twitter),
            icon: FaSquareXTwitter,
        },
    ].filter((item) => item.url);

    const scrollToTop = () => {
        if (typeof window !== "undefined") {
            window.scrollTo({ top: 0, behavior: "smooth" });
        }
    };

    return (
        <footer className="ftr">
            <div className="ftr__inner container">
                <div className="ftr__grid">
                    {/* brand + contact */}
                    <div className="ftr__brand">
                        {logoUrl ? (
                            <img
                                src={logoUrl}
                                alt={siteName}
                                className="ftr__logo"
                                width={220}
                                height={64}
                                loading="lazy"
                                decoding="async"
                                onError={() =>
                                    setLogoIndex((index) => index + 1)
                                }
                            />
                        ) : (
                            <span className="ftr__wordmark">{siteName}</span>
                        )}

                        <p className="ftr__description">{description}</p>

                        <ul className="ftr__contact">
                            {phone && (
                                <li>
                                    <a
                                        href={`tel:${cleanPhone}`}
                                        data-track-key="footer_phone_click"
                                        data-track-name="Footer – Telefon"
                                        data-track-location="footer"
                                        data-track-action="call"
                                    >
                                        <span className="ftr__contact-icon">
                                            <FaPhoneAlt aria-hidden="true" />
                                        </span>
                                        <span>{phone}</span>
                                    </a>
                                </li>
                            )}
                            {email && (
                                <li>
                                    <a
                                        href={`mailto:${email}`}
                                        data-track-key="footer_email_click"
                                        data-track-name="Footer – E-posta"
                                        data-track-location="footer"
                                        data-track-action="email"
                                    >
                                        <span className="ftr__contact-icon">
                                            <FaEnvelope aria-hidden="true" />
                                        </span>
                                        <span>{email}</span>
                                    </a>
                                </li>
                            )}
                            {address && (
                                <li>
                                    <span className="ftr__contact-static">
                                        <span className="ftr__contact-icon">
                                            <FaMapMarkerAlt aria-hidden="true" />
                                        </span>
                                        <span>{address}</span>
                                    </span>
                                </li>
                            )}
                        </ul>
                    </div>

                    {footerLinks.length > 0 && (
                        <nav className="ftr__col" aria-label="Footer">
                            <h3 className="ftr__heading">
                                {t("footer.navigation", "Navigation")}
                            </h3>
                            <ul className="ftr__links">
                                {footerLinks.map((link) => (
                                    <li key={`${link.url}-${link.label}`}>
                                        <Link href={link.url}>{link.label}</Link>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    )}

                    {categories.length > 0 && (
                        <nav
                            className="ftr__col"
                            aria-label={t("footer.services", "Leistungen")}
                        >
                            <h3 className="ftr__heading">
                                {t("footer.services", "Leistungen")}
                            </h3>
                            <ul className="ftr__links">
                                {categories.map((link) => (
                                    <li key={`${link.url}-${link.label}`}>
                                        <Link href={link.url}>{link.label}</Link>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    )}

                    {socialLinks.length > 0 && (
                        <div className="ftr__col">
                            <h3 className="ftr__heading">
                                {t("footer.social", "Social Media")}
                            </h3>
                            <div className="ftr__social">
                                {socialLinks.map(({ name, url, icon: Icon }) => (
                                    <a
                                        key={name}
                                        href={url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label={`${siteName} – ${name}`}
                                        title={name}
                                        data-track-key={`footer_social_${name.toLowerCase()}`}
                                        data-track-name={`Footer – ${name}`}
                                        data-track-location="footer_social"
                                        data-track-action="open_social"
                                    >
                                        <Icon aria-hidden="true" />
                                        <span className="sr-only">{name}</span>
                                    </a>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                <div className="ftr__bottom">
                    <p className="ftr__copy">
                        © {currentYear} {siteName}.{" "}
                        {renderText(footerSettings.footer_copyright)}
                    </p>

                    <button
                        type="button"
                        className="ftr__top"
                        onClick={scrollToTop}
                    >
                        <span>{t("footer.back_to_top", "Nach oben")}</span>
                        <FaArrowUp aria-hidden="true" />
                    </button>
                </div>
            </div>
        </footer>
    );
});

Footer.displayName = "Footer";

export default Footer;
