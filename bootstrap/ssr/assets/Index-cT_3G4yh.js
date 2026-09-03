import { jsxs, jsx } from "react/jsx-runtime";
import "react";
import { usePage } from "@inertiajs/react";
import { A as AppLayout, S as SeoHead } from "./AppLayout-CWzTYdDK.js";
import { S as ServicesGrid } from "./ServicesGrid-C15Cnj03.js";
import { b as buildPageSeo } from "./pageSeo-DZ9HqiL0.js";
import "react-icons/fa";
import "react-icons/fa6";
import "react-i18next";
import "html-react-parser";
import "isomorphic-dompurify";
import "react-dom";
import "js-cookie";
import "react-icons/fi";
function ServicesIndex() {
  const { page, locale = "de", tenantSeo } = usePage().props;
  const seo = buildPageSeo(page, locale, {
    title: "Reinigungsleistungen",
    canonical: tenantSeo == null ? void 0 : tenantSeo.canonicalUrl
  });
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
    /* @__PURE__ */ jsx("div", { className: "services-index-page", children: /* @__PURE__ */ jsx(ServicesGrid, {}) })
  ] });
}
export {
  ServicesIndex as default
};
