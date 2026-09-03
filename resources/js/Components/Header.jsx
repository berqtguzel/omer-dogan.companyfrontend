import "../../css/header.css";
import React, { useEffect, useMemo, useRef, useState, memo } from "react";
import { router, usePage } from "@inertiajs/react";
import {
    FaChevronDown,
    FaChevronRight,
    FaPhoneAlt,
    FaBars,
    FaTimes,
    FaFacebook,
    FaInstagram,
    FaEnvelope,
} from "react-icons/fa";
import { FaSquareXTwitter } from "react-icons/fa6";
import { useTranslation } from "react-i18next";
import ThemeToggle from "./ThemeToggle";

import SafeHtml from "@/Components/Common/SafeHtml";
import LanguageSwitcher from "./LanguageSwitcher";
import {
    normalizeExternalUrl,
    renderText,
    renderUrl,
} from "@/utils/renderValue";
import { withImageParams } from "@/utils/imageOptimizer";

/* ============================== helpers ============================== */

function cx(...args) {
    return args.filter(Boolean).join(" ");
}

const getOffset = () => {
    if (typeof document === "undefined") return 0;
    const el = document.querySelector(".site-header");
    const h = el ? el.offsetHeight : 0;
    return Math.max(0, h - 4);
};

const smoothScrollTo = (hash) => {
    if (typeof window === "undefined" || typeof document === "undefined") {
        return;
    }

    if (!hash) return;
    const id = hash.replace("#", "");
    const el = document.getElementById(id);
    if (!el) {
        setTimeout(() => smoothScrollTo(hash), 120);
        return;
    }
    const headerOffset = getOffset();
    const rect = el.getBoundingClientRect();
    const y = rect.top + window.pageYOffset - headerOffset;
    window.scrollTo({ top: y, behavior: "smooth" });
    if (window.history?.replaceState) {
        window.history.replaceState(
            null,
            "",
            `${window.location.pathname}#${id}`,
        );
    }
};

const optimizeLogoUrl = (url, width = 440) => {
    if (!url || /^\d+$/.test(String(url))) return "";

    return withImageParams(url, {
        width,
        quality: 82,
        format: "webp",
    });
};

const getMenuItemKey = (item, fallback) =>
    String(
        item?.id ||
            item?.slug ||
            item?.url ||
            item?.dropdownKey ||
            item?.label ||
            item?.name ||
            fallback,
    );

const LOCATION_MENU_SEGMENT =
    /(^|\/)(locations?|standorte?|lokasyonlar?|emplacements?|ubicaciones?|lokacije|lokalizacje|locatii|locațiile|lokacie)(\/|$)/i;

const withoutLocationItems = (items = []) =>
    items
        .filter(
            (item) =>
                ![item?.url, item?.slug, item?.label, item?.name].some((value) =>
                    LOCATION_MENU_SEGMENT.test(String(value || "")),
                ),
        )
        .map((item) => ({
            ...item,
            children: Array.isArray(item.children)
                ? withoutLocationItems(item.children)
                : item.children,
        }));

const renderMenuItems = (items, navigate) => {
    return items.map((item, idx) => (
        <div key={getMenuItemKey(item, `menu-${idx}`)} className="menu__item">
            <a
                href={item.url}
                className={cx(
                    "menu__link",
                    item.children?.length && "has-children",
                )}
                onClick={navigate(item.url)}
            >
                <SafeHtml html={item.label || item.name} as="span" />

                {item.children && item.children.length > 0 && (
                    <FaChevronRight className="submenu__arrow" />
                )}
            </a>

            {item.children && item.children.length > 0 && (
                <div className="submenu">
                    {renderMenuItems(item.children, navigate)}
                </div>
            )}
        </div>
    ));
};

const renderMobileMenuItems = (
    items,
    navigate,
    mobileAccordions,
    setMobileAccordions,
    closeMenu = true,
    level = 0,
) => {
    return items.map((item, idx) => {
        const key = `mobile-${item.id || `${level}-${idx}`}`;
        const hasChildren =
            Array.isArray(item.children) && item.children.length > 0;

        return (
            <div key={key} className={`acc acc--level-${level}`}>
                <button
                    type="button"
                    className="acc__toggle"
                    aria-expanded={hasChildren ? !!mobileAccordions[key] : undefined}
                    onClick={(e) => {
                        if (hasChildren) {
                            e.preventDefault();
                            setMobileAccordions((prev) => ({
                                ...prev,
                                [key]: !prev[key],
                            }));
                        } else {
                            navigate(item.url, closeMenu)(e);
                        }
                    }}
                >
                    <SafeHtml html={item.label || item.name} as="span" />

                    {hasChildren && (
                        <FaChevronDown
                            className={cx(
                                "acc__chev",
                                mobileAccordions[key] && "rot",
                            )}
                        />
                    )}
                </button>

                {hasChildren && mobileAccordions[key] && (
                    <div className="acc__content open">
                        {renderMobileMenuItems(
                            item.children,
                            navigate,
                            mobileAccordions,
                            setMobileAccordions,
                            closeMenu,
                            level + 1,
                        )}
                    </div>
                )}
            </div>
        );
    });
};

const HeaderInner = memo(({ menu, settings: providedSettings }) => {
    const { props, url } = usePage();
    const isBrowser = typeof window !== "undefined";
    const currentPath = (url || "/").split("?")[0].replace(/\/+$/, "") || "/";

    const global = props?.global || {};
    const settings =
        providedSettings || global?.settings || props?.settings || {};
    const languages = global?.languages || props?.languages || [];
    const currentLocale = props?.locale || "de";
    const headerMenuRaw =
        menu || global?.menus?.header || props?.menus?.header || [];

    const { t } = useTranslation();

    // UI state
    const [openMenu, setOpenMenu] = useState(false);
    const [openDropdown, setOpenDropdown] = useState(null);
    const [mobileAccordions, setMobileAccordions] = useState({});
    const headerRef = useRef(null);
    const closeTimer = useRef(null);
    const HOVER_INTENT = 160;

    // Contact & branding
    const contactInfo =
        settings?.contact?.contact_infos?.[0] || settings?.contact || {};
    const sitePhone = renderText(contactInfo.phone || settings?.phone, "");
    const siteMail = renderText(contactInfo.email || settings?.email);
    const siteName = renderText(
        settings?.branding?.site_name ||
            settings?.general?.site_name ||
            settings?.site_name,
        "",
    );
    const facebookUrl = normalizeExternalUrl(
        settings?.social?.social_facebook || settings?.social_facebook,
    );
    const instagramUrl = normalizeExternalUrl(
        settings?.social?.social_instagram || settings?.social_instagram,
    );
    const twitterUrl = normalizeExternalUrl(
        settings?.social?.social_twitter || settings?.social_twitter,
    );
    const socialLabels = {
        facebook: `${siteName || "Website"} – Facebook`,
        instagram: `${siteName || "Website"} – Instagram`,
        twitter: `${siteName || "Website"} – X`,
    };

    /**
     * Normal logo yalnızca normal logo alanlarından gelir — koyu tema logosuna
     * DÜŞMEZ (o logo genelde beyaz olduğu için açık zeminde kaybolurdu).
     * Koyu tema logosu ise tanımlı değilse normal logoya düşer, aksi hâlde
     * koyu temada header logosuz kalırdı.
     */
    const siteLogos = useMemo(() => {
        const getUrl = (src) => optimizeLogoUrl(renderUrl(src));
        const branding = settings?.branding || {};

        const light =
            getUrl(branding.site_logo_url) ||
            getUrl(settings?.site_logo_url) ||
            getUrl(branding.logo_url) ||
            getUrl(settings?.logo_url) ||
            getUrl(branding.site_favicon_url) ||
            getUrl(settings?.site_favicon_url) ||
            getUrl(branding.favicon_url);

        const dark =
            getUrl(branding.site_dark_logo_url) ||
            getUrl(settings?.site_dark_logo_url) ||
            getUrl(branding.dark_logo_url) ||
            getUrl(settings?.dark_logo_url) ||
            light;

        return { light, dark };
    }, [settings]);

    const navItems = useMemo(() => {
        const items = withoutLocationItems(
            Array.isArray(headerMenuRaw)
                ? headerMenuRaw
                : headerMenuRaw?.items || [],
        );
        if (items.length === 0) {
            return [
                {
                    dropdownKey: "menus-home",
                    label: "Startseite",
                    displayName: "Startseite",
                    url: "/",
                    route: "home",
                    hasChildren: false,
                    isActive: () => currentPath === "/",
                },
            ];
        }
        return items.map((item, i) => ({
            ...item,
            displayName: item.label || item.name || "Menu Item",
            dropdownKey: `menus-${getMenuItemKey(item, i)}`,
            hasChildren:
                Array.isArray(item.children) && item.children.length > 0,
            isActive: () => {
                const targetPath = (item.url || "").replace(/\/+$/, "") || "/";
                return currentPath === targetPath;
            },
        }));
    }, [currentPath, headerMenuRaw]);

    const openDrop = (key) => {
        if (closeTimer.current) clearTimeout(closeTimer.current);
        setOpenDropdown(key);
    };

    const scheduleCloseDrop = () => {
        closeTimer.current = setTimeout(
            () => setOpenDropdown(null),
            HOVER_INTENT,
        );
    };

    const navigate =
        (url, close = false) =>
        (e) => {
            if (!url) return;
            if (!isBrowser) return;

            const raw = String(url).trim();
            const browserPath = window.location.pathname;

            const hasHash = raw.includes("#");
            const parts = raw.split("#");
            const targetPathWithoutHash = parts[0] || "/";
            const hash = hasHash ? `#${parts[1]}` : "";

            const isSamePage =
                raw.startsWith("#") ||
                browserPath.replace(/\/+$/, "") ===
                    targetPathWithoutHash.replace(/\/+$/, "");

            if (hasHash && isSamePage) {
                e.preventDefault();
                smoothScrollTo(hash);
                if (close) setOpenMenu(false);
                return;
            }

            e.preventDefault();

            const localizedUrl = raw.startsWith(`/${currentLocale}`)
                ? raw
                : `/${currentLocale}${raw.startsWith("/") ? raw : `/${raw}`}`;

            router.visit(localizedUrl, {
                preserveScroll: false,
                onSuccess: () => {
                    if (hash) {
                        setTimeout(() => smoothScrollTo(hash), 150);
                    }
                },
            });

            if (close) setOpenMenu(false);
        };

    useEffect(() => {
        if (!isBrowser) return;

        if (window.location.hash) {
            setTimeout(() => {
                smoothScrollTo(window.location.hash);
            }, 300);
        }
    }, [isBrowser]);

    // Kaydırırken header'ı küçültür (.is-stuck stilleri CSS'te tanımlı)
    useEffect(() => {
        if (!isBrowser) return undefined;

        const el = headerRef.current?.closest(".site-header");
        if (!el) return undefined;

        let stuck = null;
        const sync = () => {
            const next = window.scrollY > 24;
            if (next === stuck) return;
            stuck = next;
            el.classList.toggle("is-stuck", next);
        };

        sync();
        window.addEventListener("scroll", sync, { passive: true });

        return () => window.removeEventListener("scroll", sync);
    }, [isBrowser]);

    // Drawer açıkken arka planı kilitle + ESC ile kapat
    useEffect(() => {
        if (!isBrowser || !openMenu) return undefined;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";

        const onKey = (e) => {
            if (e.key === "Escape") setOpenMenu(false);
        };
        window.addEventListener("keydown", onKey);

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener("keydown", onKey);
        };
    }, [isBrowser, openMenu]);

    const socials = [
        { url: facebookUrl, label: socialLabels.facebook, name: "Facebook", Icon: FaFacebook },
        { url: instagramUrl, label: socialLabels.instagram, name: "Instagram", Icon: FaInstagram },
        { url: twitterUrl, label: socialLabels.twitter, name: "X", Icon: FaSquareXTwitter },
    ].filter((item) => item.url);

    return (
        <>
            <div ref={headerRef} className="site-header__shell">
                <div className="navwrap">
                    <div className="container">
                        <div className="navwrap__inner">
                            <a
                                href="/"
                                onClick={navigate("/")}
                                className="brand"
                            >
                                {siteLogos.light && (
                                    <img
                                        src={siteLogos.light}
                                        alt={siteName}
                                        width={210}
                                        height={80}
                                        loading="eager"
                                        decoding="async"
                                        className="brand__logo brand__logo--light"
                                    />
                                )}
                                {siteLogos.dark && (
                                    <img
                                        src={siteLogos.dark}
                                        alt={siteName}
                                        width={210}
                                        height={80}
                                        loading="eager"
                                        decoding="async"
                                        className="brand__logo brand__logo--dark"
                                    />
                                )}
                                {!siteLogos.light && !siteLogos.dark && (
                                    <span className="brand__text">
                                        {siteName}
                                    </span>
                                )}
                            </a>

                            <nav className="nav nav--desktop">
                                {navItems.map((item) => {
                                    const isOpen =
                                        openDropdown === item.dropdownKey;
                                    return (
                                        <div
                                            key={item.dropdownKey}
                                            className={cx(
                                                "nav__item",
                                                item.isActive() && "is-active",
                                            )}
                                            onMouseEnter={() =>
                                                item.hasChildren &&
                                                openDrop(item.dropdownKey)
                                            }
                                            onMouseLeave={() =>
                                                item.hasChildren &&
                                                scheduleCloseDrop()
                                            }
                                        >
                                            <a
                                                href={item.url || "#"}
                                                className={cx(
                                                    "nav__link",
                                                    item.hasChildren &&
                                                        "has-dropdown",
                                                )}
                                                onClick={(e) =>
                                                    navigate(item.url)(e)
                                                }
                                            >
                                                <SafeHtml
                                                    html={item.displayName}
                                                    as="span"
                                                    className="nav__label"
                                                />
                                                {item.hasChildren && (
                                                    <FaChevronDown className="nav__chev" />
                                                )}
                                            </a>

                                            {item.hasChildren && isOpen && (
                                                <div
                                                    className="dropdown"
                                                    onMouseEnter={() =>
                                                        openDrop(
                                                            item.dropdownKey,
                                                        )
                                                    }
                                                >
                                                    <div className="menu">
                                                        {renderMenuItems(
                                                            item.children,
                                                            navigate,
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </nav>

                            <div className="header-controls">
                                {sitePhone && (
                                    <a
                                        className="header-call"
                                        href={`tel:${sitePhone.replace(/\s+/g, "")}`}
                                        data-track-key="header_phone_click"
                                        data-track-name="Header – Telefon"
                                        data-track-location="header_navigation"
                                        data-track-action="call"
                                    >
                                        <span className="header-call__icon">
                                            <FaPhoneAlt aria-hidden="true" />
                                        </span>
                                        <span className="header-call__copy">
                                            <small>
                                                {t(
                                                    "header.call_label",
                                                    "Direkt anrufen",
                                                )}
                                            </small>
                                            <strong>{sitePhone}</strong>
                                        </span>
                                    </a>
                                )}
                                <ThemeToggle />
                                <LanguageSwitcher
                                    currentLang={currentLocale}
                                    languages={languages}
                                />
                            </div>

                            <button
                                type="button"
                                className="hamburger"
                                onClick={() => setOpenMenu(true)}
                                aria-label="Menü öffnen"
                                aria-expanded={openMenu}
                                title="Menü öffnen"
                            >
                                <FaBars
                                    size={18}
                                    aria-hidden="true"
                                    focusable="false"
                                />
                            </button>
                        </div>
                    </div>
                </div>

                {/* Mobile drawer */}
                <div className={cx("drawer", openMenu && "is-open")}>
                    <div
                        className="drawer__backdrop"
                        onClick={() => setOpenMenu(false)}
                    />
                    <aside className="drawer__panel">
                        <div className="drawer__head">
                            {siteLogos.light || siteLogos.dark ? (
                                <>
                                    {siteLogos.light && (
                                        <img
                                            src={siteLogos.light}
                                            alt={`${siteName} logo`}
                                            width={190}
                                            height={40}
                                            loading="lazy"
                                            decoding="async"
                                            className="brand__logo brand__logo--light"
                                        />
                                    )}
                                    {siteLogos.dark && (
                                        <img
                                            src={siteLogos.dark}
                                            alt={`${siteName} logo`}
                                            width={190}
                                            height={40}
                                            loading="lazy"
                                            decoding="async"
                                            className="brand__logo brand__logo--dark"
                                        />
                                    )}
                                </>
                            ) : (
                                <span className="brand__text">{siteName}</span>
                            )}
                            <button
                                type="button"
                                onClick={() => setOpenMenu(false)}
                                aria-label="Menü schließen"
                                title="Menü schließen"
                            >
                                <FaTimes
                                    size={16}
                                    aria-hidden="true"
                                    focusable="false"
                                />
                            </button>
                        </div>

                        <div className="drawer__body">
                            {renderMobileMenuItems(
                                navItems,
                                navigate,
                                mobileAccordions,
                                setMobileAccordions,
                                true,
                            )}
                        </div>

                        <div className="drawer__controls">
                            <div className="topbar__inner">
                                <div className="topbar__left">
                                    {sitePhone && (
                                        <span className="topbar__phone">
                                            <FaPhoneAlt aria-hidden="true" />
                                            <a
                                                href={`tel:${sitePhone.replace(/\s+/g, "")}`}
                                                data-track-key="mobile_header_phone_click"
                                                data-track-name="Mobil Header – Telefon"
                                                data-track-location="mobile_header_drawer"
                                                data-track-action="call"
                                            >
                                                {sitePhone}
                                            </a>
                                        </span>
                                    )}
                                    {siteMail && (
                                        <span className="topbar__mail">
                                            <FaEnvelope aria-hidden="true" />
                                            <a
                                                href={`mailto:${siteMail}`}
                                                data-track-key="mobile_header_email_click"
                                                data-track-name="Mobil Header – E-posta"
                                                data-track-location="mobile_header_drawer"
                                                data-track-action="email"
                                            >
                                                {siteMail}
                                            </a>
                                        </span>
                                    )}
                                </div>

                                {socials.length > 0 && (
                                    <div className="topbar__right">
                                        <div className="social-icons">
                                            {socials.map(
                                                ({ url, label, name, Icon }) => (
                                                    <a
                                                        key={name}
                                                        href={url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        aria-label={label}
                                                        title={label}
                                                        data-track-key={`mobile_header_social_${name.toLowerCase()}`}
                                                        data-track-name={`Mobil Header – ${name}`}
                                                        data-track-location="mobile_header_drawer_social"
                                                        data-track-action="open_social"
                                                    >
                                                        <Icon
                                                            aria-hidden="true"
                                                            focusable="false"
                                                        />
                                                        <span className="sr-only">
                                                            {name}
                                                        </span>
                                                    </a>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
});

HeaderInner.displayName = "HeaderInner";

const Header = memo((props) => {
    return (
        <header className="site-header w-full">
            <HeaderInner {...props} />
        </header>
    );
});

Header.displayName = "Header";

export default Header;
