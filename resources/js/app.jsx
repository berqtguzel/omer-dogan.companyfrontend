import "./bootstrap";
import "../css/app.css";
import "../css/theme.css";

import(/* webpackChunkName: "non-critical-css" */ "../css/loading.css").catch(
    () => {},
);

import React from "react";
import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { I18nextProvider } from "react-i18next";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";

import i18n from "./i18n";
import { ThemeProvider } from "./Context/ThemeContext";
import {
    initializeAnalytics,
    trackPageView,
} from "./utils/analyticsQueue";
import {
    initializeButtonTracking,
    setButtonTrackingTenant,
} from "./utils/buttonTracking";

const APP_NAME = "Website";

const syncI18nFromPage = (page) => {
    const locale = page?.props?.locale;

    if (!locale || typeof locale !== "string") {
        return;
    }

    const code = locale.toLowerCase().slice(0, 2);

    if (i18n.language === code) {
        return;
    }

    void i18n.changeLanguage(code);

    try {
        localStorage.setItem("locale", code);
        localStorage.setItem("i18nextLng", code);
    } catch {}
};

createInertiaApp({
    title: (title) => title || APP_NAME,

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob("./Pages/**/*.{jsx,tsx}"),
        ),

    setup({ el, App, props }) {
        syncI18nFromPage(props.initialPage);

        const onInertiaSuccess = (event) => {
            const page = event?.detail?.page;

            if (page) {
                syncI18nFromPage(page);
                if (page.props?.tenantId) {
                    setButtonTrackingTenant(page.props.tenantId);
                }
                trackPageView();
            }
        };

        document.addEventListener("inertia:success", onInertiaSuccess);
        initializeAnalytics();
        initializeButtonTracking({
            tenantId: props.initialPage?.props?.tenantId,
        });

        const app = (
            <I18nextProvider i18n={i18n}>
                <ThemeProvider
                    initial={props?.initialPage?.props?.theme ?? "light"}
                >
                    <App {...props} />
                </ThemeProvider>
            </I18nextProvider>
        );

        createRoot(el).render(app);
    },

    progress: {
        color: "var(--site-primary-color)",
        delay: 80,
        showSpinner: false,
    },
});
