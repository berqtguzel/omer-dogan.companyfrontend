import { jsxs, jsx } from "react/jsx-runtime";
import "react";
import { usePage, Link } from "@inertiajs/react";
import { A as AppLayout, S as SeoHead, n as normalizeRichTextHeadings, b as buildResponsiveImage } from "./AppLayout-CWzTYdDK.js";
import { n as normalizeLocale, f as findTranslation, b as buildPageSeo, p as pickText } from "./pageSeo-DZ9HqiL0.js";
/* empty css              */
import "react-icons/fa";
import "react-icons/fa6";
import "react-i18next";
import "html-react-parser";
import "isomorphic-dompurify";
import "react-dom";
import "js-cookie";
import "react-icons/fi";
const formatDate = (value, locale = "de") => {
  if (!value) return "";
  try {
    return new Intl.DateTimeFormat(locale, {
      day: "2-digit",
      month: "2-digit",
      year: "numeric"
    }).format(new Date(value.replace(" ", "T")));
  } catch {
    return value;
  }
};
const localeBase = (locale = "de", location = "") => {
  try {
    const path = new URL(location).pathname;
    return path === `/${locale}/blog` || path.startsWith(`/${locale}/blog/`) ? `/${locale}` : locale === "de" ? "" : `/${locale}`;
  } catch {
    return locale === "de" ? "" : `/${locale}`;
  }
};
const pageLabel = (page, translation, key, fallback = "") => {
  var _a, _b;
  return pickText(
    translation == null ? void 0 : translation[key],
    page == null ? void 0 : page[key],
    (_a = page == null ? void 0 : page.labels) == null ? void 0 : _a[key],
    (_b = page == null ? void 0 : page.ui) == null ? void 0 : _b[key],
    fallback
  );
};
function BlogIndex() {
  const {
    posts = [],
    categories = [],
    page,
    locale = "de",
    selectedCategory,
    ziggy,
    tenantSeo
  } = usePage().props;
  const base = localeBase(locale, ziggy == null ? void 0 : ziggy.location);
  const blogHref = `${base}/blog`;
  const normalizedLocale = normalizeLocale(locale);
  const pageTranslation = findTranslation(
    page == null ? void 0 : page.translations,
    normalizedLocale
  );
  const seo = buildPageSeo(page, locale, {
    title: "Blog",
    canonical: tenantSeo == null ? void 0 : tenantSeo.canonicalUrl
  });
  const pageTitle = pickText(
    pageTranslation.name,
    page == null ? void 0 : page.name,
    seo.title,
    "Blog"
  );
  const pageContent = pickText(pageTranslation.content, page == null ? void 0 : page.content);
  const heroDescription = pickText(
    pageTranslation.description,
    page == null ? void 0 : page.description,
    seo.description
  );
  const eyebrow = pickText(
    pageTranslation.subtitle,
    page == null ? void 0 : page.subtitle,
    page == null ? void 0 : page.label,
    ""
  );
  const allLabel = pageLabel(page, pageTranslation, "all_label", "Alle");
  const readLabel = pageLabel(
    page,
    pageTranslation,
    "read_more_label",
    "Lesen"
  );
  const emptyTitle = pageLabel(
    page,
    pageTranslation,
    "empty_title",
    "Keine Blogbeitrage gefunden"
  );
  const emptyText = pageLabel(
    page,
    pageTranslation,
    "empty_text",
    "Sobald Inhalte uber die Blog API verfugbar sind, erscheinen sie hier automatisch."
  );
  return /* @__PURE__ */ jsxs(AppLayout, { children: [
    /* @__PURE__ */ jsx(
      SeoHead,
      {
        title: seo.title,
        description: seo.description,
        keywords: seo.keywords,
        ogTitle: seo.ogTitle,
        ogDescription: seo.ogDescription,
        image: seo.image,
        canonical: tenantSeo == null ? void 0 : tenantSeo.canonicalUrl,
        locale: seo.locale,
        origin: tenantSeo == null ? void 0 : tenantSeo.canonicalBaseUrl
      }
    ),
    /* @__PURE__ */ jsxs("main", { className: "blog-page", children: [
      /* @__PURE__ */ jsx("section", { className: "blog-hero", children: /* @__PURE__ */ jsxs("div", { className: "blog-shell", children: [
        eyebrow && /* @__PURE__ */ jsx("p", { className: "blog-eyebrow", children: eyebrow }),
        /* @__PURE__ */ jsx("h1", { className: "blog-title", children: pageTitle }),
        heroDescription && /* @__PURE__ */ jsx("p", { className: "blog-subtitle", children: heroDescription }),
        /* @__PURE__ */ jsxs(
          "div",
          {
            className: "blog-categories",
            "aria-label": "Blog categories",
            children: [
              selectedCategory && /* @__PURE__ */ jsx(
                Link,
                {
                  href: blogHref,
                  className: "blog-category-chip",
                  children: allLabel
                }
              ),
              categories.map((category) => /* @__PURE__ */ jsx(
                Link,
                {
                  href: `${blogHref}?category=${encodeURIComponent(category.slug)}`,
                  className: `blog-category-chip ${selectedCategory === category.slug ? "is-active" : ""}`,
                  children: category.name
                },
                category.slug
              ))
            ]
          }
        )
      ] }) }),
      /* @__PURE__ */ jsx("section", { className: "blog-list-section", children: /* @__PURE__ */ jsxs("div", { className: "blog-shell", children: [
        pageContent && /* @__PURE__ */ jsx(
          "div",
          {
            className: "blog-page-content",
            dangerouslySetInnerHTML: {
              __html: normalizeRichTextHeadings(
                pageContent
              )
            }
          }
        ),
        posts.length === 0 ? /* @__PURE__ */ jsxs("div", { className: "blog-empty", children: [
          /* @__PURE__ */ jsx("h2", { children: emptyTitle }),
          /* @__PURE__ */ jsx("p", { children: emptyText })
        ] }) : /* @__PURE__ */ jsx(
          "div",
          {
            className: `blog-list blog-list--count-${Math.min(posts.length, 3)}`,
            children: posts.map((post) => {
              var _a, _b;
              const cardImage = buildResponsiveImage(
                post.image,
                {
                  widths: [320, 480, 640],
                  width: 480,
                  quality: 70,
                  transform: !((_a = post.image) == null ? void 0 : _a.includes(
                    "/media-cache/"
                  ))
                }
              );
              return /* @__PURE__ */ jsxs(
                "article",
                {
                  className: `blog-card ${post.image ? "" : "blog-card--no-media"}`,
                  children: [
                    post.image && /* @__PURE__ */ jsx(
                      Link,
                      {
                        href: `${base}/blog/${post.slug}`,
                        className: "blog-card__media",
                        children: /* @__PURE__ */ jsx(
                          "img",
                          {
                            src: cardImage.src,
                            srcSet: cardImage.srcSet || void 0,
                            sizes: "(max-width: 768px) 92vw, 380px",
                            alt: post.title,
                            width: 480,
                            height: 300,
                            loading: "lazy",
                            decoding: "async"
                          }
                        )
                      }
                    ),
                    /* @__PURE__ */ jsxs("div", { className: "blog-card__body", children: [
                      /* @__PURE__ */ jsxs("div", { className: "blog-card__meta", children: [
                        /* @__PURE__ */ jsx("span", { children: (_b = post.category) == null ? void 0 : _b.name }),
                        /* @__PURE__ */ jsx("span", { children: formatDate(
                          post.published_at,
                          locale
                        ) }),
                        post.reading_time && /* @__PURE__ */ jsxs("span", { children: [
                          post.reading_time,
                          " min"
                        ] })
                      ] }),
                      /* @__PURE__ */ jsx("h2", { className: "blog-card__title", children: /* @__PURE__ */ jsx(
                        Link,
                        {
                          href: `${base}/blog/${post.slug}`,
                          children: post.title
                        }
                      ) }),
                      /* @__PURE__ */ jsx("p", { className: "blog-card__excerpt", children: post.excerpt }),
                      /* @__PURE__ */ jsx("div", { className: "blog-card__tags", children: (post.tags || []).slice(0, 4).map((tag) => /* @__PURE__ */ jsx("span", { children: tag }, tag)) }),
                      /* @__PURE__ */ jsx(
                        Link,
                        {
                          href: `${base}/blog/${post.slug}`,
                          className: "blog-card__link",
                          children: readLabel
                        }
                      )
                    ] })
                  ]
                },
                post.id
              );
            })
          }
        )
      ] }) })
    ] })
  ] });
}
export {
  BlogIndex as default
};
