import { jsxs, jsx } from "react/jsx-runtime";
import "react";
import { usePage, Head } from "@inertiajs/react";
import { b as buildResponsiveImage, A as AppLayout, S as SeoHead, n as normalizeRichTextHeadings } from "./AppLayout-CWzTYdDK.js";
import { C as ContactSection } from "./ContactSection-BRQuvdQ_.js";
import { F as FaqSection } from "./FaqSection-yYPCKWXI.js";
import "react-icons/fa";
import "react-icons/fa6";
import "react-i18next";
import "html-react-parser";
import "isomorphic-dompurify";
import "react-dom";
import "js-cookie";
import "react-icons/fi";
const normalizeLocale = (locale = "de") => String(locale || "de").split("-")[0].toLowerCase();
const pickText = (...values) => {
  for (const value of values) {
    if (typeof value === "string" && value.trim() !== "") {
      return value.trim();
    }
  }
  return "";
};
const findTranslation = (translations, locale) => {
  if (!Array.isArray(translations)) return {};
  return translations.find(
    (item) => normalizeLocale((item == null ? void 0 : item.language_code) || "") === normalizeLocale(locale)
  ) || translations[0] || {};
};
function StaticPage() {
  const { page, slug, locale = "de", tenantSeo } = usePage().props;
  const normalized = normalizeLocale(locale);
  const tr = findTranslation(page == null ? void 0 : page.translations, normalized);
  const seo = (page == null ? void 0 : page.seo) || {};
  const title = pickText(tr.name, page == null ? void 0 : page.name, "Seite");
  const content = pickText(tr.content, page == null ? void 0 : page.content);
  const image = pickText(tr.image, page == null ? void 0 : page.image, seo.image);
  const heroImage = buildResponsiveImage(image, {
    widths: [640, 960, 1280, 1600],
    width: 1600,
    quality: 72
  });
  const metaTitle = pickText(
    tr.meta_title,
    seo.meta_title,
    page == null ? void 0 : page.meta_title,
    title
  );
  const metaDescription = pickText(
    tr.meta_description,
    seo.meta_description,
    page == null ? void 0 : page.meta_description
  ) || content.replace(/<[^>]+>/g, "").slice(0, 160);
  const metaKeywords = pickText(
    tr.meta_keywords,
    seo.meta_keywords,
    page == null ? void 0 : page.meta_keywords
  );
  const ogTitle = pickText(tr.og_title, seo.og_title, page == null ? void 0 : page.og_title, metaTitle);
  const ogDescription = pickText(
    tr.og_description,
    seo.og_description,
    page == null ? void 0 : page.og_description,
    metaDescription
  );
  const canonical = tenantSeo == null ? void 0 : tenantSeo.canonicalUrl;
  const faq = (page == null ? void 0 : page.faq) || null;
  let faqTitle = "";
  let faqItems = [];
  if (faq) {
    const faqTr = findTranslation(faq.translations, normalized);
    faqTitle = pickText(faqTr == null ? void 0 : faqTr.name, faq == null ? void 0 : faq.name, "FAQ");
    if (Array.isArray(faq.items)) {
      faqItems = faq.items.map((item) => {
        const itemTr = findTranslation(item.translations, normalized);
        return {
          id: item.id,
          question: pickText(itemTr == null ? void 0 : itemTr.question, item == null ? void 0 : item.question),
          answer: pickText(itemTr == null ? void 0 : itemTr.answer, item == null ? void 0 : item.answer)
        };
      });
    }
  }
  return /* @__PURE__ */ jsxs(AppLayout, { children: [
    /* @__PURE__ */ jsx(
      SeoHead,
      {
        title: metaTitle,
        description: metaDescription,
        keywords: metaKeywords,
        ogTitle,
        ogDescription,
        image,
        canonical,
        locale: normalized,
        origin: tenantSeo == null ? void 0 : tenantSeo.canonicalBaseUrl
      }
    ),
    heroImage.src && /* @__PURE__ */ jsx(Head, { children: /* @__PURE__ */ jsx(
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
    /* @__PURE__ */ jsxs("section", { className: `sp-hero ${image ? "sp-hero--has-img" : ""}`, children: [
      image && /* @__PURE__ */ jsx(
        "img",
        {
          src: heroImage.src,
          srcSet: heroImage.srcSet || void 0,
          sizes: "100vw",
          alt: title,
          className: "sp-hero__img",
          width: 1600,
          height: 450,
          loading: "eager",
          fetchpriority: "high",
          decoding: "async"
        }
      ),
      /* @__PURE__ */ jsx("div", { className: "sp-hero__overlay" }),
      /* @__PURE__ */ jsx("div", { className: "sp-hero__inner container", children: /* @__PURE__ */ jsx("h1", { className: "sp-title", children: title }) })
    ] }),
    content && /* @__PURE__ */ jsx("section", { className: "sp-content", children: /* @__PURE__ */ jsx("div", { className: "container", children: /* @__PURE__ */ jsx("article", { className: "sp-card", children: /* @__PURE__ */ jsx(
      "div",
      {
        className: "sp-card__body sp-prose",
        dangerouslySetInnerHTML: {
          __html: normalizeRichTextHeadings(
            content.replace(
              /\r\n|\n|\r/g,
              "<br />"
            )
          )
        }
      }
    ) }) }) }),
    /* @__PURE__ */ jsx(FaqSection, { title: faqTitle, items: faqItems, variant: "static" }),
    /* @__PURE__ */ jsx(ContactSection, {})
  ] });
}
export {
  StaticPage as default
};
