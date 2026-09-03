import React, { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import "../../css/OfferDock.css";

const PANEL_W = 280;
const TAB_W = 40;
const HIDE_X = -(PANEL_W - TAB_W);

export default function OfferDock() {
    const { t } = useTranslation();

    const [collapsed, setCollapsed] = useState(false);
    const [isMounted, setIsMounted] = useState(false);

    useEffect(() => {
        setIsMounted(true);
        try {
            const v = localStorage.getItem("offerDockCollapsed");

            if (v === "1") {
                setCollapsed(true);
            }

            if (!sessionStorage.getItem("odockOpenedOnce")) {
                setCollapsed(false);
                localStorage.setItem("offerDockCollapsed", "0");
                sessionStorage.setItem("odockOpenedOnce", "1");
            }
        } catch {}
    }, []);

    useEffect(() => {
        if (!isMounted) return;
        try {
            localStorage.setItem("offerDockCollapsed", collapsed ? "1" : "0");
        } catch {}
    }, [collapsed, isMounted]);

    const openQuoteModal = () => {
        window.dispatchEvent(new Event("open-quote-modal"));
    };
    const cssVars = {
        "--panel-w": `${PANEL_W}px`,
        "--tab-w": `${TAB_W}px`,

        "--panel-x": isMounted && collapsed ? `${HIDE_X}px` : "0px",
    };

    const title = t("offerDock.title", "Teklif");
    const subtitle = t("offerDock.subtitle", "Ücretsiz & bağlayıcı değil");
    const buttonLabel = t("offerDock.button", "Talep et");

    const ariaOpen = t("offerDock.aria_open", "Teklif panelini aç");
    const ariaClose = t("offerDock.aria_close", "Teklif panelini kapat");

    const svgPath = collapsed ? "M9 6l6 6-6 6" : "M15 6l-6 6 6 6";

    if (!isMounted) {
        return (
            <div
                className={`odock`}
                style={{
                    "--panel-w": `${PANEL_W}px`,
                    "--tab-w": `${TAB_W}px`,
                    "--panel-x": "0px",
                }}
                suppressHydrationWarning={true}
            >
                <div className="odock__panel">
                    <div className="odock__body">
                        <div className="odock__group">
                            <div
                                className="odock__title"
                                suppressHydrationWarning={true}
                            >
                                {title}
                            </div>
                            <div
                                className="odock__sub"
                                suppressHydrationWarning={true}
                            >
                                {subtitle}
                            </div>
                        </div>

                        <button
                            type="button"
                            className="odock__cta bg-button anfordern-modal-open-button"
                            data-track-key="home_fixed_anfordern"
                            data-track-name="Anfordern – Formular öffnen"
                            data-track-location="left_fixed_button"
                            data-track-action="open_contact_form"
                            data-track-form="anfordern_form"
                            disabled={true}
                            suppressHydrationWarning={true}
                        >
                            {buttonLabel}
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    className="odock__tab bg-button"
                    aria-label={ariaClose}
                    disabled={true}
                    suppressHydrationWarning={true}
                >
                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M15 6l-6 6 6 6"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                </button>
            </div>
        );
    }

    return (
        <div
            className={`odock ${collapsed ? "odock--collapsed" : ""}`}
            style={cssVars}
            suppressHydrationWarning={true}
        >
            <div className="odock__panel">
                <div className="odock__body">
                    <div className="odock__group">
                        <div
                            className="odock__title"
                            suppressHydrationWarning={true}
                        >
                            {title}
                        </div>
                        <div
                            className="odock__sub"
                            suppressHydrationWarning={true}
                        >
                            {subtitle}
                        </div>
                    </div>

                    <button
                        type="button"
                        className="odock__cta bg-button anfordern-modal-open-button"
                        data-track-key="home_fixed_anfordern"
                        data-track-name="Anfordern – Formular öffnen"
                        data-track-location="left_fixed_button"
                        data-track-action="open_contact_form"
                        data-track-form="anfordern_form"
                        onClick={openQuoteModal}
                        suppressHydrationWarning={true}
                    >
                        {buttonLabel}
                    </button>
                </div>
            </div>

            <button
                type="button"
                className="odock__tab bg-button"
                aria-label={collapsed ? ariaOpen : ariaClose}
                onClick={() => setCollapsed((s) => !s)}
                suppressHydrationWarning={true}
            >
                <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        d={svgPath}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </button>
        </div>
    );
}
