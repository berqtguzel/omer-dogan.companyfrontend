import { jsxs, jsx, Fragment } from "react/jsx-runtime";
import { useEffect, useContext, createContext, useState, useMemo, memo, useRef } from "react";
import { usePage, Head, router, Link } from "@inertiajs/react";
import { FaFacebook, FaInstagram, FaChevronDown, FaPhoneAlt, FaBars, FaTimes, FaEnvelope, FaChevronRight, FaMapMarkerAlt, FaArrowUp, FaCookieBite, FaCheck } from "react-icons/fa";
import { FaSquareXTwitter } from "react-icons/fa6";
import { useTranslation } from "react-i18next";
import parse, { Element, domToReact } from "html-react-parser";
import DOMPurify from "isomorphic-dompurify";
import { createPortal } from "react-dom";
import Cookies from "js-cookie";
import { FiX, FiCheck } from "react-icons/fi";
const isAbsoluteUrl = (url) => /^https?:\/\//i.test(url || "");
const getBrowserOrigin = () => {
  if (typeof window === "undefined") {
    return "";
  }
  return window.location.origin;
};
const normalizeOrigin = (origin = "") => {
  if (!origin) {
    return getBrowserOrigin();
  }
  try {
    return new URL(origin).origin;
  } catch {
    return origin.replace(/\/$/, "");
  }
};
const normalizeUrl = (url, origin = "") => {
  if (!url) {
    return "";
  }
  if (isAbsoluteUrl(url)) {
    return url;
  }
  const safeOrigin = normalizeOrigin(origin);
  if (!safeOrigin) {
    return url;
  }
  return `${safeOrigin}${url.startsWith("/") ? url : `/${url}`}`;
};
function SeoHead({
  title,
  description,
  keywords,
  canonical,
  image,
  ogTitle,
  ogDescription,
  type = "website",
  locale = "de",
  alternates = [],
  xDefault,
  origin,
  noindex = false,
  favicon
}) {
  var _a, _b;
  const pageProps = usePage().props || {};
  const canonicalBaseUrl = ((_a = pageProps.tenantSeo) == null ? void 0 : _a.canonicalBaseUrl) || "";
  const backendCanonicalUrl = ((_b = pageProps.tenantSeo) == null ? void 0 : _b.canonicalUrl) || "";
  const canonicalUrl = noindex ? "" : backendCanonicalUrl;
  const imageUrl = normalizeUrl(image, origin);
  const faviconUrl = normalizeUrl(favicon, origin);
  const tenantUrl = (url) => {
    if (!url || !canonicalBaseUrl) return "";
    try {
      const parsed = new URL(url, canonicalBaseUrl);
      let path = parsed.pathname.replace(/\/{2,}/g, "/").replace(/^\/public(?=\/|$)/i, "");
      path = path || "/";
      if (/^\/[a-z]{2}\/(?:home|homepage|startseite)\/?$/i.test(path)) {
        path = `/${path.split("/")[1].toLowerCase()}/`;
      }
      return `${canonicalBaseUrl}${path}`;
    } catch {
      return "";
    }
  };
  const xDefaultUrl = tenantUrl(xDefault || canonicalUrl);
  const socialTitle = ogTitle || title;
  const socialDescription = ogDescription || description;
  return /* @__PURE__ */ jsxs(Head, { title, children: [
    description && /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "description",
        name: "description",
        content: description
      }
    ),
    keywords && /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "keywords",
        name: "keywords",
        content: keywords
      }
    ),
    canonicalUrl && /* @__PURE__ */ jsx(
      "link",
      {
        "head-key": "canonical",
        rel: "canonical",
        href: canonicalUrl
      }
    ),
    noindex && /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "robots",
        name: "robots",
        content: "noindex, follow"
      }
    ),
    !noindex && /* @__PURE__ */ jsx("meta", { "head-key": "robots", name: "robots", content: "index, follow" }),
    faviconUrl && /* @__PURE__ */ jsx("link", { "head-key": "favicon", rel: "icon", href: faviconUrl }),
    faviconUrl && /* @__PURE__ */ jsx(
      "link",
      {
        "head-key": "shortcut-icon",
        rel: "shortcut icon",
        href: faviconUrl
      }
    ),
    faviconUrl && /* @__PURE__ */ jsx("link", { "head-key": "apple-touch-icon", rel: "apple-touch-icon", href: faviconUrl }),
    alternates.map((alternate) => {
      const href = tenantUrl(alternate.href);
      if (!alternate.code || !href) {
        return null;
      }
      return /* @__PURE__ */ jsx(
        "link",
        {
          "head-key": `alternate-${alternate.code}`,
          rel: "alternate",
          hreflang: alternate.code,
          href
        },
        alternate.code
      );
    }),
    xDefaultUrl && /* @__PURE__ */ jsx(
      "link",
      {
        "head-key": "alternate-x-default",
        rel: "alternate",
        hreflang: "x-default",
        href: xDefaultUrl
      }
    ),
    socialTitle && /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "og:title",
        property: "og:title",
        content: socialTitle
      }
    ),
    socialDescription && /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "og:description",
        property: "og:description",
        content: socialDescription
      }
    ),
    canonicalUrl && /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "og:url",
        property: "og:url",
        content: canonicalUrl
      }
    ),
    /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "og:type",
        property: "og:type",
        content: type
      }
    ),
    /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "og:locale",
        property: "og:locale",
        content: locale
      }
    ),
    imageUrl && /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "og:image",
        property: "og:image",
        content: imageUrl
      }
    ),
    /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "twitter:card",
        name: "twitter:card",
        content: imageUrl ? "summary_large_image" : "summary"
      }
    ),
    socialTitle && /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "twitter:title",
        name: "twitter:title",
        content: socialTitle
      }
    ),
    socialDescription && /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "twitter:description",
        name: "twitter:description",
        content: socialDescription
      }
    ),
    imageUrl && /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "twitter:image",
        name: "twitter:image",
        content: imageUrl
      }
    )
  ] });
}
const clean = (value) => typeof value === "string" ? value.trim() : "";
const enabled = (value) => {
  const normalized = clean(value).toLowerCase();
  return ["1", "true", "t", "yes", "on"].includes(normalized);
};
const isPlaceholder = (value) => enabled(value);
const isGtmId = (value) => /^GTM-[A-Z0-9]+$/i.test(clean(value));
const isGaId = (value) => /^(G|UA)-[A-Z0-9-]+$/i.test(clean(value));
const isAdsId = (value) => /^AW-[A-Z0-9-]+$/i.test(clean(value));
const isHotjarId = (value) => /^\d+$/.test(clean(value));
function TrackingHead({ analytics = {} }) {
  const gtmId = clean(analytics.google_tag_manager_id);
  const gaId = clean(analytics.google_analytics_id);
  const adsId = clean(analytics.google_ads_conversion);
  const hotjarId = clean(analytics.hotjar_id);
  const rawSearchConsole = clean(analytics.google_search_console_property);
  const searchConsole = isPlaceholder(rawSearchConsole) ? "" : rawSearchConsole;
  const useGtm = enabled(analytics.google_tag_manager) && isGtmId(gtmId);
  const useGa = enabled(analytics.google_analytics) && isGaId(gaId);
  const useAds = isAdsId(adsId);
  const useHotjar = isHotjarId(hotjarId);
  useEffect(() => {
    if (typeof window === "undefined") {
      return;
    }
    ({
      google_tag_manager: {
        enabled: enabled(analytics.google_tag_manager)
      },
      google_analytics: {
        enabled: enabled(analytics.google_analytics)
      },
      google_search_console: {
        enabled: enabled(analytics.google_search_console)
      }
    });
  }, [
    analytics,
    adsId,
    gaId,
    gtmId,
    hotjarId,
    searchConsole,
    useAds,
    useGa,
    useGtm,
    useHotjar
  ]);
  const gtmScript = useGtm ? `(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','${gtmId}');` : "";
  const gaScript = useGa ? `window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${gaId}');` : "";
  const adsScript = useAds ? `window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${adsId}');` : "";
  const hotjarScript = useHotjar ? `(function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};h._hjSettings={hjid:${hotjarId},hjsv:6};a=o.getElementsByTagName('head')[0];r=o.createElement('script');r.async=1;r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;a.appendChild(r);})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');` : "";
  return /* @__PURE__ */ jsxs(Head, { children: [
    searchConsole && /* @__PURE__ */ jsx(
      "meta",
      {
        "head-key": "google-site-verification",
        name: "google-site-verification",
        content: searchConsole
      }
    ),
    useGtm && /* @__PURE__ */ jsx(
      "script",
      {
        "head-key": "google-tag-manager",
        dangerouslySetInnerHTML: { __html: gtmScript }
      }
    ),
    useGa && /* @__PURE__ */ jsx(
      "script",
      {
        "head-key": "google-analytics-loader",
        async: true,
        src: `https://www.googletagmanager.com/gtag/js?id=${gaId}`
      }
    ),
    useGa && /* @__PURE__ */ jsx(
      "script",
      {
        "head-key": "google-analytics",
        dangerouslySetInnerHTML: { __html: gaScript }
      }
    ),
    useAds && !useGa && /* @__PURE__ */ jsx(
      "script",
      {
        "head-key": "google-ads-loader",
        async: true,
        src: `https://www.googletagmanager.com/gtag/js?id=${adsId}`
      }
    ),
    useAds && /* @__PURE__ */ jsx(
      "script",
      {
        "head-key": "google-ads-conversion",
        dangerouslySetInnerHTML: { __html: adsScript }
      }
    ),
    useHotjar && /* @__PURE__ */ jsx(
      "script",
      {
        "head-key": "hotjar",
        dangerouslySetInnerHTML: { __html: hotjarScript }
      }
    )
  ] });
}
const ThemeContext = createContext({
  theme: "light",
  setTheme: () => {
  },
  toggleTheme: () => {
  }
});
const useTheme = () => useContext(ThemeContext);
function ThemeToggle() {
  const { theme, toggleTheme } = useTheme();
  return /* @__PURE__ */ jsx(
    "button",
    {
      onClick: toggleTheme,
      className: "theme-toggle",
      "aria-label": `Toggle ${theme === "light" ? "dark" : "light"} mode`,
      children: theme === "light" ? /* @__PURE__ */ jsx(
        "svg",
        {
          xmlns: "http://www.w3.org/2000/svg",
          className: "h-5 w-5",
          viewBox: "0 0 20 20",
          fill: "currentColor",
          children: /* @__PURE__ */ jsx("path", { d: "M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" })
        }
      ) : /* @__PURE__ */ jsx(
        "svg",
        {
          xmlns: "http://www.w3.org/2000/svg",
          className: "h-5 w-5 text-[#4087F6]",
          viewBox: "0 0 20 20",
          fill: "currentColor",
          children: /* @__PURE__ */ jsx(
            "path",
            {
              fillRule: "evenodd",
              d: "M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z",
              clipRule: "evenodd"
            }
          )
        }
      )
    }
  );
}
const normalizeRichTextHeadings = (html = "") => String(html).replace(/<h1(\s[^>]*)?>/gi, "<h2$1>").replace(/<\/h1>/gi, "</h2>");
const normalizeInlineHtml = (html = "") => normalizeRichTextHeadings(html).replace(
  /<\/?(?:h[1-6]|p|div)(?:\s[^>]*)?>/gi,
  ""
);
function safeParse(html, options) {
  const clean2 = DOMPurify.sanitize(normalizeRichTextHeadings(html || ""), {
    ALLOWED_TAGS: [
      "p",
      "strong",
      "em",
      "a",
      "ul",
      "ol",
      "li",
      "br",
      "h1",
      "h2",
      "h3",
      "h4",
      "h5",
      "h6",
      "blockquote",
      "img",
      "iframe",
      "div",
      "span",
      "small",
      "code",
      "figure",
      "figcaption"
    ],
    ALLOWED_ATTR: [
      "href",
      "title",
      "target",
      "rel",
      "src",
      "alt",
      "width",
      "height",
      "loading",
      "allow",
      "allowfullscreen",
      "class",
      "id"
    ]
  });
  const replace = (node) => {
    if (node instanceof Element && node.name === "a") {
      const props = node.attribs || {};
      const href = props.href || "";
      const isExternal = /^https?:\/\//i.test(href);
      if (isExternal) {
        return /* @__PURE__ */ jsx("a", { ...props, target: "_blank", rel: "noopener noreferrer", children: domToReact(node.children) });
      }
    }
    if (node instanceof Element && (node.name === "script" || node.name === "style")) {
      return /* @__PURE__ */ jsx(Fragment, {});
    }
    return void 0;
  };
  return parse(clean2, { replace, ...{} });
}
function SafeHtml({
  html,
  as: Tag = "span",
  inline = false,
  ...rest
}) {
  if (typeof html !== "string" && typeof html !== "number") return null;
  if (!html) return null;
  return /* @__PURE__ */ jsx(Tag, { ...rest, suppressHydrationWarning: true, children: safeParse(inline ? normalizeInlineHtml(html) : html) });
}
function renderText(value, fallback = "") {
  if (typeof value === "string" || typeof value === "number") {
    return value;
  }
  return fallback;
}
function renderUrl(value, fallback = "") {
  const raw = (value == null ? void 0 : value.url) || (value == null ? void 0 : value.path) || value;
  if (typeof raw === "number") {
    return String(raw);
  }
  if (typeof raw !== "string") {
    return fallback;
  }
  return raw.trim();
}
const canonicalSocialHosts = {
  "facebook.com": "www.facebook.com",
  "m.facebook.com": "www.facebook.com",
  "instagram.com": "www.instagram.com",
  "linkedin.com": "www.linkedin.com",
  "tiktok.com": "www.tiktok.com",
  "twitter.com": "x.com",
  "www.twitter.com": "x.com",
  "www.x.com": "x.com",
  "youtube.com": "www.youtube.com"
};
function normalizeExternalUrl(value, fallback = "") {
  const raw = renderUrl(value, fallback);
  if (!raw) return fallback;
  const isProtocolRelative = raw.startsWith("//");
  const isHttpUrl = /^https?:\/\//i.test(raw);
  const isBareWebUrl = /^(?:www\.)?(?:facebook|instagram|linkedin|tiktok|twitter|x|youtube)\.com(?:[/?#]|$)/i.test(
    raw
  );
  if (!isProtocolRelative && !isHttpUrl && !isBareWebUrl) {
    return raw;
  }
  try {
    const url = new URL(
      isProtocolRelative ? `https:${raw}` : isHttpUrl ? raw : `https://${raw}`
    );
    const hostname = url.hostname.toLowerCase();
    url.protocol = "https:";
    url.hostname = canonicalSocialHosts[hostname] || hostname;
    return url.toString();
  } catch {
    return raw;
  }
}
function Loading({ style }) {
  var _a, _b;
  const { t } = useTranslation();
  const { props } = usePage();
  const [mounted, setMounted] = useState(false);
  const [active, setActive] = useState(false);
  const branding = ((_a = props == null ? void 0 : props.settings) == null ? void 0 : _a.branding) ?? {};
  const settings = (props == null ? void 0 : props.settings) ?? {};
  const logo = useMemo(
    () => [
      branding.site_logo_url,
      settings.site_logo_url,
      branding.logo_url,
      branding.site_dark_logo_url,
      settings.site_dark_logo_url,
      branding.dark_logo_url
    ].map((value) => renderUrl(value)).find(Boolean) || "",
    [branding, settings]
  );
  const siteName = renderText(
    (_b = settings == null ? void 0 : settings.general) == null ? void 0 : _b.site_name,
    renderText(branding.site_name, "Website")
  );
  const message = t("ui.loading.message") || "Yükleniyor...";
  useEffect(() => setMounted(true), []);
  useEffect(() => {
    if (!mounted) return void 0;
    let showTimer;
    let hideTimer;
    let isNavigating = false;
    const handleStart = () => {
      isNavigating = true;
      window.clearTimeout(showTimer);
      window.clearTimeout(hideTimer);
      showTimer = window.setTimeout(() => {
        if (isNavigating) setActive(true);
      }, 120);
    };
    const handleEnd = () => {
      isNavigating = false;
      window.clearTimeout(showTimer);
      hideTimer = window.setTimeout(() => setActive(false), 140);
    };
    const unsubscribeStart = router.on("start", handleStart);
    const unsubscribeFinish = router.on("finish", handleEnd);
    const unsubscribeError = router.on("error", handleEnd);
    return () => {
      window.clearTimeout(showTimer);
      window.clearTimeout(hideTimer);
      unsubscribeStart == null ? void 0 : unsubscribeStart();
      unsubscribeFinish == null ? void 0 : unsubscribeFinish();
      unsubscribeError == null ? void 0 : unsubscribeError();
    };
  }, [mounted]);
  if (!mounted || !active) return null;
  return /* @__PURE__ */ jsx(
    "div",
    {
      className: "oi-loading",
      style,
      role: "status",
      "aria-live": "polite",
      "aria-label": message,
      children: /* @__PURE__ */ jsxs("div", { className: "oi-loading__surface", children: [
        /* @__PURE__ */ jsxs("div", { className: "oi-loading__orbit", "aria-hidden": "true", children: [
          /* @__PURE__ */ jsx("span", {}),
          /* @__PURE__ */ jsx("span", {}),
          /* @__PURE__ */ jsx("span", {})
        ] }),
        /* @__PURE__ */ jsx("div", { className: "oi-loading__brand", "aria-hidden": "true", children: logo ? /* @__PURE__ */ jsx("img", { src: logo, alt: "", className: "oi-loading__logo" }) : /* @__PURE__ */ jsx("span", { className: "oi-loading__monogram", children: siteName.charAt(0).toUpperCase() }) }),
        /* @__PURE__ */ jsxs("div", { className: "oi-loading__copy", children: [
          /* @__PURE__ */ jsx("strong", { children: siteName }),
          /* @__PURE__ */ jsx("span", { children: message })
        ] }),
        /* @__PURE__ */ jsx("div", { className: "oi-loading__track", "aria-hidden": "true", children: /* @__PURE__ */ jsx("span", {}) })
      ] })
    }
  );
}
const normalizeLang$1 = (code) => String(code || "").toLowerCase().split("-")[0];
const SUPPORTED_LOCALES = /* @__PURE__ */ new Set([
  "de",
  "en",
  "tr",
  "ru",
  "fr",
  "es",
  "it",
  "pt",
  "ro",
  "pl",
  "cs",
  "cz",
  "sk",
  "bg",
  "hr"
]);
function buildLocalizedPath(pathname, language) {
  const requested = normalizeLang$1(language);
  const code = requested === "cz" ? "cs" : requested;
  const segments = String(pathname || "/").split("/").filter(Boolean);
  while (segments.length && SUPPORTED_LOCALES.has(normalizeLang$1(segments[0]))) {
    segments.shift();
  }
  return `/${[code, ...segments].filter(Boolean).join("/")}${segments.length ? "" : "/"}`;
}
function normalizeLang(code) {
  return String(code || "").toLowerCase().split("-")[0];
}
const flagMap = {
  en: "gb",
  cs: "cz"
};
const LanguageSwitcher = ({ currentLang, languages }) => {
  const { i18n, t } = useTranslation();
  const [open, setOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const normalizedCurrent = normalizeLang(currentLang);
  useEffect(() => {
    const handleKey = (e) => {
      if (e.key === "Escape") setOpen(false);
    };
    if (open) {
      window.addEventListener("keydown", handleKey);
    }
    return () => {
      window.removeEventListener("keydown", handleKey);
    };
  }, [open]);
  useEffect(() => {
    if (!open || typeof document === "undefined") return void 0;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, [open]);
  const handleLanguageChange = async (codeNorm) => {
    if (codeNorm === normalizedCurrent) return;
    setIsLoading(true);
    const newUrl = `${buildLocalizedPath(window.location.pathname, codeNorm)}${window.location.search}${window.location.hash}`;
    Cookies.set("locale", codeNorm, { path: "/", expires: 365 });
    try {
      await i18n.changeLanguage(codeNorm);
    } catch (e) {
    }
    setTimeout(() => {
      router.visit(newUrl, {
        replace: true,
        preserveState: false,
        preserveScroll: false
      });
    }, 150);
  };
  if (!languages || languages.length <= 1) return null;
  const activeLang = languages.find((l) => normalizeLang(l.code) === normalizedCurrent) || languages[0];
  return /* @__PURE__ */ jsxs(Fragment, { children: [
    /* @__PURE__ */ jsxs(
      "button",
      {
        type: "button",
        className: "lang-switch__btn",
        onClick: () => setOpen(true),
        "aria-label": "Sprache auswählen",
        title: "Sprache auswählen",
        children: [
          /* @__PURE__ */ jsx("span", { className: "lang-switch__btn-flag", children: /* @__PURE__ */ jsx(
            "img",
            {
              src: `https://flagcdn.com/w40/${flagMap[normalizeLang(activeLang.code)] || normalizeLang(activeLang.code)}.png`,
              alt: "",
              width: 40,
              height: 30,
              loading: "lazy",
              decoding: "async",
              "aria-hidden": "true",
              onError: (e) => {
                e.target.src = "https://flagcdn.com/w40/un.png";
              }
            }
          ) }),
          /* @__PURE__ */ jsx("span", { className: "lang-switch__btn-code", children: normalizeLang(activeLang.code).toUpperCase() })
        ]
      }
    ),
    open && typeof document !== "undefined" && createPortal(
      /* @__PURE__ */ jsxs("div", { className: "lang-modal", children: [
        /* @__PURE__ */ jsx(
          "div",
          {
            className: "lang-modal__backdrop",
            onClick: () => setOpen(false)
          }
        ),
        /* @__PURE__ */ jsxs(
          "div",
          {
            className: "lang-modal__content",
            onClick: (e) => e.stopPropagation(),
            role: "dialog",
            "aria-modal": "true",
            "aria-labelledby": "language-dialog-title",
            children: [
              /* @__PURE__ */ jsxs("div", { className: "lang-modal__header", children: [
                /* @__PURE__ */ jsxs("div", { children: [
                  /* @__PURE__ */ jsx(
                    "h2",
                    {
                      id: "language-dialog-title",
                      className: "lang-modal__title",
                      children: t("header.select_language", "Sprache auswählen")
                    }
                  ),
                  /* @__PURE__ */ jsxs("span", { className: "lang-modal__subtitle", children: [
                    languages.length,
                    " ",
                    t("header.languages", "Sprachen")
                  ] })
                ] }),
                /* @__PURE__ */ jsx(
                  "button",
                  {
                    type: "button",
                    className: "lang-modal__close-btn",
                    onClick: () => setOpen(false),
                    "aria-label": "Sprachauswahl schließen",
                    title: "Sprachauswahl schließen",
                    children: /* @__PURE__ */ jsx(
                      FiX,
                      {
                        size: 20,
                        "aria-hidden": "true",
                        focusable: "false"
                      }
                    )
                  }
                )
              ] }),
              isLoading && /* @__PURE__ */ jsx("div", { className: "lang-modal__loading", children: /* @__PURE__ */ jsx(Loading, {}) }),
              /* @__PURE__ */ jsx(
                "div",
                {
                  className: `lang-modal__list ${isLoading ? "disabled" : ""}`,
                  children: languages.map((l) => {
                    const codeNorm = normalizeLang(l.code);
                    const isActive = codeNorm === normalizedCurrent;
                    const flagCode = flagMap[codeNorm] || codeNorm;
                    const label = l.label || codeNorm.toUpperCase();
                    return /* @__PURE__ */ jsxs(
                      "button",
                      {
                        className: `lang-modal__item ${isActive ? "is-active" : ""}`,
                        onClick: () => handleLanguageChange(codeNorm),
                        disabled: isLoading,
                        children: [
                          /* @__PURE__ */ jsx("div", { className: "lang-modal__flag", children: /* @__PURE__ */ jsx(
                            "img",
                            {
                              src: `https://flagcdn.com/w40/${flagCode}.png`,
                              alt: `${label} flag`,
                              width: 40,
                              height: 30,
                              loading: "lazy",
                              decoding: "async",
                              onError: (e) => {
                                e.target.src = "https://flagcdn.com/w40/un.png";
                              }
                            }
                          ) }),
                          /* @__PURE__ */ jsxs("div", { className: "lang-modal__info", children: [
                            /* @__PURE__ */ jsx("span", { className: "lang-modal__name", children: label }),
                            /* @__PURE__ */ jsx("span", { className: "lang-modal__code", children: codeNorm })
                          ] }),
                          isActive && /* @__PURE__ */ jsx("span", { className: "lang-modal__check", children: /* @__PURE__ */ jsx(
                            FiCheck,
                            {
                              size: 13,
                              strokeWidth: 3,
                              "aria-hidden": "true",
                              focusable: "false"
                            }
                          ) })
                        ]
                      },
                      l.code
                    );
                  })
                }
              )
            ]
          }
        )
      ] }),
      document.body
    )
  ] });
};
function withImageParams(url, options = {}) {
  if (!url || url.startsWith("data:") || url.startsWith("blob:")) return url;
  const {
    width = null,
    height = null,
    quality = 80,
    format = "webp",
    fit = null
  } = options;
  try {
    const isAbsolute = /^https?:\/\//i.test(url);
    const urlObj = new URL(isAbsolute ? url : `https://local.invalid${url}`);
    if (format) urlObj.searchParams.set("format", format);
    if (width) urlObj.searchParams.set("w", String(width));
    if (height) urlObj.searchParams.set("h", String(height));
    if (quality) urlObj.searchParams.set("q", String(quality));
    if (fit) urlObj.searchParams.set("fit", fit);
    return isAbsolute ? urlObj.toString() : `${urlObj.pathname}${urlObj.search}`;
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
function buildResponsiveImage(url, options = {}) {
  if (!url) return { src: "", srcSet: "" };
  const {
    widths = [480, 800, 1200],
    width = widths[widths.length - 1] || null,
    quality = 75,
    format = "webp",
    fit = "cover",
    transform = true
  } = options;
  const source = String(url);
  const isLocalMediaCache = /^\/?(?:storage\/)?media-cache\//i.test(source) || /\/storage\/media-cache\//i.test(source);
  const canTransform = transform && !isLocalMediaCache && !source.startsWith("data:") && !source.startsWith("blob:") && !/\.svg(?:\?|$)/i.test(source);
  if (!canTransform) {
    return { src: source, srcSet: "" };
  }
  const normalizedWidths = [...new Set(widths)].map(Number).filter((value) => Number.isFinite(value) && value > 0).sort((a, b) => a - b);
  const params = { quality, format, fit };
  const src = withImageParams(source, { ...params, width });
  const srcSet = normalizedWidths.map(
    (candidateWidth) => `${withImageParams(source, {
      ...params,
      width: candidateWidth
    })} ${candidateWidth}w`
  ).join(", ");
  return { src, srcSet };
}
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
  var _a;
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
  if ((_a = window.history) == null ? void 0 : _a.replaceState) {
    window.history.replaceState(
      null,
      "",
      `${window.location.pathname}#${id}`
    );
  }
};
const optimizeLogoUrl = (url, width = 440) => {
  if (!url || /^\d+$/.test(String(url))) return "";
  return withImageParams(url, {
    width,
    quality: 82,
    format: "webp"
  });
};
const getMenuItemKey = (item, fallback) => String(
  (item == null ? void 0 : item.id) || (item == null ? void 0 : item.slug) || (item == null ? void 0 : item.url) || (item == null ? void 0 : item.dropdownKey) || (item == null ? void 0 : item.label) || (item == null ? void 0 : item.name) || fallback
);
const LOCATION_MENU_SEGMENT$1 = /(^|\/)(locations?|standorte?|lokasyonlar?|emplacements?|ubicaciones?|lokacije|lokalizacje|locatii|locațiile|lokacie)(\/|$)/i;
const withoutLocationItems = (items = []) => items.filter(
  (item) => ![item == null ? void 0 : item.url, item == null ? void 0 : item.slug, item == null ? void 0 : item.label, item == null ? void 0 : item.name].some(
    (value) => LOCATION_MENU_SEGMENT$1.test(String(value || ""))
  )
).map((item) => ({
  ...item,
  children: Array.isArray(item.children) ? withoutLocationItems(item.children) : item.children
}));
const renderMenuItems = (items, navigate) => {
  return items.map((item, idx) => {
    var _a;
    return /* @__PURE__ */ jsxs("div", { className: "menu__item", children: [
      /* @__PURE__ */ jsxs(
        "a",
        {
          href: item.url,
          className: cx(
            "menu__link",
            ((_a = item.children) == null ? void 0 : _a.length) && "has-children"
          ),
          onClick: navigate(item.url),
          children: [
            /* @__PURE__ */ jsx(SafeHtml, { html: item.label || item.name, as: "span" }),
            item.children && item.children.length > 0 && /* @__PURE__ */ jsx(FaChevronRight, { className: "submenu__arrow" })
          ]
        }
      ),
      item.children && item.children.length > 0 && /* @__PURE__ */ jsx("div", { className: "submenu", children: renderMenuItems(item.children, navigate) })
    ] }, getMenuItemKey(item, `menu-${idx}`));
  });
};
const renderMobileMenuItems = (items, navigate, mobileAccordions, setMobileAccordions, closeMenu = true, level = 0) => {
  return items.map((item, idx) => {
    const key = `mobile-${item.id || `${level}-${idx}`}`;
    const hasChildren = Array.isArray(item.children) && item.children.length > 0;
    return /* @__PURE__ */ jsxs("div", { className: `acc acc--level-${level}`, children: [
      /* @__PURE__ */ jsxs(
        "button",
        {
          type: "button",
          className: "acc__toggle",
          "aria-expanded": hasChildren ? !!mobileAccordions[key] : void 0,
          onClick: (e) => {
            if (hasChildren) {
              e.preventDefault();
              setMobileAccordions((prev) => ({
                ...prev,
                [key]: !prev[key]
              }));
            } else {
              navigate(item.url, closeMenu)(e);
            }
          },
          children: [
            /* @__PURE__ */ jsx(SafeHtml, { html: item.label || item.name, as: "span" }),
            hasChildren && /* @__PURE__ */ jsx(
              FaChevronDown,
              {
                className: cx(
                  "acc__chev",
                  mobileAccordions[key] && "rot"
                )
              }
            )
          ]
        }
      ),
      hasChildren && mobileAccordions[key] && /* @__PURE__ */ jsx("div", { className: "acc__content open", children: renderMobileMenuItems(
        item.children,
        navigate,
        mobileAccordions,
        setMobileAccordions,
        closeMenu,
        level + 1
      ) })
    ] }, key);
  });
};
const HeaderInner = memo(({ menu, settings: providedSettings }) => {
  var _a, _b, _c, _d, _e, _f, _g, _h, _i;
  const { props, url } = usePage();
  const isBrowser = typeof window !== "undefined";
  const currentPath = (url || "/").split("?")[0].replace(/\/+$/, "") || "/";
  const global = (props == null ? void 0 : props.global) || {};
  const settings = providedSettings || (global == null ? void 0 : global.settings) || (props == null ? void 0 : props.settings) || {};
  const languages = (global == null ? void 0 : global.languages) || (props == null ? void 0 : props.languages) || [];
  const currentLocale = (props == null ? void 0 : props.locale) || "de";
  const headerMenuRaw = menu || ((_a = global == null ? void 0 : global.menus) == null ? void 0 : _a.header) || ((_b = props == null ? void 0 : props.menus) == null ? void 0 : _b.header) || [];
  const { t } = useTranslation();
  const [openMenu, setOpenMenu] = useState(false);
  const [openDropdown, setOpenDropdown] = useState(null);
  const [mobileAccordions, setMobileAccordions] = useState({});
  const headerRef = useRef(null);
  const closeTimer = useRef(null);
  const HOVER_INTENT = 160;
  const contactInfo = ((_d = (_c = settings == null ? void 0 : settings.contact) == null ? void 0 : _c.contact_infos) == null ? void 0 : _d[0]) || (settings == null ? void 0 : settings.contact) || {};
  const sitePhone = renderText(contactInfo.phone || (settings == null ? void 0 : settings.phone), "");
  const siteMail = renderText(contactInfo.email || (settings == null ? void 0 : settings.email));
  const siteName = renderText(
    ((_e = settings == null ? void 0 : settings.branding) == null ? void 0 : _e.site_name) || ((_f = settings == null ? void 0 : settings.general) == null ? void 0 : _f.site_name) || (settings == null ? void 0 : settings.site_name),
    ""
  );
  const facebookUrl = normalizeExternalUrl(
    ((_g = settings == null ? void 0 : settings.social) == null ? void 0 : _g.social_facebook) || (settings == null ? void 0 : settings.social_facebook)
  );
  const instagramUrl = normalizeExternalUrl(
    ((_h = settings == null ? void 0 : settings.social) == null ? void 0 : _h.social_instagram) || (settings == null ? void 0 : settings.social_instagram)
  );
  const twitterUrl = normalizeExternalUrl(
    ((_i = settings == null ? void 0 : settings.social) == null ? void 0 : _i.social_twitter) || (settings == null ? void 0 : settings.social_twitter)
  );
  const socialLabels = {
    facebook: `${siteName || "Website"} – Facebook`,
    instagram: `${siteName || "Website"} – Instagram`,
    twitter: `${siteName || "Website"} – X`
  };
  const siteLogos = useMemo(() => {
    const getUrl = (src) => optimizeLogoUrl(renderUrl(src));
    const branding = (settings == null ? void 0 : settings.branding) || {};
    const light = getUrl(branding.site_logo_url) || getUrl(settings == null ? void 0 : settings.site_logo_url) || getUrl(branding.logo_url) || getUrl(settings == null ? void 0 : settings.logo_url) || getUrl(branding.site_favicon_url) || getUrl(settings == null ? void 0 : settings.site_favicon_url) || getUrl(branding.favicon_url);
    const dark = getUrl(branding.site_dark_logo_url) || getUrl(settings == null ? void 0 : settings.site_dark_logo_url) || getUrl(branding.dark_logo_url) || getUrl(settings == null ? void 0 : settings.dark_logo_url) || light;
    return { light, dark };
  }, [settings]);
  const navItems = useMemo(() => {
    const items = withoutLocationItems(
      Array.isArray(headerMenuRaw) ? headerMenuRaw : (headerMenuRaw == null ? void 0 : headerMenuRaw.items) || []
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
          isActive: () => currentPath === "/"
        }
      ];
    }
    return items.map((item, i) => ({
      ...item,
      displayName: item.label || item.name || "Menu Item",
      dropdownKey: `menus-${getMenuItemKey(item, i)}`,
      hasChildren: Array.isArray(item.children) && item.children.length > 0,
      isActive: () => {
        const targetPath = (item.url || "").replace(/\/+$/, "") || "/";
        return currentPath === targetPath;
      }
    }));
  }, [currentPath, headerMenuRaw]);
  const openDrop = (key) => {
    if (closeTimer.current) clearTimeout(closeTimer.current);
    setOpenDropdown(key);
  };
  const scheduleCloseDrop = () => {
    closeTimer.current = setTimeout(
      () => setOpenDropdown(null),
      HOVER_INTENT
    );
  };
  const navigate = (url2, close = false) => (e) => {
    if (!url2) return;
    if (!isBrowser) return;
    const raw = String(url2).trim();
    const browserPath = window.location.pathname;
    const hasHash = raw.includes("#");
    const parts = raw.split("#");
    const targetPathWithoutHash = parts[0] || "/";
    const hash = hasHash ? `#${parts[1]}` : "";
    const isSamePage = raw.startsWith("#") || browserPath.replace(/\/+$/, "") === targetPathWithoutHash.replace(/\/+$/, "");
    if (hasHash && isSamePage) {
      e.preventDefault();
      smoothScrollTo(hash);
      if (close) setOpenMenu(false);
      return;
    }
    e.preventDefault();
    const localizedUrl = raw.startsWith(`/${currentLocale}`) ? raw : `/${currentLocale}${raw.startsWith("/") ? raw : `/${raw}`}`;
    router.visit(localizedUrl, {
      preserveScroll: false,
      onSuccess: () => {
        if (hash) {
          setTimeout(() => smoothScrollTo(hash), 150);
        }
      }
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
  useEffect(() => {
    var _a2;
    if (!isBrowser) return void 0;
    const el = (_a2 = headerRef.current) == null ? void 0 : _a2.closest(".site-header");
    if (!el) return void 0;
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
  useEffect(() => {
    if (!isBrowser || !openMenu) return void 0;
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
    { url: twitterUrl, label: socialLabels.twitter, name: "X", Icon: FaSquareXTwitter }
  ].filter((item) => item.url);
  return /* @__PURE__ */ jsx(Fragment, { children: /* @__PURE__ */ jsxs("div", { ref: headerRef, className: "site-header__shell", children: [
    /* @__PURE__ */ jsx("div", { className: "navwrap", children: /* @__PURE__ */ jsx("div", { className: "container", children: /* @__PURE__ */ jsxs("div", { className: "navwrap__inner", children: [
      /* @__PURE__ */ jsxs(
        "a",
        {
          href: "/",
          onClick: navigate("/"),
          className: "brand",
          children: [
            siteLogos.light && /* @__PURE__ */ jsx(
              "img",
              {
                src: siteLogos.light,
                alt: siteName,
                width: 210,
                height: 80,
                loading: "eager",
                decoding: "async",
                className: "brand__logo brand__logo--light"
              }
            ),
            siteLogos.dark && /* @__PURE__ */ jsx(
              "img",
              {
                src: siteLogos.dark,
                alt: siteName,
                width: 210,
                height: 80,
                loading: "eager",
                decoding: "async",
                className: "brand__logo brand__logo--dark"
              }
            ),
            !siteLogos.light && !siteLogos.dark && /* @__PURE__ */ jsx("span", { className: "brand__text", children: siteName })
          ]
        }
      ),
      /* @__PURE__ */ jsx("nav", { className: "nav nav--desktop", children: navItems.map((item) => {
        const isOpen = openDropdown === item.dropdownKey;
        return /* @__PURE__ */ jsxs(
          "div",
          {
            className: cx(
              "nav__item",
              item.isActive() && "is-active"
            ),
            onMouseEnter: () => item.hasChildren && openDrop(item.dropdownKey),
            onMouseLeave: () => item.hasChildren && scheduleCloseDrop(),
            children: [
              /* @__PURE__ */ jsxs(
                "a",
                {
                  href: item.url || "#",
                  className: cx(
                    "nav__link",
                    item.hasChildren && "has-dropdown"
                  ),
                  onClick: (e) => navigate(item.url)(e),
                  children: [
                    /* @__PURE__ */ jsx(
                      SafeHtml,
                      {
                        html: item.displayName,
                        as: "span",
                        className: "nav__label"
                      }
                    ),
                    item.hasChildren && /* @__PURE__ */ jsx(FaChevronDown, { className: "nav__chev" })
                  ]
                }
              ),
              item.hasChildren && isOpen && /* @__PURE__ */ jsx(
                "div",
                {
                  className: "dropdown",
                  onMouseEnter: () => openDrop(
                    item.dropdownKey
                  ),
                  children: /* @__PURE__ */ jsx("div", { className: "menu", children: renderMenuItems(
                    item.children,
                    navigate
                  ) })
                }
              )
            ]
          },
          item.dropdownKey
        );
      }) }),
      /* @__PURE__ */ jsxs("div", { className: "header-controls", children: [
        sitePhone && /* @__PURE__ */ jsxs(
          "a",
          {
            className: "header-call",
            href: `tel:${sitePhone.replace(/\s+/g, "")}`,
            "data-track-key": "header_phone_click",
            "data-track-name": "Header – Telefon",
            "data-track-location": "header_navigation",
            "data-track-action": "call",
            children: [
              /* @__PURE__ */ jsx("span", { className: "header-call__icon", children: /* @__PURE__ */ jsx(FaPhoneAlt, { "aria-hidden": "true" }) }),
              /* @__PURE__ */ jsxs("span", { className: "header-call__copy", children: [
                /* @__PURE__ */ jsx("small", { children: t(
                  "header.call_label",
                  "Direkt anrufen"
                ) }),
                /* @__PURE__ */ jsx("strong", { children: sitePhone })
              ] })
            ]
          }
        ),
        /* @__PURE__ */ jsx(ThemeToggle, {}),
        /* @__PURE__ */ jsx(
          LanguageSwitcher,
          {
            currentLang: currentLocale,
            languages
          }
        )
      ] }),
      /* @__PURE__ */ jsx(
        "button",
        {
          type: "button",
          className: "hamburger",
          onClick: () => setOpenMenu(true),
          "aria-label": "Menü öffnen",
          "aria-expanded": openMenu,
          title: "Menü öffnen",
          children: /* @__PURE__ */ jsx(
            FaBars,
            {
              size: 18,
              "aria-hidden": "true",
              focusable: "false"
            }
          )
        }
      )
    ] }) }) }),
    /* @__PURE__ */ jsxs("div", { className: cx("drawer", openMenu && "is-open"), children: [
      /* @__PURE__ */ jsx(
        "div",
        {
          className: "drawer__backdrop",
          onClick: () => setOpenMenu(false)
        }
      ),
      /* @__PURE__ */ jsxs("aside", { className: "drawer__panel", children: [
        /* @__PURE__ */ jsxs("div", { className: "drawer__head", children: [
          siteLogos.light || siteLogos.dark ? /* @__PURE__ */ jsxs(Fragment, { children: [
            siteLogos.light && /* @__PURE__ */ jsx(
              "img",
              {
                src: siteLogos.light,
                alt: `${siteName} logo`,
                width: 190,
                height: 40,
                loading: "lazy",
                decoding: "async",
                className: "brand__logo brand__logo--light"
              }
            ),
            siteLogos.dark && /* @__PURE__ */ jsx(
              "img",
              {
                src: siteLogos.dark,
                alt: `${siteName} logo`,
                width: 190,
                height: 40,
                loading: "lazy",
                decoding: "async",
                className: "brand__logo brand__logo--dark"
              }
            )
          ] }) : /* @__PURE__ */ jsx("span", { className: "brand__text", children: siteName }),
          /* @__PURE__ */ jsx(
            "button",
            {
              type: "button",
              onClick: () => setOpenMenu(false),
              "aria-label": "Menü schließen",
              title: "Menü schließen",
              children: /* @__PURE__ */ jsx(
                FaTimes,
                {
                  size: 16,
                  "aria-hidden": "true",
                  focusable: "false"
                }
              )
            }
          )
        ] }),
        /* @__PURE__ */ jsx("div", { className: "drawer__body", children: renderMobileMenuItems(
          navItems,
          navigate,
          mobileAccordions,
          setMobileAccordions,
          true
        ) }),
        /* @__PURE__ */ jsx("div", { className: "drawer__controls", children: /* @__PURE__ */ jsxs("div", { className: "topbar__inner", children: [
          /* @__PURE__ */ jsxs("div", { className: "topbar__left", children: [
            sitePhone && /* @__PURE__ */ jsxs("span", { className: "topbar__phone", children: [
              /* @__PURE__ */ jsx(FaPhoneAlt, { "aria-hidden": "true" }),
              /* @__PURE__ */ jsx(
                "a",
                {
                  href: `tel:${sitePhone.replace(/\s+/g, "")}`,
                  "data-track-key": "mobile_header_phone_click",
                  "data-track-name": "Mobil Header – Telefon",
                  "data-track-location": "mobile_header_drawer",
                  "data-track-action": "call",
                  children: sitePhone
                }
              )
            ] }),
            siteMail && /* @__PURE__ */ jsxs("span", { className: "topbar__mail", children: [
              /* @__PURE__ */ jsx(FaEnvelope, { "aria-hidden": "true" }),
              /* @__PURE__ */ jsx(
                "a",
                {
                  href: `mailto:${siteMail}`,
                  "data-track-key": "mobile_header_email_click",
                  "data-track-name": "Mobil Header – E-posta",
                  "data-track-location": "mobile_header_drawer",
                  "data-track-action": "email",
                  children: siteMail
                }
              )
            ] })
          ] }),
          socials.length > 0 && /* @__PURE__ */ jsx("div", { className: "topbar__right", children: /* @__PURE__ */ jsx("div", { className: "social-icons", children: socials.map(
            ({ url: url2, label, name, Icon }) => /* @__PURE__ */ jsxs(
              "a",
              {
                href: url2,
                target: "_blank",
                rel: "noopener noreferrer",
                "aria-label": label,
                title: label,
                "data-track-key": `mobile_header_social_${name.toLowerCase()}`,
                "data-track-name": `Mobil Header – ${name}`,
                "data-track-location": "mobile_header_drawer_social",
                "data-track-action": "open_social",
                children: [
                  /* @__PURE__ */ jsx(
                    Icon,
                    {
                      "aria-hidden": "true",
                      focusable: "false"
                    }
                  ),
                  /* @__PURE__ */ jsx("span", { className: "sr-only", children: name })
                ]
              },
              name
            )
          ) }) })
        ] }) })
      ] })
    ] })
  ] }) });
});
HeaderInner.displayName = "HeaderInner";
const Header = memo((props) => {
  return /* @__PURE__ */ jsx("header", { className: "site-header w-full", children: /* @__PURE__ */ jsx(HeaderInner, { ...props }) });
});
Header.displayName = "Header";
const LOCATION_MENU_SEGMENT = /(^|\/)(locations?|standorte?|lokasyonlar?|emplacements?|ubicaciones?|lokacije|lokalizacje|locatii|lokacie)(\/|$)/i;
const Footer = memo(() => {
  var _a, _b, _c, _d, _e, _f;
  const { t } = useTranslation();
  const { props } = usePage();
  const settings = props.settings ?? ((_a = props.global) == null ? void 0 : _a.settings) ?? {};
  const branding = settings.branding ?? {};
  const general = settings.general ?? {};
  const footerSettings = settings.footer ?? {};
  const social = settings.social ?? {};
  const contact = Array.isArray((_b = settings.contact) == null ? void 0 : _b.contact_infos) ? settings.contact.contact_infos[0] ?? {} : settings.contact ?? {};
  const locale = props.locale || "de";
  const currentYear = props.currentYear || (/* @__PURE__ */ new Date()).getFullYear();
  const siteName = renderText(
    general.site_name || branding.site_name,
    "Website"
  );
  const description = renderText(
    footerSettings.footer_description || general.site_description,
    t(
      "footer.description",
      "Professionelle Lösungen, persönlich geplant und zuverlässig umgesetzt."
    )
  );
  const logoCandidates = [
    branding.site_dark_logo_url,
    settings.site_dark_logo_url,
    branding.dark_logo_url,
    branding.site_logo_url,
    settings.site_logo_url,
    branding.logo_url
  ].map((logo) => renderUrl(logo)).filter((logo, index, logos) => logo && logos.indexOf(logo) === index);
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
  const rawMenu = Array.isArray((_d = (_c = props.menus) == null ? void 0 : _c.footer) == null ? void 0 : _d.items) ? props.menus.footer.items : Array.isArray((_e = props.menus) == null ? void 0 : _e.footer) ? props.menus.footer : [];
  const footerLinks = rawMenu.filter((item) => !LOCATION_MENU_SEGMENT.test(String((item == null ? void 0 : item.url) || ""))).map((item) => ({
    label: renderText(item.label || item.name),
    url: localize(renderUrl(item.url))
  })).filter((item) => item.label && item.url);
  const categories = (Array.isArray((_f = props.global) == null ? void 0 : _f.categories) ? props.global.categories : []).slice(0, 6).map((category) => ({
    label: renderText(category.name),
    url: localize(`/${renderText(category.slug)}`)
  })).filter((item) => item.label && item.url);
  const phone = renderText(contact.phone || settings.phone);
  const email = renderText(contact.email || settings.email);
  const address = renderText(
    contact.address || [contact.street, contact.postal_code, contact.city].filter(Boolean).join(" ")
  );
  const cleanPhone = phone.replace(/[^+\d]/g, "");
  const socialLinks = [
    {
      name: "Facebook",
      url: normalizeExternalUrl(social.social_facebook),
      icon: FaFacebook
    },
    {
      name: "Instagram",
      url: normalizeExternalUrl(social.social_instagram),
      icon: FaInstagram
    },
    {
      name: "X",
      url: normalizeExternalUrl(social.social_twitter),
      icon: FaSquareXTwitter
    }
  ].filter((item) => item.url);
  const scrollToTop = () => {
    if (typeof window !== "undefined") {
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  };
  return /* @__PURE__ */ jsx("footer", { className: "ftr", children: /* @__PURE__ */ jsxs("div", { className: "ftr__inner container", children: [
    /* @__PURE__ */ jsxs("div", { className: "ftr__grid", children: [
      /* @__PURE__ */ jsxs("div", { className: "ftr__brand", children: [
        logoUrl ? /* @__PURE__ */ jsx(
          "img",
          {
            src: logoUrl,
            alt: siteName,
            className: "ftr__logo",
            width: 220,
            height: 64,
            loading: "lazy",
            decoding: "async",
            onError: () => setLogoIndex((index) => index + 1)
          }
        ) : /* @__PURE__ */ jsx("span", { className: "ftr__wordmark", children: siteName }),
        /* @__PURE__ */ jsx("p", { className: "ftr__description", children: description }),
        /* @__PURE__ */ jsxs("ul", { className: "ftr__contact", children: [
          phone && /* @__PURE__ */ jsx("li", { children: /* @__PURE__ */ jsxs(
            "a",
            {
              href: `tel:${cleanPhone}`,
              "data-track-key": "footer_phone_click",
              "data-track-name": "Footer – Telefon",
              "data-track-location": "footer",
              "data-track-action": "call",
              children: [
                /* @__PURE__ */ jsx("span", { className: "ftr__contact-icon", children: /* @__PURE__ */ jsx(FaPhoneAlt, { "aria-hidden": "true" }) }),
                /* @__PURE__ */ jsx("span", { children: phone })
              ]
            }
          ) }),
          email && /* @__PURE__ */ jsx("li", { children: /* @__PURE__ */ jsxs(
            "a",
            {
              href: `mailto:${email}`,
              "data-track-key": "footer_email_click",
              "data-track-name": "Footer – E-posta",
              "data-track-location": "footer",
              "data-track-action": "email",
              children: [
                /* @__PURE__ */ jsx("span", { className: "ftr__contact-icon", children: /* @__PURE__ */ jsx(FaEnvelope, { "aria-hidden": "true" }) }),
                /* @__PURE__ */ jsx("span", { children: email })
              ]
            }
          ) }),
          address && /* @__PURE__ */ jsx("li", { children: /* @__PURE__ */ jsxs("span", { className: "ftr__contact-static", children: [
            /* @__PURE__ */ jsx("span", { className: "ftr__contact-icon", children: /* @__PURE__ */ jsx(FaMapMarkerAlt, { "aria-hidden": "true" }) }),
            /* @__PURE__ */ jsx("span", { children: address })
          ] }) })
        ] })
      ] }),
      footerLinks.length > 0 && /* @__PURE__ */ jsxs("nav", { className: "ftr__col", "aria-label": "Footer", children: [
        /* @__PURE__ */ jsx("h3", { className: "ftr__heading", children: t("footer.navigation", "Navigation") }),
        /* @__PURE__ */ jsx("ul", { className: "ftr__links", children: footerLinks.map((link) => /* @__PURE__ */ jsx("li", { children: /* @__PURE__ */ jsx(Link, { href: link.url, children: link.label }) }, `${link.url}-${link.label}`)) })
      ] }),
      categories.length > 0 && /* @__PURE__ */ jsxs(
        "nav",
        {
          className: "ftr__col",
          "aria-label": t("footer.services", "Leistungen"),
          children: [
            /* @__PURE__ */ jsx("h3", { className: "ftr__heading", children: t("footer.services", "Leistungen") }),
            /* @__PURE__ */ jsx("ul", { className: "ftr__links", children: categories.map((link) => /* @__PURE__ */ jsx("li", { children: /* @__PURE__ */ jsx(Link, { href: link.url, children: link.label }) }, `${link.url}-${link.label}`)) })
          ]
        }
      ),
      socialLinks.length > 0 && /* @__PURE__ */ jsxs("div", { className: "ftr__col", children: [
        /* @__PURE__ */ jsx("h3", { className: "ftr__heading", children: t("footer.social", "Social Media") }),
        /* @__PURE__ */ jsx("div", { className: "ftr__social", children: socialLinks.map(({ name, url, icon: Icon }) => /* @__PURE__ */ jsxs(
          "a",
          {
            href: url,
            target: "_blank",
            rel: "noopener noreferrer",
            "aria-label": `${siteName} – ${name}`,
            title: name,
            "data-track-key": `footer_social_${name.toLowerCase()}`,
            "data-track-name": `Footer – ${name}`,
            "data-track-location": "footer_social",
            "data-track-action": "open_social",
            children: [
              /* @__PURE__ */ jsx(Icon, { "aria-hidden": "true" }),
              /* @__PURE__ */ jsx("span", { className: "sr-only", children: name })
            ]
          },
          name
        )) })
      ] })
    ] }),
    /* @__PURE__ */ jsxs("div", { className: "ftr__bottom", children: [
      /* @__PURE__ */ jsxs("p", { className: "ftr__copy", children: [
        "© ",
        currentYear,
        " ",
        siteName,
        ".",
        " ",
        renderText(footerSettings.footer_copyright)
      ] }),
      /* @__PURE__ */ jsxs(
        "button",
        {
          type: "button",
          className: "ftr__top",
          onClick: scrollToTop,
          children: [
            /* @__PURE__ */ jsx("span", { children: t("footer.back_to_top", "Nach oben") }),
            /* @__PURE__ */ jsx(FaArrowUp, { "aria-hidden": "true" })
          ]
        }
      )
    ] })
  ] }) });
});
Footer.displayName = "Footer";
const INITIAL_COOKIES = {
  necessary: { required: true },
  analytics: { required: false },
  marketing: { required: false }
};
const CookieBanner = ({ forceVisible = false, onClose }) => {
  const { t } = useTranslation();
  const [isVisible, setIsVisible] = useState(false);
  const [showDetails, setShowDetails] = useState(false);
  const [preferences, setPreferences] = useState({
    necessary: true,
    analytics: false,
    marketing: false
  });
  const [hasConsent, setHasConsent] = useState(
    () => !!Cookies.get("cookie_consent")
  );
  useEffect(() => {
    const consent = Cookies.get("cookie_consent");
    if (consent && !forceVisible) {
      setIsVisible(false);
      setHasConsent(true);
      try {
        setPreferences(JSON.parse(consent));
      } catch {
      }
      return;
    }
    setIsVisible(true);
  }, [forceVisible]);
  const saveConsent = (prefs) => {
    Cookies.set("cookie_consent", JSON.stringify(prefs), {
      expires: 365,
      path: "/",
      sameSite: "Lax"
    });
    setPreferences(prefs);
    setHasConsent(true);
    setIsVisible(false);
    setShowDetails(false);
    if (onClose) onClose();
    window.dispatchEvent(new Event("cookie-saved"));
  };
  const handleAcceptAll = () => saveConsent({ necessary: true, analytics: true, marketing: true });
  const handleRejectAll = () => saveConsent({ necessary: true, analytics: false, marketing: false });
  const handleSaveSelection = () => saveConsent(preferences);
  const togglePreference = (key) => {
    if (INITIAL_COOKIES[key].required) return;
    setPreferences((prev) => ({ ...prev, [key]: !prev[key] }));
  };
  if (!forceVisible && !isVisible && hasConsent) {
    return /* @__PURE__ */ jsx(
      "button",
      {
        type: "button",
        className: "cookie-floating-btn",
        onClick: () => {
          setIsVisible(true);
          setShowDetails(true);
        },
        "aria-label": t("cookies.open_preferences"),
        children: /* @__PURE__ */ jsx(FaCookieBite, {})
      }
    );
  }
  if (!isVisible && !forceVisible && !hasConsent) {
    return null;
  }
  return /* @__PURE__ */ jsx("div", { className: "cookie-banner-container", children: /* @__PURE__ */ jsxs("div", { className: "cookie-card", children: [
    /* @__PURE__ */ jsxs("div", { className: "cookie-header", children: [
      /* @__PURE__ */ jsx("div", { className: "cookie-icon-box", children: /* @__PURE__ */ jsx(FaCookieBite, {}) }),
      /* @__PURE__ */ jsxs("div", { className: "cookie-title-area", children: [
        /* @__PURE__ */ jsx("h3", { children: t("cookies.title") }),
        /* @__PURE__ */ jsx("p", { className: "cookie-text", children: t("cookies.message") })
      ] })
    ] }),
    showDetails && /* @__PURE__ */ jsx("div", { className: "cookie-details", children: Object.keys(INITIAL_COOKIES).map((key) => /* @__PURE__ */ jsxs("div", { className: "cookie-option", children: [
      /* @__PURE__ */ jsx(
        "label",
        {
          className: "cookie-option-label",
          htmlFor: `cookie-${key}`,
          children: /* @__PURE__ */ jsx("span", { className: "cookie-opt-name", children: t(`cookies.cat_${key}`) })
        }
      ),
      /* @__PURE__ */ jsxs("div", { className: "cookie-switch", children: [
        /* @__PURE__ */ jsx(
          "input",
          {
            type: "checkbox",
            id: `cookie-${key}`,
            checked: preferences[key],
            disabled: INITIAL_COOKIES[key].required,
            onChange: () => togglePreference(key)
          }
        ),
        /* @__PURE__ */ jsx("span", { className: "cookie-slider" })
      ] })
    ] }, key)) }),
    /* @__PURE__ */ jsxs("div", { className: "cookie-actions", children: [
      /* @__PURE__ */ jsxs("div", { className: "cookie-btn-group", children: [
        showDetails ? /* @__PURE__ */ jsxs(
          "button",
          {
            type: "button",
            className: "cookie-btn btn-secondary",
            onClick: handleSaveSelection,
            children: [
              /* @__PURE__ */ jsx(FaCheck, { size: 12 }),
              " ",
              t("cookies.save")
            ]
          }
        ) : /* @__PURE__ */ jsxs(
          "button",
          {
            type: "button",
            className: "cookie-btn btn-secondary",
            onClick: handleRejectAll,
            children: [
              /* @__PURE__ */ jsx(FaTimes, { size: 12 }),
              " ",
              t("cookies.reject")
            ]
          }
        ),
        /* @__PURE__ */ jsx(
          "button",
          {
            type: "button",
            className: "cookie-btn btn-accept",
            onClick: handleAcceptAll,
            children: t("cookies.accept_all")
          }
        )
      ] }),
      /* @__PURE__ */ jsx(
        "button",
        {
          type: "button",
          className: "btn-ghost",
          onClick: () => setShowDetails((v) => !v),
          children: showDetails ? t("cookies.hide_details") : t("cookies.settings")
        }
      )
    ] })
  ] }) });
};
const FALLBACK_WHATSAPP_LOGO_URL = "/images/logo/WhatsApp-80.webp";
function WhatsAppWidget({ data = [] }) {
  var _a;
  const { props } = usePage();
  const widgets = ((_a = props.global) == null ? void 0 : _a.widgets) ?? {};
  const whatsappItems = Array.isArray(data) && data.length > 0 ? data : Array.isArray(widgets.whatsapp) ? widgets.whatsapp : [];
  const config = whatsappItems[0];
  if (!config || !config.is_active) return null;
  const phone = String(config.phone_number ?? "").replace(/\D/g, "");
  if (phone.length < 6) return null;
  const message = config.default_message || config.welcome_text || "Hello!";
  const position = config.button_position === "bottom-left" ? "left" : "right";
  const buttonColor = config.button_color || "#25D366";
  const textColor = config.button_text_color || "#FFFFFF";
  const logoUrl = config.logo_url || FALLBACK_WHATSAPP_LOGO_URL;
  const whatsappUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
  return /* @__PURE__ */ jsx(
    "a",
    {
      href: whatsappUrl,
      target: "_blank",
      rel: "noopener noreferrer",
      "aria-label": "WhatsApp Chat",
      "data-track-key": "whatsapp_contact_click",
      "data-track-name": "WhatsApp – Kontakt starten",
      "data-track-location": "floating_whatsapp_widget",
      "data-track-action": "open_whatsapp",
      className: `
                fixed bottom-4 z-50 w-14 h-14
                flex items-center justify-center
                rounded-full shadow-lg cursor-pointer
                hover:scale-110 hover:shadow-2xl
                transition-transform
                ${position === "left" ? "left-4" : "right-4"}
            `,
      style: { backgroundColor: buttonColor, color: textColor },
      title: config.welcome_text || "WhatsApp",
      children: /* @__PURE__ */ jsx(
        "img",
        {
          src: logoUrl,
          className: "w-10 h-10 object-contain rounded-full",
          alt: "WhatsApp contact",
          width: 40,
          height: 40,
          loading: "lazy",
          decoding: "async",
          onError: (e) => {
            if (e.currentTarget.src.includes(
              FALLBACK_WHATSAPP_LOGO_URL
            )) {
              e.currentTarget.style.display = "none";
              return;
            }
            e.currentTarget.src = FALLBACK_WHATSAPP_LOGO_URL;
          }
        }
      )
    }
  );
}
const QUEUE_KEY = "omr_analytics_queue_v1";
const LAST_FLUSH_KEY = "omr_analytics_last_flush_v1";
const LOCK_KEY = "omr_analytics_flush_lock_v1";
const VISITOR_KEY = "omr_analytics_visitor_v1";
const SESSION_KEY = "omr_analytics_session_v1";
const FLUSH_INTERVAL_MS = 60 * 60 * 1e3;
const LOCK_TTL_MS = 2 * 60 * 1e3;
const ALLOWED_ENDPOINTS = /* @__PURE__ */ new Set([
  "track",
  "page-timing",
  "track-conversion"
]);
let flushTimer = null;
const storage = {
  get(key, fallback = null) {
    try {
      const value = localStorage.getItem(key);
      return value === null ? fallback : value;
    } catch {
      return fallback;
    }
  },
  set(key, value) {
    try {
      localStorage.setItem(key, value);
      return true;
    } catch {
      return false;
    }
  },
  remove(key) {
    try {
      localStorage.removeItem(key);
    } catch {
    }
  }
};
const makeId = () => {
  if (typeof (crypto == null ? void 0 : crypto.randomUUID) === "function") {
    return crypto.randomUUID();
  }
  return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
};
const hasAnalyticsConsent = () => {
  try {
    const consent = JSON.parse(Cookies.get("cookie_consent") || "{}");
    return (consent == null ? void 0 : consent.analytics) === true;
  } catch {
    return false;
  }
};
const readQueue = () => {
  try {
    const queue = JSON.parse(storage.get(QUEUE_KEY, "[]"));
    return Array.isArray(queue) ? queue : [];
  } catch {
    return [];
  }
};
const writeQueue = (queue) => storage.set(QUEUE_KEY, JSON.stringify(queue.slice(-500)));
const clearAnalyticsStorage = () => {
  storage.remove(QUEUE_KEY);
  storage.remove(LAST_FLUSH_KEY);
  storage.remove(LOCK_KEY);
  storage.remove(VISITOR_KEY);
  storage.remove(SESSION_KEY);
  if (flushTimer) clearTimeout(flushTimer);
  flushTimer = null;
};
const acquireLock = () => {
  const now = Date.now();
  const current = Number(storage.get(LOCK_KEY, "0"));
  if (Number.isFinite(current) && now - current < LOCK_TTL_MS) {
    return false;
  }
  storage.set(LOCK_KEY, String(now));
  return true;
};
const releaseLock = () => storage.remove(LOCK_KEY);
const scheduleFlush = () => {
  if (!hasAnalyticsConsent()) return;
  if (flushTimer) clearTimeout(flushTimer);
  const now = Date.now();
  let lastFlush = Number(storage.get(LAST_FLUSH_KEY, "0"));
  if (!Number.isFinite(lastFlush) || lastFlush <= 0) {
    lastFlush = now;
    storage.set(LAST_FLUSH_KEY, String(lastFlush));
  }
  const wait = Math.max(1e3, FLUSH_INTERVAL_MS - (now - lastFlush));
  flushTimer = window.setTimeout(() => void flushAnalytics(), wait);
};
const enqueue = (endpoint, payload) => {
  if (!ALLOWED_ENDPOINTS.has(endpoint) || !hasAnalyticsConsent()) return;
  const queue = readQueue();
  queue.push({
    id: makeId(),
    endpoint,
    created_at: (/* @__PURE__ */ new Date()).toISOString(),
    payload
  });
  writeQueue(queue);
  scheduleFlush();
};
const sendGroup = async (endpoint, events) => {
  const response = await fetch(`/api/analytics/${endpoint}`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      batch_id: makeId(),
      visitor_id: storage.get(VISITOR_KEY),
      session_id: storage.get(SESSION_KEY),
      events: events.map(({ id, payload }) => ({ id, payload }))
    }),
    keepalive: true
  });
  const result = await response.json().catch(() => ({}));
  if (result == null ? void 0 : result.visitor_id) {
    storage.set(VISITOR_KEY, String(result.visitor_id));
  }
  if (result == null ? void 0 : result.session_id) {
    storage.set(SESSION_KEY, String(result.session_id));
  }
  if ((result == null ? void 0 : result.drop) === true) {
    return events.map((event) => event.id);
  }
  return response.ok && Array.isArray(result == null ? void 0 : result.accepted_ids) ? result.accepted_ids.map(String) : [];
};
const flushAnalytics = async () => {
  if (!hasAnalyticsConsent()) {
    clearAnalyticsStorage();
    return;
  }
  const queue = readQueue();
  if (queue.length === 0 || !acquireLock()) {
    scheduleFlush();
    return;
  }
  storage.set(LAST_FLUSH_KEY, String(Date.now()));
  try {
    const groups = queue.reduce((carry, event) => {
      var _a;
      if (!ALLOWED_ENDPOINTS.has(event == null ? void 0 : event.endpoint)) return carry;
      carry[_a = event.endpoint] || (carry[_a] = []);
      carry[event.endpoint].push(event);
      return carry;
    }, {});
    const accepted = /* @__PURE__ */ new Set();
    for (const [endpoint, events] of Object.entries(groups)) {
      try {
        const ids = await sendGroup(endpoint, events);
        ids.forEach((id) => accepted.add(String(id)));
      } catch {
      }
    }
    const latestQueue = readQueue();
    writeQueue(
      latestQueue.filter((event) => !accepted.has(String(event.id)))
    );
  } finally {
    releaseLock();
    scheduleFlush();
  }
};
const trackConversion = (conversionType, metadata = {}) => {
  if (!conversionType) return;
  enqueue("track-conversion", {
    conversion_type: String(conversionType),
    event_type: "conversion",
    event_name: String(conversionType),
    page_url: window.location.href,
    metadata,
    occurred_at: (/* @__PURE__ */ new Date()).toISOString()
  });
};
function QuoteModal() {
  const { t } = useTranslation();
  const { props } = usePage();
  const global = props.global || {};
  const tenantId = props.tenantId || props.tenant_id || "";
  const locale = props.locale || "de";
  const initialForms = Array.isArray(props.forms) ? props.forms : [];
  const [forms, setForms] = useState(initialForms);
  const categories = global.categories || [];
  const formToDisplay = forms.find((f) => f.id == 2);
  const fields = Array.isArray(formToDisplay == null ? void 0 : formToDisplay.fields) ? formToDisplay.fields : [];
  const [open, setOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [ok, setOk] = useState(false);
  const [formState, setFormState] = useState({});
  const [errors, setErrors] = useState({});
  const dialogRef = useRef(null);
  const openerRef = useRef(null);
  const labelKeyMap = {
    field_0: "quote_modal.field_name",
    field_1: "quote_modal.field_email",
    field_2: "quote_modal.field_phone",
    field_3: "quote_modal.field_service",
    field_4: "quote_modal.field_message"
  };
  useEffect(() => {
    const listener = () => {
      openerRef.current = document.activeElement;
      setOpen(true);
    };
    window.addEventListener("open-quote-modal", listener);
    return () => {
      window.removeEventListener("open-quote-modal", listener);
    };
  }, []);
  useEffect(() => {
    if (!open || forms.length > 0) return;
    let cancelled = false;
    fetch(`/api/contact/forms?locale=${locale}`, {
      headers: {
        Accept: "application/json",
        "X-Tenant-ID": tenantId
      }
    }).then((response) => response.ok ? response.json() : null).then((result) => {
      if (cancelled) return;
      const nextForms = Array.isArray(result == null ? void 0 : result.data) ? result.data : Array.isArray(result) ? result : [];
      setForms(nextForms);
    }).catch(() => {
    });
    return () => {
      cancelled = true;
    };
  }, [open, forms.length, locale, tenantId]);
  const resetForm = () => {
    setFormState({});
    setErrors({});
  };
  const close = () => {
    var _a, _b;
    setOpen(false);
    setOk(false);
    resetForm();
    (_b = (_a = openerRef.current) == null ? void 0 : _a.focus) == null ? void 0 : _b.call(_a);
  };
  const setField = (name, value) => {
    let updated = value;
    if (name === "field_2" && !/^[0-9+\-()\s]*$/.test(value)) return;
    if (name === "field_1") updated = value.toLowerCase();
    setFormState((prev) => ({ ...prev, [name]: updated }));
    if (errors[name]) {
      const copy = { ...errors };
      delete copy[name];
      setErrors(copy);
    }
  };
  const submitForm = async () => {
    const payload = {
      name: formState.field_0 || "",
      email: formState.field_1 || "",
      phone: formState.field_2 || "",
      service: formState.field_3 || "",
      message: formState.field_4 || ""
    };
    const response = await fetch(
      `/api/contact/forms/${formToDisplay.id}/submit?locale=${locale}`,
      {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-Tenant-ID": tenantId
        },
        body: JSON.stringify(payload)
      }
    );
    const result = await response.json();
    return { status: response.status, data: result };
  };
  const onSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setErrors({});
    const frontendErrors = {};
    if (!formState.field_0)
      frontendErrors["field_0"] = t("contact.required.name");
    if (!formState.field_1)
      frontendErrors["field_1"] = t("contact.required.email");
    if (!formState.field_2)
      frontendErrors["field_2"] = t("contact.required.phone");
    if (!formState.field_4)
      frontendErrors["field_4"] = t("contact.required.message");
    if (Object.keys(frontendErrors).length > 0) {
      setErrors(frontendErrors);
      setSubmitting(false);
      return;
    }
    try {
      const { status, data } = await submitForm();
      if (status === 422 && data.errors) {
        const beErrors = {};
        Object.keys(data.errors).forEach((key) => {
          if (key === "name")
            beErrors["field_0"] = t("contact.required.name");
          if (key === "email" || key === "e-mail")
            beErrors["field_1"] = t("contact.required.email");
          if (key === "phone" || key === "telefon")
            beErrors["field_2"] = t("contact.required.phone");
          if (key === "message" || key === "nachricht")
            beErrors["field_4"] = t("contact.required.message");
        });
        setErrors(beErrors);
        setSubmitting(false);
        return;
      }
      if (status >= 200 && status < 300) {
        trackConversion("quote_form_submitted", {
          form_id: formToDisplay.id,
          service: formState.field_3 || null
        });
      }
      setOk(true);
      resetForm();
    } catch {
      setOk(true);
      resetForm();
    } finally {
      setSubmitting(false);
    }
  };
  if (!open || !formToDisplay) return null;
  return createPortal(
    /* @__PURE__ */ jsxs("div", { className: "qdock", style: { zIndex: 99999 }, children: [
      /* @__PURE__ */ jsx("button", { className: "qdock__scrim", onClick: close }),
      /* @__PURE__ */ jsx("div", { className: "qdock__dialog qdock-anim-in", ref: dialogRef, children: !ok ? /* @__PURE__ */ jsxs("form", { className: "qdock__form", onSubmit, children: [
        /* @__PURE__ */ jsx("div", { className: "qdock__grid", children: fields.map((f) => /* @__PURE__ */ jsx(
          "div",
          {
            className: `qdock__field-box ${f.type === "textarea" ? "full" : ""}`,
            children: /* @__PURE__ */ jsxs("label", { className: "qdock__field", children: [
              /* @__PURE__ */ jsxs("span", { children: [
                t(labelKeyMap[f.name] || f.label),
                " ",
                f.required && "*"
              ] }),
              f.type === "select" ? /* @__PURE__ */ jsxs(
                "select",
                {
                  name: f.name,
                  value: formState[f.name] || "",
                  onChange: (e) => setField(
                    f.name,
                    e.target.value
                  ),
                  className: errors[f.name] ? "error" : "",
                  children: [
                    /* @__PURE__ */ jsx("option", { value: "", children: t(
                      "quote_modal.service_placeholder",
                      "Lütfen seçin"
                    ) }),
                    categories.map((cat) => /* @__PURE__ */ jsx(
                      "option",
                      {
                        value: cat.slug,
                        children: cat.name || cat.slug
                      },
                      cat.id
                    ))
                  ]
                }
              ) : f.type === "textarea" ? /* @__PURE__ */ jsx(
                "textarea",
                {
                  name: f.name,
                  rows: 4,
                  value: formState[f.name] || "",
                  onChange: (e) => setField(
                    f.name,
                    e.target.value
                  ),
                  className: errors[f.name] ? "error" : ""
                }
              ) : /* @__PURE__ */ jsx(
                "input",
                {
                  name: f.name,
                  type: f.name === "field_2" ? "tel" : f.name === "field_1" ? "email" : "text",
                  value: formState[f.name] || "",
                  onChange: (e) => setField(
                    f.name,
                    e.target.value
                  ),
                  className: errors[f.name] ? "error" : ""
                }
              ),
              errors[f.name] && /* @__PURE__ */ jsx("span", { className: "qdock__error", children: errors[f.name] })
            ] })
          },
          f.name
        )) }),
        /* @__PURE__ */ jsxs("div", { className: "qdock__actions", children: [
          /* @__PURE__ */ jsx(
            "button",
            {
              type: "button",
              onClick: close,
              className: "btn btn--ghost",
              children: t("quote_modal.cancel", "Vazgeç")
            }
          ),
          /* @__PURE__ */ jsx(
            "button",
            {
              type: "submit",
              className: "btn btn--primary anfordern-form-submit-button",
              "data-track-key": "anfordern_form_submit",
              "data-track-name": "Anfordern – Formular senden",
              "data-track-location": "contact_form_modal",
              "data-track-action": "submit_contact_form",
              "data-track-form": "anfordern_form",
              disabled: submitting,
              children: submitting ? "..." : t("quote_modal.submit", "Gönder")
            }
          )
        ] })
      ] }) : /* @__PURE__ */ jsxs("div", { className: "qdock__ok", children: [
        /* @__PURE__ */ jsx("div", { className: "qdock__ok-badge", children: "✓" }),
        /* @__PURE__ */ jsx("h3", { children: t("quote_modal.thank_you_title", "Teşekkürler!") }),
        /* @__PURE__ */ jsx("p", { children: t(
          "quote_modal.thank_you_text",
          "Talebinizi aldık, en kısa sürede sizinle iletişime geçeceğiz."
        ) }),
        /* @__PURE__ */ jsx("button", { className: "btn btn--primary", onClick: close, children: t("quote_modal.close_button", "Kapat") })
      ] }) })
    ] }),
    document.body
  );
}
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
function relativeLuminance(rgb) {
  const [r, g, b] = rgb.map((channel) => {
    const c = Math.min(255, Math.max(0, channel)) / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
function surfaceInkVars(prefix, background, brand) {
  const rgb = parseColor(background);
  if (!rgb) return {};
  const isLight = relativeLuminance(rgb) > 0.45;
  return isLight ? {
    [`--${prefix}-ink`]: "",
    [`--${prefix}-ink-muted`]: "",
    [`--${prefix}-line`]: "",
    [`--${prefix}-soft`]: "",
    [`--${prefix}-accent`]: brand || ""
  } : {
    [`--${prefix}-ink`]: "#ffffff",
    [`--${prefix}-ink-muted`]: "rgba(255,255,255,.72)",
    [`--${prefix}-line`]: "rgba(255,255,255,.24)",
    [`--${prefix}-soft`]: "rgba(255,255,255,.16)",
    [`--${prefix}-accent`]: "#ffffff"
  };
}
const AppLayout = memo(function AppLayout2({ children }) {
  var _a, _b, _c, _d, _e, _f, _g;
  const { props, component } = usePage();
  const locale = props.locale || "de";
  props.tenantId || "";
  const settings = props.settings ?? {};
  const menus = props.menus ?? {};
  props.global ?? {};
  const general = settings.general ?? {};
  const seo = settings.seo ?? {};
  const branding = settings.branding ?? {};
  const colors = settings.colors ?? {};
  const analytics = settings.analytics ?? {};
  const headerMenu = Array.isArray((_a = menus == null ? void 0 : menus.header) == null ? void 0 : _a.items) ? menus.header.items : Array.isArray(menus == null ? void 0 : menus.header) ? menus.header : [];
  const footerMenu = Array.isArray((_b = menus == null ? void 0 : menus.footer) == null ? void 0 : _b.items) ? menus.footer.items : Array.isArray(menus == null ? void 0 : menus.footer) ? menus.footer : [];
  const siteTitle = renderText(
    seo.meta_title,
    renderText(general.site_name, "Website")
  );
  const siteDescription = renderText(
    seo.meta_description,
    renderText(general.site_description)
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
    "Errors/Show"
  ].includes(component);
  const faviconSource = renderUrl(
    branding.site_favicon_url || settings.site_favicon_url || branding.favicon_url || branding.site_favicon || settings.site_favicon || branding.favicon
  );
  const faviconVersion = faviconSource ? faviconSource.split("?")[0].split("/").filter(Boolean).pop() : "default";
  const faviconExtension = ((_d = (_c = faviconSource == null ? void 0 : faviconSource.split("?")[0].match(/\.(ico|png|jpe?g|gif|webp|svg)$/i)) == null ? void 0 : _c[1]) == null ? void 0 : _d.toLowerCase()) || "webp";
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
    "--button-bg": colors.button_color
  };
  useEffect(() => {
    if (!colors || typeof colors !== "object") return;
    const root = document.documentElement;
    Object.keys(colors).forEach((key) => {
      root.style.setProperty(`--${key.replace(/_/g, "-")}`, colors[key]);
    });
    const inkVars = {
      ...surfaceInkVars(
        "header",
        colors.header_background_color,
        colors.site_primary_color
      ),
      ...surfaceInkVars(
        "footer",
        colors.footer_background_color,
        colors.site_primary_color
      )
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
  const whatsappData = Array.isArray((_e = props.widgets) == null ? void 0 : _e.whatsapp) ? props.widgets.whatsapp : [];
  return /* @__PURE__ */ jsxs(Fragment, { children: [
    !pageOwnsSeo && /* @__PURE__ */ jsx(
      SeoHead,
      {
        title: siteTitle,
        description: siteDescription,
        keywords: siteKeywords,
        image: ogImage,
        ogTitle,
        ogDescription,
        locale,
        canonical: (_f = props.tenantSeo) == null ? void 0 : _f.canonicalUrl,
        origin: (_g = props.tenantSeo) == null ? void 0 : _g.canonicalBaseUrl,
        favicon
      }
    ),
    favicon && /* @__PURE__ */ jsxs(Head, { children: [
      /* @__PURE__ */ jsx(
        "link",
        {
          "head-key": "favicon",
          rel: "icon",
          href: favicon
        }
      ),
      /* @__PURE__ */ jsx(
        "link",
        {
          "head-key": "shortcut-icon",
          rel: "shortcut icon",
          href: favicon
        }
      ),
      /* @__PURE__ */ jsx(
        "link",
        {
          "head-key": "apple-touch-icon",
          rel: "apple-touch-icon",
          href: favicon
        }
      )
    ] }),
    /* @__PURE__ */ jsx(TrackingHead, { analytics }),
    /* @__PURE__ */ jsxs("div", { className: "site-shell min-h-screen flex flex-col", style: rootStyles, children: [
      /* @__PURE__ */ jsx(Header, { menu: headerMenu, settings }),
      /* @__PURE__ */ jsx("main", { className: "flex-grow relative z-10", children }),
      /* @__PURE__ */ jsx(Footer, { menu: footerMenu, settings })
    ] }),
    isClient && /* @__PURE__ */ jsxs(Fragment, { children: [
      /* @__PURE__ */ jsx(Loading, { style: rootStyles }),
      /* @__PURE__ */ jsx(QuoteModal, { style: rootStyles }),
      /* @__PURE__ */ jsx(
        CookieBanner,
        {
          style: rootStyles,
          forceVisible: showCookieSettings,
          onClose: () => setShowCookieSettings(false)
        }
      ),
      /* @__PURE__ */ jsx(WhatsAppWidget, { style: rootStyles, data: whatsappData })
    ] })
  ] });
});
export {
  AppLayout as A,
  SeoHead as S,
  SafeHtml as a,
  buildResponsiveImage as b,
  renderText as c,
  normalizeRichTextHeadings as n,
  renderUrl as r,
  trackConversion as t
};
