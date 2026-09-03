import { jsxs, jsx } from "react/jsx-runtime";
import { useState, useEffect } from "react";
import { usePage, Link } from "@inertiajs/react";
import { r as renderUrl, A as AppLayout, S as SeoHead } from "./AppLayout-CWzTYdDK.js";
import DOMPurify from "isomorphic-dompurify";
import parse from "html-react-parser";
import { useTranslation } from "react-i18next";
import { FiHome, FiMail, FiRefreshCw } from "react-icons/fi";
/* empty css                    */
import "react-icons/fa";
import "react-icons/fa6";
import "react-dom";
import "js-cookie";
function NotFound() {
  var _a;
  const { t } = useTranslation();
  const { props } = usePage();
  const { status = 404, page, global = {} } = props;
  const branding = ((_a = props.settings) == null ? void 0 : _a.branding) || {};
  const logo = renderUrl((branding == null ? void 0 : branding.site_logo_url) || (branding == null ? void 0 : branding.logo_url));
  const darkLogo = renderUrl(
    (branding == null ? void 0 : branding.site_dark_logo_url) || (branding == null ? void 0 : branding.dark_logo_url) || (branding == null ? void 0 : branding.site_logo_url) || (branding == null ? void 0 : branding.logo_url)
  );
  const siteName = (branding == null ? void 0 : branding.site_name) || "Logo";
  const [isDark, setIsDark] = useState(false);
  useEffect(() => {
    if (typeof document === "undefined" || typeof window === "undefined") {
      return;
    }
    setIsDark(document.documentElement.classList.contains("dark"));
    const observer = new MutationObserver(() => {
      setIsDark(document.documentElement.classList.contains("dark"));
    });
    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["class"]
    });
    return () => observer.disconnect();
  }, []);
  const logoSrc = isDark && darkLogo ? darkLogo : logo;
  const locale = props.locale || "de";
  const is500 = status >= 500;
  const safeContent = parse(
    DOMPurify.sanitize(
      (page == null ? void 0 : page.content) || t(is500 ? "errors.notFound.500" : "errors.notFound.404")
    )
  );
  const title = (page == null ? void 0 : page.title) || (page == null ? void 0 : page.meta_title) || (is500 ? "500 — Serverfehler" : "404 — Seite nicht gefunden");
  const description = (page == null ? void 0 : page.meta_description) || (page == null ? void 0 : page.content) || t(is500 ? "errors.notFound.500" : "errors.notFound.404");
  return /* @__PURE__ */ jsxs(AppLayout, { children: [
    /* @__PURE__ */ jsx(SeoHead, { title, description, noindex: true }),
    /* @__PURE__ */ jsx("section", { className: "error-page", children: /* @__PURE__ */ jsxs("div", { className: "error-container", children: [
      logoSrc && /* @__PURE__ */ jsx("div", { className: "error-logo-wrapper", children: /* @__PURE__ */ jsx(
        Link,
        {
          href: `/${locale}`,
          className: "error-logo-link",
          children: /* @__PURE__ */ jsx(
            "img",
            {
              src: logoSrc,
              alt: siteName,
              className: "error-logo",
              width: 210,
              height: 80,
              loading: "lazy",
              decoding: "async"
            }
          )
        }
      ) }),
      /* @__PURE__ */ jsx("h1", { className: "error-status", children: status }),
      /* @__PURE__ */ jsx("div", { className: "error-message", children: safeContent }),
      /* @__PURE__ */ jsxs("div", { className: "error-actions", children: [
        /* @__PURE__ */ jsxs(Link, { href: `/${locale}`, className: "error-btn primary", children: [
          /* @__PURE__ */ jsx(FiHome, { size: 20 }),
          t("errors.notFound.home")
        ] }),
        !is500 && /* @__PURE__ */ jsxs(
          Link,
          {
            href: `/${locale}/kontakt`,
            className: "error-btn secondary",
            children: [
              /* @__PURE__ */ jsx(FiMail, { size: 20 }),
              t("errors.notFound.contact")
            ]
          }
        ),
        is500 && /* @__PURE__ */ jsxs(
          "button",
          {
            onClick: () => window.location.reload(),
            className: "error-btn secondary",
            children: [
              /* @__PURE__ */ jsx(FiRefreshCw, { size: 20 }),
              t("errors.notFound.reload")
            ]
          }
        )
      ] })
    ] }) })
  ] });
}
export {
  NotFound as default
};
