import { jsxs, jsx } from "react/jsx-runtime";
import { memo, useState } from "react";
import { useTranslation } from "react-i18next";
import { Link, usePage } from "@inertiajs/react";
import { b as buildResponsiveImage, a as SafeHtml } from "./AppLayout-CWzTYdDK.js";
const API_BASE = "https://omerdogan.de".replace(/\/+$/, "");
function formatServiceTitleFromSlug(slug) {
  return String(slug || "").replace(/[-_]+/g, " ").replace(/\s+/g, " ").trim().split(" ").filter(Boolean).map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(" ");
}
function looksLikeSlugTitle(value = "") {
  const text = String(value || "").trim();
  if (!text) return false;
  return text.includes("-") || text.includes("_") || text === text.toLowerCase() && !text.includes(" ");
}
function resolveServiceTitle(item = {}) {
  var _a, _b, _c, _d;
  const categoryName = item.category_name || ((_a = item.category) == null ? void 0 : _a.name) || item.service_name || ((_b = item.service) == null ? void 0 : _b.name) || formatServiceTitleFromSlug(
    item.category_slug || item.service_slug || ((_c = item.category) == null ? void 0 : _c.slug) || ((_d = item.service) == null ? void 0 : _d.slug)
  );
  const rawTitle = item.title || item.name || item.page_title || item.meta_title || "";
  if (looksLikeSlugTitle(rawTitle)) {
    return formatServiceTitleFromSlug(rawTitle || item.slug);
  }
  return rawTitle || categoryName || formatServiceTitleFromSlug(item.slug) || "Service";
}
function normalizeUrl(url) {
  const value = String(url || "").trim();
  if (!value || /^\d+$/.test(value)) return null;
  if (value.startsWith("http") || value.startsWith("/")) return value;
  if (!API_BASE) return `/${value.replace(/^\/+/, "")}`;
  return `${API_BASE}/${value.replace(/^\/+/, "")}`;
}
const ServiceCard = memo((props) => {
  const { image, slug } = props;
  const { t, i18n } = useTranslation();
  const lang = (i18n.language || "de").split("-")[0].toLowerCase();
  const localePrefix = `/${lang}`;
  const displayTitle = resolveServiceTitle(props);
  const finalImage = normalizeUrl(image);
  const isLocalMirror = Boolean(
    finalImage && (finalImage.startsWith("/storage/") || finalImage.startsWith("/media-cache/") || finalImage.startsWith("/media-proxy/") || finalImage.includes("/storage/media-cache/"))
  );
  const responsiveImage = buildResponsiveImage(finalImage, {
    widths: [320, 480, 640],
    width: 480,
    quality: 70,
    transform: !isLocalMirror
  });
  const imageSrc = responsiveImage.src;
  const href = `${localePrefix}/${slug || ""}`.replace(/\/+/g, "/");
  const [imageFailed, setImageFailed] = useState(false);
  return /* @__PURE__ */ jsxs(Link, { href, className: "service-card", "aria-label": displayTitle, children: [
    /* @__PURE__ */ jsx("div", { className: "service-card__image-wrapper", children: finalImage && !imageFailed ? /* @__PURE__ */ jsx(
      "img",
      {
        src: imageSrc,
        srcSet: responsiveImage.srcSet || void 0,
        sizes: "(max-width: 640px) 92vw, (max-width: 1200px) 45vw, 400px",
        alt: displayTitle,
        className: "service-card__image",
        width: "400",
        height: "400",
        loading: "eager",
        decoding: "async",
        fetchpriority: "auto",
        onError: () => setImageFailed(true)
      }
    ) : /* @__PURE__ */ jsx(
      "div",
      {
        className: "service-card__image-placeholder",
        "aria-hidden": "true"
      }
    ) }),
    /* @__PURE__ */ jsxs("div", { className: "service-card__content", children: [
      /* @__PURE__ */ jsx("h3", { className: "service-card__title", children: /* @__PURE__ */ jsx(SafeHtml, { html: displayTitle, inline: true }) }),
      /* @__PURE__ */ jsxs("span", { className: "service-card__button", children: [
        /* @__PURE__ */ jsx("span", { children: t("services.card.button", "Details") }),
        /* @__PURE__ */ jsx("svg", { className: "service-card__arrow", viewBox: "0 0 24 24", children: /* @__PURE__ */ jsx(
          "path",
          {
            d: "M5 12H19M19 12L12 5M19 12L12 19",
            stroke: "currentColor",
            strokeWidth: "2",
            strokeLinecap: "round",
            strokeLinejoin: "round"
          }
        ) })
      ] })
    ] })
  ] });
});
ServiceCard.displayName = "ServiceCard";
const ServicesGrid = () => {
  var _a;
  const { t } = useTranslation();
  const { props } = usePage();
  const content = (props == null ? void 0 : props.content) || {};
  const globalCategories = (_a = props.global) == null ? void 0 : _a.categories;
  const categories = Array.isArray(globalCategories) && globalCategories.length > 0 ? globalCategories : props.categories || [];
  return /* @__PURE__ */ jsx("section", { id: "services", className: "services-section", children: /* @__PURE__ */ jsxs("div", { className: "services-container", children: [
    /* @__PURE__ */ jsx("h2", { className: "services-title", children: /* @__PURE__ */ jsx(
      SafeHtml,
      {
        html: content.services_title || t("servicesList.title"),
        inline: true
      }
    ) }),
    /* @__PURE__ */ jsxs("div", { className: "services-grid", children: [
      categories.map((cat) => {
        const name = cat.name || "Service";
        return /* @__PURE__ */ jsx(
          ServiceCard,
          {
            title: name,
            image: cat.image,
            slug: cat.slug
          },
          cat.id
        );
      }),
      !categories.length && /* @__PURE__ */ jsx("div", { className: "no-services-message", children: t("servicesList.no_services") })
    ] })
  ] }) });
};
const ServicesGrid$1 = memo(ServicesGrid);
export {
  ServicesGrid$1 as S
};
