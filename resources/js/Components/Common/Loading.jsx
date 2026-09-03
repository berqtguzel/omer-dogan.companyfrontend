import React, { useEffect, useMemo, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import { renderText, renderUrl } from "@/utils/renderValue";
import "../../../css/loading.css";

export default function Loading({ style }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const [mounted, setMounted] = useState(false);
    const [active, setActive] = useState(false);

    const branding = props?.settings?.branding ?? {};
    const settings = props?.settings ?? {};
    const logo = useMemo(
        () =>
            [
                branding.site_logo_url,
                settings.site_logo_url,
                branding.logo_url,
                branding.site_dark_logo_url,
                settings.site_dark_logo_url,
                branding.dark_logo_url,
            ]
                .map((value) => renderUrl(value))
                .find(Boolean) || "",
        [branding, settings],
    );
    const siteName = renderText(
        settings?.general?.site_name,
        renderText(branding.site_name, "Website"),
    );
    const message = t("ui.loading.message") || "Yükleniyor...";

    useEffect(() => setMounted(true), []);

    useEffect(() => {
        if (!mounted) return undefined;

        let showTimer;
        let hideTimer;
        let isNavigating = false;

        const handleStart = () => {
            isNavigating = true;
            window.clearTimeout(showTimer);
            window.clearTimeout(hideTimer);
            showTimer = window.setTimeout(() => {
                if (isNavigating) setActive(true);
            }, 120);
        };

        const handleEnd = () => {
            isNavigating = false;
            window.clearTimeout(showTimer);
            hideTimer = window.setTimeout(() => setActive(false), 140);
        };

        const unsubscribeStart = router.on("start", handleStart);
        const unsubscribeFinish = router.on("finish", handleEnd);
        const unsubscribeError = router.on("error", handleEnd);

        return () => {
            window.clearTimeout(showTimer);
            window.clearTimeout(hideTimer);
            unsubscribeStart?.();
            unsubscribeFinish?.();
            unsubscribeError?.();
        };
    }, [mounted]);

    if (!mounted || !active) return null;

    return (
        <div
            className="oi-loading"
            style={style}
            role="status"
            aria-live="polite"
            aria-label={message}
        >
            <div className="oi-loading__surface">
                <div className="oi-loading__orbit" aria-hidden="true">
                    <span />
                    <span />
                    <span />
                </div>

                <div className="oi-loading__brand" aria-hidden="true">
                    {logo ? (
                        <img src={logo} alt="" className="oi-loading__logo" />
                    ) : (
                        <span className="oi-loading__monogram">
                            {siteName.charAt(0).toUpperCase()}
                        </span>
                    )}
                </div>

                <div className="oi-loading__copy">
                    <strong>{siteName}</strong>
                    <span>{message}</span>
                </div>

                <div className="oi-loading__track" aria-hidden="true">
                    <span />
                </div>
            </div>
        </div>
    );
}
