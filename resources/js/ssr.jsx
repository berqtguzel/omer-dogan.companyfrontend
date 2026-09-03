import React from "react";
import ReactDOMServer from "react-dom/server";
import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";

import { I18nextProvider } from "react-i18next";
import { ThemeProvider as SSRThemeProvider } from "./Context/ThemeContext.ssr";
import i18n from "./i18n";

const resolveAppName = (page) =>
    page?.props?.global?.settings?.seo?.meta_title ||
    page?.props?.global?.settings?.general?.site_name ||
    page?.props?.global?.settings?.branding?.site_name ||
    "Website";

createServer(async (page) => {
    const locale = page.props.locale || "de";
    await i18n.changeLanguage(locale);

    return createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => title || resolveAppName(page),
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.jsx`,
                import.meta.glob("./Pages/**/*.jsx"),
            ),

        setup: ({ App, props }) => (
            <I18nextProvider i18n={i18n}>
                <SSRThemeProvider initial="light">
                    <App {...props} />
                </SSRThemeProvider>
            </I18nextProvider>
        ),
    });
});
