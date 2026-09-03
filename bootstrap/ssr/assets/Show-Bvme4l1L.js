import { jsxs, jsx } from "react/jsx-runtime";
import "react";
import { usePage, Head, Link } from "@inertiajs/react";
import { b as buildResponsiveImage, A as AppLayout, S as SeoHead, a as SafeHtml } from "./AppLayout-CWzTYdDK.js";
import { F as FaqSection } from "./FaqSection-yYPCKWXI.js";
/* empty css              */
import "react-icons/fa";
import "react-icons/fa6";
import "react-i18next";
import "html-react-parser";
import "isomorphic-dompurify";
import "react-dom";
import "js-cookie";
import "react-icons/fi";
const formatDateTime = (value, locale = "de") => {
  if (!value) return "";
  try {
    return new Intl.DateTimeFormat(locale, {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit"
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
function BlogShow() {
  var _a, _b, _c, _d, _e, _f;
  const { post, relatedPosts = [], locale = "de", ziggy, tenantSeo } = usePage().props;
  const base = localeBase(locale, ziggy == null ? void 0 : ziggy.location);
  const faq = (post == null ? void 0 : post.faq) || { title: "", items: [] };
  const articleImage = buildResponsiveImage(post == null ? void 0 : post.image, {
    widths: [480, 768, 1040],
    width: 1040,
    quality: 72,
    transform: !((_a = post == null ? void 0 : post.image) == null ? void 0 : _a.includes("/media-cache/"))
  });
  return /* @__PURE__ */ jsxs(AppLayout, { children: [
    /* @__PURE__ */ jsx(
      SeoHead,
      {
        title: ((_b = post.meta) == null ? void 0 : _b.title) || post.title,
        description: ((_c = post.meta) == null ? void 0 : _c.description) || post.excerpt,
        keywords: (_d = post.meta) == null ? void 0 : _d.keywords,
        canonical: tenantSeo == null ? void 0 : tenantSeo.canonicalUrl,
        image: post.image,
        locale,
        origin: tenantSeo == null ? void 0 : tenantSeo.canonicalBaseUrl
      }
    ),
    articleImage.src && /* @__PURE__ */ jsx(Head, { children: /* @__PURE__ */ jsx(
      "link",
      {
        rel: "preload",
        as: "image",
        href: articleImage.src,
        imageSrcSet: articleImage.srcSet || void 0,
        imageSizes: "(max-width: 900px) 92vw, 520px",
        fetchpriority: "high"
      }
    ) }),
    /* @__PURE__ */ jsxs("main", { className: "blog-page blog-page--show", children: [
      /* @__PURE__ */ jsx("section", { className: "blog-article-hero", children: /* @__PURE__ */ jsx("div", { className: "blog-shell", children: /* @__PURE__ */ jsxs("div", { className: "blog-article-hero__inner", children: [
        /* @__PURE__ */ jsxs("div", { children: [
          /* @__PURE__ */ jsx(Link, { href: `${base}/blog`, className: "blog-back-link", children: "Blog" }),
          /* @__PURE__ */ jsxs("div", { className: "blog-badges", children: [
            /* @__PURE__ */ jsx("span", { children: (_e = post.category) == null ? void 0 : _e.name }),
            ((_f = post.primary_service) == null ? void 0 : _f.name) && /* @__PURE__ */ jsx("span", { children: post.primary_service.name }),
            post.city && /* @__PURE__ */ jsx("span", { children: post.city })
          ] }),
          /* @__PURE__ */ jsx("h1", { className: "blog-article-title", children: post.title }),
          post.excerpt && /* @__PURE__ */ jsx("p", { className: "blog-article-excerpt", children: post.excerpt }),
          /* @__PURE__ */ jsxs("div", { className: "blog-article-meta", children: [
            /* @__PURE__ */ jsx("span", { children: formatDateTime(post.published_at, locale) }),
            post.reading_time && /* @__PURE__ */ jsxs("span", { children: [
              post.reading_time,
              " min"
            ] }),
            /* @__PURE__ */ jsx("span", { children: post.slug })
          ] })
        ] }),
        post.image && /* @__PURE__ */ jsx(
          "img",
          {
            className: "blog-article-image",
            src: articleImage.src,
            srcSet: articleImage.srcSet || void 0,
            sizes: "(max-width: 900px) 92vw, 520px",
            alt: post.title,
            width: 520,
            height: 340,
            loading: "eager",
            fetchpriority: "high",
            decoding: "async"
          }
        )
      ] }) }) }),
      /* @__PURE__ */ jsx("section", { className: "blog-article-section", children: /* @__PURE__ */ jsx("div", { className: "blog-shell", children: /* @__PURE__ */ jsx("article", { className: "blog-content blog-content--single", children: /* @__PURE__ */ jsx("div", { className: "blog-prose", children: post.content ? /* @__PURE__ */ jsx(SafeHtml, { html: post.content }) : /* @__PURE__ */ jsx("p", { children: "Dieser Beitrag hat noch keinen Inhalt." }) }) }) }) }),
      /* @__PURE__ */ jsx(FaqSection, { title: faq.title, items: faq.items || [], variant: "static" }),
      relatedPosts.length > 0 && /* @__PURE__ */ jsx("section", { className: "blog-related", children: /* @__PURE__ */ jsxs("div", { className: "blog-shell", children: [
        /* @__PURE__ */ jsx("h2", { children: "Related posts" }),
        /* @__PURE__ */ jsx("div", { className: "blog-related__grid", children: relatedPosts.map((item) => {
          var _a2;
          return /* @__PURE__ */ jsxs(
            Link,
            {
              href: `${base}/blog/${item.slug}`,
              className: "blog-related__item",
              children: [
                /* @__PURE__ */ jsx("span", { children: (_a2 = item.category) == null ? void 0 : _a2.name }),
                /* @__PURE__ */ jsx("strong", { children: item.title })
              ]
            },
            item.slug
          );
        }) })
      ] }) })
    ] })
  ] });
}
export {
  BlogShow as default
};
