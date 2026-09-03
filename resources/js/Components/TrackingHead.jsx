import { useEffect } from "react";
import { Head } from "@inertiajs/react";

const clean = (value) => (typeof value === "string" ? value.trim() : "");

const enabled = (value) => {
    const normalized = clean(value).toLowerCase();

    return ["1", "true", "t", "yes", "on"].includes(normalized);
};

const isPlaceholder = (value) => enabled(value);
const isGtmId = (value) => /^GTM-[A-Z0-9]+$/i.test(clean(value));
const isGaId = (value) => /^(G|UA)-[A-Z0-9-]+$/i.test(clean(value));
const isAdsId = (value) => /^AW-[A-Z0-9-]+$/i.test(clean(value));
const isHotjarId = (value) => /^\d+$/.test(clean(value));

export default function TrackingHead({ analytics = {} }) {
    const gtmId = clean(analytics.google_tag_manager_id);
    const gaId = clean(analytics.google_analytics_id);
    const adsId = clean(analytics.google_ads_conversion);
    const hotjarId = clean(analytics.hotjar_id);
    const rawSearchConsole = clean(analytics.google_search_console_property);
    const searchConsole = isPlaceholder(rawSearchConsole)
        ? ""
        : rawSearchConsole;

    const useGtm = enabled(analytics.google_tag_manager) && isGtmId(gtmId);
    const useGa = enabled(analytics.google_analytics) && isGaId(gaId);
    const useAds = isAdsId(adsId);
    const useHotjar = isHotjarId(hotjarId);

    useEffect(() => {
        if (typeof window === "undefined") {
            return;
        }

        const diagnostics = {
            google_tag_manager: {
                enabled: enabled(analytics.google_tag_manager),
                id: gtmId || null,
                active: useGtm,
                status: useGtm ? "active" : "missing-or-invalid-id",
            },
            google_analytics: {
                enabled: enabled(analytics.google_analytics),
                id: gaId || null,
                active: useGa,
                status: useGa ? "active" : "missing-or-invalid-id",
            },
            google_ads_conversion: {
                id: adsId || null,
                active: useAds,
                status: useAds ? "active" : "missing-or-invalid-id",
            },
            google_search_console: {
                enabled: enabled(analytics.google_search_console),
                property: searchConsole || null,
                active: Boolean(searchConsole),
                status: searchConsole
                    ? "meta-rendered"
                    : "missing-or-placeholder",
            },
            hotjar: {
                id: hotjarId || null,
                active: useHotjar,
                status: useHotjar ? "active" : "missing-or-invalid-id",
            },
        };
    }, [
        analytics,
        adsId,
        gaId,
        gtmId,
        hotjarId,
        searchConsole,
        useAds,
        useGa,
        useGtm,
        useHotjar,
    ]);

    const gtmScript = useGtm
        ? `(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','${gtmId}');`
        : "";

    const gaScript = useGa
        ? `window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${gaId}');`
        : "";

    const adsScript = useAds
        ? `window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${adsId}');`
        : "";

    const hotjarScript = useHotjar
        ? `(function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};h._hjSettings={hjid:${hotjarId},hjsv:6};a=o.getElementsByTagName('head')[0];r=o.createElement('script');r.async=1;r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;a.appendChild(r);})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');`
        : "";

    return (
        <Head>
            {searchConsole && (
                <meta
                    head-key="google-site-verification"
                    name="google-site-verification"
                    content={searchConsole}
                />
            )}

            {useGtm && (
                <script
                    head-key="google-tag-manager"
                    dangerouslySetInnerHTML={{ __html: gtmScript }}
                />
            )}

            {useGa && (
                <script
                    head-key="google-analytics-loader"
                    async
                    src={`https://www.googletagmanager.com/gtag/js?id=${gaId}`}
                />
            )}
            {useGa && (
                <script
                    head-key="google-analytics"
                    dangerouslySetInnerHTML={{ __html: gaScript }}
                />
            )}

            {useAds && !useGa && (
                <script
                    head-key="google-ads-loader"
                    async
                    src={`https://www.googletagmanager.com/gtag/js?id=${adsId}`}
                />
            )}
            {useAds && (
                <script
                    head-key="google-ads-conversion"
                    dangerouslySetInnerHTML={{ __html: adsScript }}
                />
            )}

            {useHotjar && (
                <script
                    head-key="hotjar"
                    dangerouslySetInnerHTML={{ __html: hotjarScript }}
                />
            )}
        </Head>
    );
}
