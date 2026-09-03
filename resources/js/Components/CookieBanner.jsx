import React, { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import Cookies from "js-cookie";
import { FaCookieBite, FaCheck, FaTimes } from "react-icons/fa";
import "../../css/CookieBanner.css";

const INITIAL_COOKIES = {
    necessary: { required: true },
    analytics: { required: false },
    marketing: { required: false },
};

const CookieBanner = ({ forceVisible = false, onClose }) => {
    const { t } = useTranslation();

    const [isVisible, setIsVisible] = useState(false);
    const [showDetails, setShowDetails] = useState(false);

    const [preferences, setPreferences] = useState({
        necessary: true,
        analytics: false,
        marketing: false,
    });

    const [hasConsent, setHasConsent] = useState(() =>
        !!Cookies.get("cookie_consent")
    );

    /** --- INITIAL LOAD LOGIC --- */
    useEffect(() => {
        const consent = Cookies.get("cookie_consent");

        if (consent && !forceVisible) {
            setIsVisible(false);
            setHasConsent(true);
            try {
                setPreferences(JSON.parse(consent));
            } catch {}
            return;
        }

        setIsVisible(true);
    }, [forceVisible]);

    /** --- SAVE FUNCTION --- */
    const saveConsent = (prefs) => {
        Cookies.set("cookie_consent", JSON.stringify(prefs), {
            expires: 365,
            path: "/",
            sameSite: "Lax",
        });

        setPreferences(prefs);
        setHasConsent(true);
        setIsVisible(false);
        setShowDetails(false);

        if (onClose) onClose(); // 🔥 CRITICAL FIX

        window.dispatchEvent(new Event("cookie-saved"));
    };

    const handleAcceptAll = () =>
        saveConsent({ necessary: true, analytics: true, marketing: true });

    const handleRejectAll = () =>
        saveConsent({ necessary: true, analytics: false, marketing: false });

    const handleSaveSelection = () => saveConsent(preferences);

    const togglePreference = (key) => {
        if (INITIAL_COOKIES[key].required) return;
        setPreferences((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    /** --- FLOATING BUTTON --- */
    if (!forceVisible && !isVisible && hasConsent) {
        return (
            <button
                type="button"
                className="cookie-floating-btn"
                onClick={() => {
                    setIsVisible(true);
                    setShowDetails(true);
                }}
                aria-label={t("cookies.open_preferences")}
            >
                <FaCookieBite />
            </button>
        );
    }

    /** --- PREVENT FLASH AT FIRST LOAD --- */
    if (!isVisible && !forceVisible && !hasConsent) {
        return null;
    }

    return (
        <div className="cookie-banner-container">
            <div className="cookie-card">
                <div className="cookie-header">
                    <div className="cookie-icon-box">
                        <FaCookieBite />
                    </div>
                    <div className="cookie-title-area">
                        <h3>{t("cookies.title")}</h3>
                        <p className="cookie-text">{t("cookies.message")}</p>
                    </div>
                </div>

                {showDetails && (
                    <div className="cookie-details">
                        {Object.keys(INITIAL_COOKIES).map((key) => (
                            <div key={key} className="cookie-option">
                                <label
                                    className="cookie-option-label"
                                    htmlFor={`cookie-${key}`}
                                >
                                    <span className="cookie-opt-name">
                                        {t(`cookies.cat_${key}`)}
                                    </span>
                                </label>

                                <div className="cookie-switch">
                                    <input
                                        type="checkbox"
                                        id={`cookie-${key}`}
                                        checked={preferences[key]}
                                        disabled={INITIAL_COOKIES[key].required}
                                        onChange={() => togglePreference(key)}
                                    />
                                    <span className="cookie-slider" />
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <div className="cookie-actions">
                    <div className="cookie-btn-group">
                        {showDetails ? (
                            <button
                                type="button"
                                className="cookie-btn btn-secondary"
                                onClick={handleSaveSelection}
                            >
                                <FaCheck size={12} /> {t("cookies.save")}
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="cookie-btn btn-secondary"
                                onClick={handleRejectAll}
                            >
                                <FaTimes size={12} /> {t("cookies.reject")}
                            </button>
                        )}

                        <button
                            type="button"
                            className="cookie-btn btn-accept"
                            onClick={handleAcceptAll}
                        >
                            {t("cookies.accept_all")}
                        </button>
                    </div>

                    <button
                        type="button"
                        className="btn-ghost"
                        onClick={() => setShowDetails((v) => !v)}
                    >
                        {showDetails
                            ? t("cookies.hide_details")
                            : t("cookies.settings")}
                    </button>
                </div>
            </div>
        </div>
    );
};

export default CookieBanner;
