import { jsxs, jsx } from "react/jsx-runtime";
import "react";
import { usePage, Head } from "@inertiajs/react";
import { motion } from "framer-motion";
import { b as buildResponsiveImage, A as AppLayout, S as SeoHead, a as SafeHtml } from "./AppLayout-CWzTYdDK.js";
import { F as FaqSection } from "./FaqSection-yYPCKWXI.js";
import "react-icons/fa";
import "react-icons/fa6";
import "react-i18next";
import "html-react-parser";
import "isomorphic-dompurify";
import "react-dom";
import "js-cookie";
import "react-icons/fi";
const truncate = (text = "", max = 160) => String(text || "").replace(/<[^>]+>/g, "").replace(/\s+/g, " ").trim().slice(0, max);
const normalizeLang = (lang) => String(lang || "").toLowerCase().split("-")[0].trim() === "cz" ? "cs" : String(lang || "").toLowerCase().split("-")[0].trim();
const unwrapResource = (value) => {
  if (!value) return null;
  if (value.data && typeof value.data === "object") return value.data;
  return value;
};
const getTranslations = (item) => Array.isArray(item == null ? void 0 : item.translations) ? item.translations : [];
const getActiveTranslation = (item, locale, fallbackLocale = "de") => {
  const translations = getTranslations(item);
  const current = normalizeLang(locale);
  const fallback = normalizeLang(fallbackLocale);
  return translations.find(
    (tr) => normalizeLang(tr == null ? void 0 : tr.language_code) === current
  ) || translations.find(
    (tr) => normalizeLang(tr == null ? void 0 : tr.language_code) === fallback
  ) || translations.find((tr) => normalizeLang(tr == null ? void 0 : tr.language_code) === "de") || translations[0] || {};
};
const getServiceTitle = (item, locale) => {
  var _a, _b;
  const entity = unwrapResource(item);
  const tr = getActiveTranslation(
    entity,
    locale,
    ((_a = entity == null ? void 0 : entity._meta) == null ? void 0 : _a.default_language) || "de"
  );
  const metaTitle = ((_b = entity == null ? void 0 : entity.meta) == null ? void 0 : _b.title) || (entity == null ? void 0 : entity.meta_title) || "";
  return (tr == null ? void 0 : tr.name) || (tr == null ? void 0 : tr.title) || (entity == null ? void 0 : entity.name) || (entity == null ? void 0 : entity.title) || (entity == null ? void 0 : entity.category_name) || String(metaTitle).split("|")[0].trim() || (entity == null ? void 0 : entity.slug) || "Service";
};
const getServiceDescription = (item, locale) => {
  var _a, _b, _c, _d;
  const entity = unwrapResource(item);
  const tr = getActiveTranslation(
    entity,
    locale,
    ((_a = entity == null ? void 0 : entity._meta) == null ? void 0 : _a.default_language) || "de"
  );
  return (tr == null ? void 0 : tr.content) || (tr == null ? void 0 : tr.description) || (tr == null ? void 0 : tr.body) || (tr == null ? void 0 : tr.details) || (entity == null ? void 0 : entity.content) || (entity == null ? void 0 : entity.description) || (entity == null ? void 0 : entity.long_description) || (entity == null ? void 0 : entity.body) || (entity == null ? void 0 : entity.details) || (entity == null ? void 0 : entity.short_description) || ((_b = entity == null ? void 0 : entity.meta) == null ? void 0 : _b.description) || (entity == null ? void 0 : entity.meta_description) || ((_c = entity == null ? void 0 : entity.entity) == null ? void 0 : _c.expertise) || ((_d = entity == null ? void 0 : entity.entity) == null ? void 0 : _d.about) || "";
};
const getServiceShortDescription = (item, locale) => {
  var _a;
  const entity = unwrapResource(item);
  const tr = getActiveTranslation(
    entity,
    locale,
    ((_a = entity == null ? void 0 : entity._meta) == null ? void 0 : _a.default_language) || "de"
  );
  return (tr == null ? void 0 : tr.short_description) || (entity == null ? void 0 : entity.short_description) || truncate(getServiceDescription(entity, locale), 140);
};
const getCategoryName = (item, locale) => {
  var _a;
  const entity = unwrapResource(item);
  const tr = getActiveTranslation(
    entity,
    locale,
    ((_a = entity == null ? void 0 : entity._meta) == null ? void 0 : _a.default_language) || "de"
  );
  return (tr == null ? void 0 : tr.name) || (entity == null ? void 0 : entity.name) || (entity == null ? void 0 : entity.title) || "";
};
const pickText = (...values) => {
  for (const value of values) {
    if (typeof value === "string" && value.trim() !== "") {
      return value.trim();
    }
  }
  return "";
};
const pickMeta = (item, key) => {
  var _a, _b;
  return pickText(
    (_a = item == null ? void 0 : item.meta) == null ? void 0 : _a[key],
    (_b = item == null ? void 0 : item.meta) == null ? void 0 : _b[`meta_${key}`],
    item == null ? void 0 : item[`meta_${key}`],
    key === "title" ? item == null ? void 0 : item.seo_title : void 0,
    key === "description" ? item == null ? void 0 : item.seo_description : void 0,
    key === "keywords" ? item == null ? void 0 : item.seo_keywords : void 0
  );
};
const normalizeFaq = (faqSource, locale) => {
  var _a;
  const faq = Array.isArray(faqSource) ? { items: faqSource } : faqSource;
  if (!faq || typeof faq !== "object") {
    return { title: "", items: [] };
  }
  const faqTr = getActiveTranslation(
    faq,
    locale,
    ((_a = faq == null ? void 0 : faq._meta) == null ? void 0 : _a.default_language) || "de"
  );
  const title = pickText(faqTr == null ? void 0 : faqTr.name, faq == null ? void 0 : faq.name, faq == null ? void 0 : faq.title, "FAQ");
  const rawItems = Array.isArray(faq.items) ? faq.items : Array.isArray(faq.questions) ? faq.questions : [];
  const items = rawItems.map((item, index) => {
    var _a2;
    const itemTr = getActiveTranslation(
      item,
      locale,
      ((_a2 = item == null ? void 0 : item._meta) == null ? void 0 : _a2.default_language) || "de"
    );
    return {
      id: (item == null ? void 0 : item.id) ?? `${index}-${(item == null ? void 0 : item.question) || "faq"}`,
      question: pickText(
        itemTr == null ? void 0 : itemTr.question,
        item == null ? void 0 : item.question,
        item == null ? void 0 : item.title
      ),
      answer: pickText(itemTr == null ? void 0 : itemTr.answer, item == null ? void 0 : item.answer, item == null ? void 0 : item.content)
    };
  }).filter((item) => item.question && item.answer);
  return { title, items };
};
function ServiceShow() {
  var _a, _b, _c, _d, _e, _f, _g, _h;
  const { props } = usePage();
  const rawService = props.service ?? null;
  const rawCategory = props.category ?? null;
  const service = unwrapResource(rawService);
  const category = unwrapResource(rawCategory);
  const locale = props.locale || ((_a = service == null ? void 0 : service._meta) == null ? void 0 : _a.current_language) || ((_b = service == null ? void 0 : service._meta) == null ? void 0 : _b.default_language) || "de";
  const appName = ((_c = props.global) == null ? void 0 : _c.appName) || "Site";
  if (!service) {
    return /* @__PURE__ */ jsxs("section", { className: "service-show__loading", children: [
      /* @__PURE__ */ jsx("div", { className: "service-show__spinner" }),
      /* @__PURE__ */ jsx("p", { children: "Service not found." })
    ] });
  }
  const activeTr = getActiveTranslation(
    service,
    locale,
    ((_d = service == null ? void 0 : service._meta) == null ? void 0 : _d.default_language) || "en"
  );
  const faq = normalizeFaq(
    (props == null ? void 0 : props.faq) || (service == null ? void 0 : service.faq) || (service == null ? void 0 : service.faqs),
    locale
  );
  const title = getServiceTitle(service, locale);
  const description = getServiceDescription(service, locale);
  const shortDescription = getServiceShortDescription(service, locale);
  const categoryName = getCategoryName(category, locale);
  const image = (service == null ? void 0 : service.image) || "https://images.unsplash.com/photo-1581578731117-e0a820bd4928?q=80&w=1920&auto=format&fit=crop";
  const heroImage = buildResponsiveImage(image, {
    widths: [640, 960, 1280, 1600],
    width: 1600,
    quality: 72,
    transform: !image.includes("/media-cache/")
  });
  const seoTitle = pickMeta(service, "title") || (activeTr == null ? void 0 : activeTr.meta_title) || `${title} - ${appName}`;
  const seoDescription = pickMeta(service, "description") || (activeTr == null ? void 0 : activeTr.meta_description) || shortDescription || truncate(description);
  const seoKeywords = pickMeta(service, "keywords") || (activeTr == null ? void 0 : activeTr.meta_keywords) || "";
  const slug = (service == null ? void 0 : service.slug) || "";
  const canonicalUrl = `/${locale}/${slug}`;
  const seoState = (props == null ? void 0 : props.seo) || {};
  return /* @__PURE__ */ jsxs(AppLayout, { children: [
    /* @__PURE__ */ jsx(
      SeoHead,
      {
        title: seoTitle,
        description: seoDescription,
        keywords: seoKeywords,
        ogTitle: pickText(
          (_e = service == null ? void 0 : service.meta) == null ? void 0 : _e.og_title,
          service == null ? void 0 : service.og_title,
          seoTitle
        ),
        ogDescription: pickText(
          (_f = service == null ? void 0 : service.meta) == null ? void 0 : _f.og_description,
          service == null ? void 0 : service.og_description,
          seoDescription
        ),
        canonical: seoState.canonical || canonicalUrl,
        image,
        locale,
        origin: (_g = props == null ? void 0 : props.tenantSeo) == null ? void 0 : _g.canonicalBaseUrl,
        alternates: seoState.alternates || [],
        xDefault: seoState.x_default,
        noindex: seoState.indexable === false
      }
    ),
    /* @__PURE__ */ jsx(Head, { children: /* @__PURE__ */ jsx("script", { type: "application/ld+json", children: JSON.stringify({
      "@context": "https://schema.org",
      "@type": "Service",
      ...((_h = props == null ? void 0 : props.tenantSeo) == null ? void 0 : _h.canonicalUrl) ? {
        "@id": `${props.tenantSeo.canonicalUrl}#service`,
        url: props.tenantSeo.canonicalUrl
      } : {},
      name: title,
      description: truncate(description),
      ...faq.items.length ? {
        subjectOf: {
          "@type": "FAQPage",
          mainEntity: faq.items.map((item) => ({
            "@type": "Question",
            name: item.question,
            acceptedAnswer: {
              "@type": "Answer",
              text: item.answer
            }
          }))
        }
      } : {}
    }) }) }),
    /* @__PURE__ */ jsx(Head, { children: /* @__PURE__ */ jsx(
      "link",
      {
        rel: "preload",
        as: "image",
        href: heroImage.src,
        imageSrcSet: heroImage.srcSet || void 0,
        imageSizes: "100vw",
        fetchpriority: "high"
      }
    ) }),
    /* @__PURE__ */ jsxs(
      motion.section,
      {
        className: "service-show__hero",
        initial: { opacity: 0 },
        animate: { opacity: 1 },
        transition: { duration: 0.6 },
        children: [
          /* @__PURE__ */ jsxs("div", { className: "service-show__hero-media", children: [
            /* @__PURE__ */ jsx(
              "img",
              {
                src: heroImage.src,
                srcSet: heroImage.srcSet || void 0,
                sizes: "100vw",
                width: 1600,
                height: 700,
                alt: title,
                className: "service-show__hero-img",
                loading: "eager",
                fetchpriority: "high",
                decoding: "async"
              }
            ),
            /* @__PURE__ */ jsx("div", { className: "service-show__hero-overlay" })
          ] }),
          /* @__PURE__ */ jsxs(
            motion.div,
            {
              className: "service-show__hero-content",
              initial: { opacity: 0, y: 20 },
              animate: { opacity: 1, y: 0 },
              transition: { duration: 0.6, delay: 0.2 },
              children: [
                categoryName && /* @__PURE__ */ jsx("p", { className: "service-show__badge", children: categoryName }),
                /* @__PURE__ */ jsx("h1", { className: "service-show__title", children: title })
              ]
            }
          )
        ]
      }
    ),
    description && /* @__PURE__ */ jsx(
      motion.section,
      {
        className: "service-show__content fade-up",
        initial: { opacity: 0, y: 20 },
        animate: { opacity: 1, y: 0 },
        transition: { duration: 0.7 },
        children: /* @__PURE__ */ jsx("div", { className: "service-show__content-inner service-show__content-grid", children: /* @__PURE__ */ jsx("article", { className: "service-show__prose", children: /* @__PURE__ */ jsx(SafeHtml, { html: description }) }) })
      }
    ),
    /* @__PURE__ */ jsx(FaqSection, { title: faq.title, items: faq.items, variant: "service" })
  ] });
}
export {
  ServiceShow as default
};
