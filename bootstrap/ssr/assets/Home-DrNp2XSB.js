import { jsxs, jsx } from "react/jsx-runtime";
import { memo, useRef, useState, useEffect, useMemo } from "react";
import { usePage, Head } from "@inertiajs/react";
import { b as buildResponsiveImage, a as SafeHtml, c as renderText, r as renderUrl, A as AppLayout, S as SeoHead } from "./AppLayout-CWzTYdDK.js";
import { useTranslation } from "react-i18next";
import { S as ServicesGrid } from "./ServicesGrid-C15Cnj03.js";
import { C as ContactSection } from "./ContactSection-BRQuvdQ_.js";
import "react-icons/fa";
import "react-icons/fa6";
import "html-react-parser";
import "isomorphic-dompurify";
import "react-dom";
import "js-cookie";
import "react-icons/fi";
const API_BASE$1 = "https://omerdogan.de".replace(/\/+$/, "");
const normalizeUrl = (value) => {
  const url = String(value || "").trim();
  if (!url || /^\d+$/.test(url)) return null;
  if (url.startsWith("http")) return url;
  if (url.startsWith("/storage/") && API_BASE$1) return `${API_BASE$1}${url}`;
  if (url.startsWith("/")) return url;
  if (!API_BASE$1) return `/${url.replace(/^\/+/, "")}`;
  return `${API_BASE$1}/${url.replace(/^\/+/, "")}`;
};
const HeroSection = memo(({ sliders }) => {
  const { props } = usePage();
  const sliderList = (sliders == null ? void 0 : sliders.sliders) ?? [];
  const slide = sliderList[0] ?? {};
  const locale = String(props.locale || props.defaultLang || "de").toLowerCase().slice(0, 2);
  const defaultLocale = String(props.defaultLang || "de").toLowerCase().slice(0, 2);
  const servicesHref = locale === defaultLocale ? "/#services" : `/${locale}#services`;
  const heroRef = useRef(null);
  const videoRef = useRef(null);
  const [isMobile, setIsMobile] = useState(false);
  const [shouldLoadVideo, setShouldLoadVideo] = useState(false);
  const [showVideo, setShowVideo] = useState(false);
  const [videoLoaded, setVideoLoaded] = useState(false);
  const [isHeroVisible, setIsHeroVisible] = useState(true);
  const videoUrl = normalizeUrl(slide.video_url);
  const posterUrl = normalizeUrl(slide.video_poster) || normalizeUrl(slide.image);
  const poster = buildResponsiveImage(posterUrl, {
    widths: [640, 960, 1280, 1600],
    width: 1600,
    quality: 72
  });
  const imageAlt = String(slide.image_alt || slide.title || "Service hero image").replace(/<[^>]+>/g, "").trim() || "Service hero image";
  useEffect(() => {
    if (typeof window === "undefined") return;
    const mobile = window.matchMedia("(max-width: 768px)").matches;
    setIsMobile(mobile);
  }, []);
  useEffect(() => {
    if (!heroRef.current || isMobile || !videoUrl) return;
    const observer = new IntersectionObserver(
      ([entry]) => {
        setIsHeroVisible(entry.isIntersecting);
        if (entry.isIntersecting) {
          setShouldLoadVideo(true);
        }
      },
      { threshold: 0.05 }
    );
    observer.observe(heroRef.current);
    return () => observer.disconnect();
  }, [isMobile, videoUrl]);
  useEffect(() => {
    if (!shouldLoadVideo) return;
    const t = setTimeout(() => setShowVideo(true), 1500);
    return () => clearTimeout(t);
  }, [shouldLoadVideo]);
  useEffect(() => {
    const video = videoRef.current;
    if (!video || !showVideo) return;
    if (!isHeroVisible || document.hidden) {
      video.pause();
      return;
    }
    video.play().catch(() => {
    });
  }, [isHeroVisible, showVideo, videoLoaded]);
  useEffect(() => {
    if (!showVideo) return;
    const handleVisibilityChange = () => {
      const video = videoRef.current;
      if (!video) return;
      if (document.hidden || !isHeroVisible) {
        video.pause();
      } else {
        video.play().catch(() => {
        });
      }
    };
    document.addEventListener("visibilitychange", handleVisibilityChange);
    return () => document.removeEventListener(
      "visibilitychange",
      handleVisibilityChange
    );
  }, [isHeroVisible, showVideo]);
  const scrollToServices = (event) => {
    const services = document.getElementById("services");
    if (!services) return;
    event.preventDefault();
    services.scrollIntoView({ behavior: "smooth", block: "start" });
    window.history.pushState(null, "", servicesHref);
  };
  return /* @__PURE__ */ jsxs("section", { ref: heroRef, id: "top", className: "hero-section", children: [
    poster.src && /* @__PURE__ */ jsx(Head, { children: /* @__PURE__ */ jsx(
      "link",
      {
        rel: "preload",
        as: "image",
        href: poster.src,
        imageSrcSet: poster.srcSet || void 0,
        imageSizes: "100vw",
        fetchpriority: "high"
      }
    ) }),
    posterUrl && /* @__PURE__ */ jsx(
      "img",
      {
        src: poster.src,
        srcSet: poster.srcSet || void 0,
        sizes: "100vw",
        alt: imageAlt,
        className: "hero-bg hero-bg--image",
        width: "1920",
        height: "1080",
        loading: "eager",
        fetchpriority: "high",
        decoding: "async",
        style: { objectFit: "cover" }
      }
    ),
    !isMobile && showVideo && videoUrl && /* @__PURE__ */ jsx(
      "video",
      {
        ref: videoRef,
        className: "hero-bg hero-bg--video",
        playsInline: true,
        muted: true,
        autoPlay: true,
        loop: true,
        preload: "none",
        onLoadedData: () => setVideoLoaded(true),
        style: {
          opacity: videoLoaded ? 1 : 0,
          transition: "opacity .6s ease-in-out"
        },
        children: /* @__PURE__ */ jsx("source", { src: videoUrl, type: "video/mp4" })
      }
    ),
    /* @__PURE__ */ jsxs("div", { className: "hero-content", children: [
      /* @__PURE__ */ jsx("h1", { className: "hero-title", children: /* @__PURE__ */ jsx(SafeHtml, { html: slide.title || "", inline: true }) }),
      /* @__PURE__ */ jsx("p", { className: "hero-subtitle", children: /* @__PURE__ */ jsx(SafeHtml, { html: slide.description || "" }) }),
      slide.buttonLabel && /* @__PURE__ */ jsx("div", { className: "hero-cta-group", children: /* @__PURE__ */ jsx(
        "a",
        {
          href: servicesHref,
          className: "hero-cta hero-cta--primary",
          onClick: scrollToServices,
          children: slide.buttonLabel
        }
      ) })
    ] })
  ] });
});
const SERVICE_IMAGE_SIZES = "(max-width: 768px) 92vw, 367px";
const API_BASE = "https://omerdogan.de".replace(/\/+$/, "");
const buildServiceImage = (rawImage, baseUrl = API_BASE) => {
  const normalized = String(rawImage || "").trim();
  if (!normalized || /^\d+$/.test(normalized)) {
    return {
      src: "",
      srcSet: ""
    };
  }
  const isLocalMediaCache = /^\/?media-cache\//i.test(normalized);
  const absoluteUrl = normalized.startsWith("http") ? normalized : isLocalMediaCache ? `/${normalized.replace(/^\/+/, "")}` : `${baseUrl}/${normalized.replace(/^\/+/, "")}`;
  if (/\/media-cache\/.+\.(avif|webp|png|jpe?g|gif|svg)(\?.*)?$/i.test(absoluteUrl)) {
    return {
      src: absoluteUrl,
      srcSet: ""
    };
  }
  return buildResponsiveImage(absoluteUrl, {
    widths: [360, 480, 640, 720],
    width: 480,
    quality: 65
  });
};
const firstImageValue = (...values) => {
  for (const value of values) {
    if (Array.isArray(value)) {
      const nested = firstImageValue(...value);
      if (nested) return nested;
      continue;
    }
    if (value && typeof value === "object") {
      const nested = firstImageValue(
        value.url,
        value.path,
        value.src,
        value.original_url,
        value.id
      );
      if (nested) return nested;
      continue;
    }
    if (typeof value === "number") {
      return String(value);
    }
    if (typeof value === "string" && value.trim()) {
      return value.trim();
    }
  }
  return "";
};
const ServiceCategories = memo(({ content = {} }) => {
  var _a, _b, _c, _d, _e, _f;
  const { t } = useTranslation();
  const { props } = usePage();
  const locale = (props == null ? void 0 : props.locale) || "de";
  const rawHighlights = ((_a = props.widgets) == null ? void 0 : _a.highlights) ?? ((_b = props.widgets) == null ? void 0 : _b.service_highlights) ?? ((_d = (_c = props.global) == null ? void 0 : _c.widgets) == null ? void 0 : _d.highlights) ?? ((_f = (_e = props.global) == null ? void 0 : _e.widgets) == null ? void 0 : _f.service_highlights) ?? [];
  const list = Array.isArray(rawHighlights == null ? void 0 : rawHighlights.data) ? rawHighlights.data : Array.isArray(rawHighlights) ? rawHighlights : [];
  const services = useMemo(
    () => list.map((s) => {
      const tr = (s.translations || []).find(
        (i) => String(i.language_code || "").toLowerCase() === String(locale).toLowerCase()
      );
      const rawImage = firstImageValue(
        s.image_url,
        s.image,
        s.media,
        s.thumbnail,
        s.featured_image,
        s.cover_image
      );
      const image = buildServiceImage(rawImage);
      return {
        ...s,
        name: (tr == null ? void 0 : tr.name) || s.name,
        description: (tr == null ? void 0 : tr.description) || s.description,
        image: image.src,
        imageSrcSet: image.srcSet
      };
    }),
    [list, locale]
  );
  return /* @__PURE__ */ jsx("section", { className: "svc-section", children: /* @__PURE__ */ jsxs("div", { className: "svc-container", children: [
    /* @__PURE__ */ jsx("h2", { className: "svc-title", children: /* @__PURE__ */ jsx(
      SafeHtml,
      {
        html: content.section_services || t("services.section_title")
      }
    ) }),
    /* @__PURE__ */ jsx("div", { className: "svc-grid", children: services.map((svc, index) => {
      return /* @__PURE__ */ jsxs(
        "div",
        {
          className: "svc-card",
          children: [
            /* @__PURE__ */ jsx("div", { className: "svc-card-img-wrap", children: svc.image ? /* @__PURE__ */ jsx(
              "img",
              {
                src: svc.image,
                srcSet: svc.imageSrcSet || void 0,
                sizes: SERVICE_IMAGE_SIZES,
                className: "svc-card-img",
                decoding: "async",
                loading: "eager",
                fetchpriority: index === 0 ? "high" : "auto",
                width: 360,
                height: 230,
                alt: svc.image_alt || svc.name || "Cleaning service",
                onError: (e) => {
                  e.currentTarget.style.visibility = "hidden";
                }
              }
            ) : /* @__PURE__ */ jsx("div", { className: "svc-card-img svc-card-img--placeholder", "aria-hidden": "true" }) }),
            /* @__PURE__ */ jsxs("div", { className: "svc-card-body", children: [
              /* @__PURE__ */ jsx("h3", { className: "svc-card-title", children: svc.name }),
              /* @__PURE__ */ jsx("p", { className: "svc-card-desc", children: /* @__PURE__ */ jsx(SafeHtml, { html: svc.description }) })
            ] })
          ]
        },
        svc.id
      );
    }) })
  ] }) });
});
ServiceCategories.displayName = "ServiceCategories";
const HomeFaqSection = memo(({ content = {} }) => {
  const { t } = useTranslation();
  const { props } = usePage();
  const faq = (props == null ? void 0 : props.faq) || {};
  const items = Array.isArray(faq.items) ? faq.items : [];
  const [openId, setOpenId] = useState(() => {
    var _a;
    return ((_a = items[0]) == null ? void 0 : _a.id) ?? 0;
  });
  if (items.length === 0) {
    return null;
  }
  const title = renderText(
    content.faq_title,
    t("faq.title", "Häufige Fragen")
  );
  const subtitle = renderText(
    content.faq_subtitle,
    t(
      "faq.subtitle",
      "Die Antworten, nach denen am häufigsten gefragt wird. Ihre Frage ist nicht dabei? Melden Sie sich einfach."
    )
  );
  return /* @__PURE__ */ jsx("section", { id: "faq", className: "hfaq-section", children: /* @__PURE__ */ jsxs("div", { className: "hfaq-container container", children: [
    /* @__PURE__ */ jsxs("header", { className: "hfaq-head", children: [
      /* @__PURE__ */ jsxs("span", { className: "hfaq-eyebrow", children: [
        t("faq.eyebrow", "FAQ"),
        faq.is_demo && /* @__PURE__ */ jsx("em", { className: "hfaq-demo", children: t("common.demo", "Demo") })
      ] }),
      /* @__PURE__ */ jsx("h2", { className: "hfaq-title", children: /* @__PURE__ */ jsx(SafeHtml, { html: title, inline: true }) }),
      subtitle && /* @__PURE__ */ jsx("p", { className: "hfaq-subtitle", children: /* @__PURE__ */ jsx(SafeHtml, { html: subtitle, inline: true }) })
    ] }),
    /* @__PURE__ */ jsx("div", { className: "hfaq-list", children: items.map((item, index) => {
      const key = item.id ?? index;
      const isOpen = openId === key;
      return /* @__PURE__ */ jsxs(
        "div",
        {
          className: `hfaq-item${isOpen ? " is-open" : ""}`,
          children: [
            /* @__PURE__ */ jsx("h3", { className: "hfaq-item__heading", children: /* @__PURE__ */ jsxs(
              "button",
              {
                type: "button",
                className: "hfaq-item__trigger",
                "aria-expanded": isOpen,
                "aria-controls": `hfaq-panel-${key}`,
                id: `hfaq-trigger-${key}`,
                onClick: () => setOpenId(isOpen ? null : key),
                children: [
                  /* @__PURE__ */ jsx("span", { className: "hfaq-item__question", children: item.question }),
                  /* @__PURE__ */ jsx(
                    "span",
                    {
                      className: "hfaq-item__icon",
                      "aria-hidden": "true"
                    }
                  )
                ]
              }
            ) }),
            /* @__PURE__ */ jsx(
              "div",
              {
                className: "hfaq-item__panel",
                id: `hfaq-panel-${key}`,
                role: "region",
                "aria-labelledby": `hfaq-trigger-${key}`,
                hidden: !isOpen,
                children: /* @__PURE__ */ jsx("div", { className: "hfaq-item__answer", children: /* @__PURE__ */ jsx(SafeHtml, { html: item.answer }) })
              }
            )
          ]
        },
        key
      );
    }) })
  ] }) });
});
HomeFaqSection.displayName = "HomeFaqSection";
const MAX_ITEMS = 6;
function initials(name) {
  return String(name || "").trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join("");
}
function formatDate(value, locale) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  try {
    return new Intl.DateTimeFormat(locale, {
      year: "numeric",
      month: "long"
    }).format(date);
  } catch (e) {
    return date.toISOString().slice(0, 7);
  }
}
const Stars = ({ value, label }) => {
  const rounded = Math.round(Number(value) || 0);
  return /* @__PURE__ */ jsx("span", { className: "rev-stars", role: "img", "aria-label": label, children: [1, 2, 3, 4, 5].map((star) => /* @__PURE__ */ jsx(
    "svg",
    {
      className: `rev-stars__icon${star <= rounded ? " is-on" : ""}`,
      viewBox: "0 0 20 20",
      "aria-hidden": "true",
      focusable: "false",
      children: /* @__PURE__ */ jsx("path", { d: "M10 1.6l2.47 5.28 5.53.76-4.03 3.83 1.02 5.6L10 14.4l-4.99 2.67 1.02-5.6L2 7.64l5.53-.76z" })
    },
    star
  )) });
};
const ReviewsSection = memo(({ content = {} }) => {
  var _a, _b;
  const { t } = useTranslation();
  const { props } = usePage();
  const locale = (props == null ? void 0 : props.locale) || "de";
  const reviews = (props == null ? void 0 : props.reviews) || {};
  const items = useMemo(
    () => Array.isArray(reviews.items) ? reviews.items.slice(0, MAX_ITEMS) : [],
    [reviews.items]
  );
  if (items.length === 0) {
    return null;
  }
  const average = (_a = reviews.summary) == null ? void 0 : _a.average;
  const count = ((_b = reviews.summary) == null ? void 0 : _b.count) ?? items.length;
  const title = renderText(
    content.reviews_title,
    t("reviews.title", "Das sagen unsere Kunden")
  );
  const subtitle = renderText(
    content.reviews_subtitle,
    t(
      "reviews.subtitle",
      "Echte Rückmeldungen aus Hotels, Büros und Objekten, die wir täglich betreuen."
    )
  );
  return /* @__PURE__ */ jsx("section", { id: "reviews", className: "rev-section", children: /* @__PURE__ */ jsxs("div", { className: "rev-container container", children: [
    /* @__PURE__ */ jsxs("header", { className: "rev-head", children: [
      /* @__PURE__ */ jsxs("div", { className: "rev-head__copy", children: [
        /* @__PURE__ */ jsxs("span", { className: "rev-eyebrow", children: [
          t("reviews.eyebrow", "Kundenstimmen"),
          reviews.is_demo && /* @__PURE__ */ jsx("em", { className: "rev-demo", children: t("common.demo", "Demo") })
        ] }),
        /* @__PURE__ */ jsx("h2", { className: "rev-title", children: /* @__PURE__ */ jsx(SafeHtml, { html: title, inline: true }) }),
        subtitle && /* @__PURE__ */ jsx("p", { className: "rev-subtitle", children: /* @__PURE__ */ jsx(SafeHtml, { html: subtitle, inline: true }) })
      ] }),
      average != null && /* @__PURE__ */ jsxs("div", { className: "rev-score", children: [
        /* @__PURE__ */ jsx("strong", { className: "rev-score__value", children: average.toLocaleString(locale, {
          minimumFractionDigits: 1,
          maximumFractionDigits: 1
        }) }),
        /* @__PURE__ */ jsx(
          Stars,
          {
            value: average,
            label: t("reviews.rating_label", "Bewertung")
          }
        ),
        /* @__PURE__ */ jsx("span", { className: "rev-score__count", children: t("reviews.count", "{{count}} Bewertungen", {
          count
        }) })
      ] })
    ] }),
    /* @__PURE__ */ jsx("div", { className: "rev-grid", children: items.map((item, index) => {
      const name = item.name || t("reviews.anonymous", "Kunde");
      const date = formatDate(item.date, locale);
      const meta = [item.role, date].filter(Boolean).join(" · ");
      return /* @__PURE__ */ jsxs(
        "article",
        {
          className: "rev-card",
          children: [
            item.rating != null && /* @__PURE__ */ jsx(
              Stars,
              {
                value: item.rating,
                label: `${item.rating} / 5`
              }
            ),
            /* @__PURE__ */ jsx("blockquote", { className: "rev-card__quote", children: /* @__PURE__ */ jsx(SafeHtml, { html: item.comment }) }),
            /* @__PURE__ */ jsxs("footer", { className: "rev-card__author", children: [
              /* @__PURE__ */ jsx("span", { className: "rev-card__avatar", children: item.avatar ? /* @__PURE__ */ jsx(
                "img",
                {
                  src: item.avatar,
                  alt: "",
                  width: 44,
                  height: 44,
                  loading: "lazy",
                  decoding: "async",
                  "aria-hidden": "true"
                }
              ) : initials(name) }),
              /* @__PURE__ */ jsxs("span", { className: "rev-card__identity", children: [
                /* @__PURE__ */ jsx("strong", { className: "rev-card__name", children: name }),
                meta && /* @__PURE__ */ jsx("span", { className: "rev-card__meta", children: meta })
              ] })
            ] })
          ]
        },
        item.id ?? `review-${index}`
      );
    }) })
  ] }) });
});
ReviewsSection.displayName = "ReviewsSection";
function Home({
  content,
  currentRoute,
  settings
}) {
  var _a, _b, _c, _d, _e, _f, _g;
  const { props } = usePage();
  const homeRef = useRef(null);
  const locale = props.locale || "de";
  const seo = ((_a = props.settings) == null ? void 0 : _a.seo) || {};
  const siteName = renderText(
    (_c = (_b = props.settings) == null ? void 0 : _b.general) == null ? void 0 : _c.site_name,
    renderText((_e = (_d = props.settings) == null ? void 0 : _d.branding) == null ? void 0 : _e.site_name, "Reinigungsunternehmen")
  );
  const title = renderText(seo.meta_title, siteName);
  const description = renderText(seo.meta_description);
  const keywords = renderText(seo.meta_keywords);
  const ogTitle = renderText(seo.og_title, title);
  const ogDesc = renderText(seo.og_description, description);
  const ogImage = renderUrl(seo.og_image);
  const canonicalUrl = (_f = props.tenantSeo) == null ? void 0 : _f.canonicalUrl;
  const canonicalOrigin = (_g = props.tenantSeo) == null ? void 0 : _g.canonicalBaseUrl;
  useEffect(() => {
    const root = homeRef.current;
    if (!root) return void 0;
    const targets = root.querySelectorAll(
      ".svc-title, .services-title, .svc-card, .service-card, .reviews-section, .home-faq-section, .contact-content"
    );
    targets.forEach((target, index) => {
      target.classList.add("home-reveal");
      target.style.setProperty("--reveal-order", String(index % 6));
    });
    root.classList.add("home-motion-ready");
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-revealed");
          observer.unobserve(entry.target);
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -6%" }
    );
    targets.forEach((target) => observer.observe(target));
    return () => observer.disconnect();
  }, []);
  return /* @__PURE__ */ jsxs(AppLayout, { content, currentRoute, children: [
    /* @__PURE__ */ jsx(
      SeoHead,
      {
        title,
        description,
        keywords,
        canonical: canonicalUrl,
        image: ogImage,
        ogTitle,
        ogDescription: ogDesc,
        locale,
        origin: canonicalOrigin
      }
    ),
    /* @__PURE__ */ jsxs("div", { ref: homeRef, className: "home-page", children: [
      /* @__PURE__ */ jsx(HeroSection, { sliders: props.sliders }),
      /* @__PURE__ */ jsx(ServiceCategories, {}),
      /* @__PURE__ */ jsx(ServicesGrid, {}),
      /* @__PURE__ */ jsx(ReviewsSection, { content }),
      /* @__PURE__ */ jsx(HomeFaqSection, { content }),
      /* @__PURE__ */ jsx(ContactSection, { settings })
    ] })
  ] });
}
export {
  Home as default
};
