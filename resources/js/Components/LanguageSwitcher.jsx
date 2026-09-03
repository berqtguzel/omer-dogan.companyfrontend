import React, { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { router } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import Cookies from "js-cookie";
import { FiCheck, FiX } from "react-icons/fi";
import Loading from "./Common/Loading";
import { buildLocalizedPath } from "@/utils/localizedPath";
import "../../css/language-switcher.css";

function normalizeLang(code) {
    return String(code || "")
        .toLowerCase()
        .split("-")[0];
}

// 🔥 flag fix map
const flagMap = {
    en: "gb",
    cs: "cz",
};

const LanguageSwitcher = ({ currentLang, languages }) => {
    const { i18n, t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    const normalizedCurrent = normalizeLang(currentLang);

    // ✅ ESC ile kapatma
    useEffect(() => {
        const handleKey = (e) => {
            if (e.key === "Escape") setOpen(false);
        };

        if (open) {
            window.addEventListener("keydown", handleKey);
        }

        return () => {
            window.removeEventListener("keydown", handleKey);
        };
    }, [open]);

    useEffect(() => {
        if (!open || typeof document === "undefined") return undefined;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [open]);

    const handleLanguageChange = async (codeNorm) => {
        if (codeNorm === normalizedCurrent) return;

        setIsLoading(true);

        const newUrl = `${buildLocalizedPath(window.location.pathname, codeNorm)}${window.location.search}${window.location.hash}`;

        Cookies.set("locale", codeNorm, { path: "/", expires: 365 });

        try {
            await i18n.changeLanguage(codeNorm);
        } catch (e) {}

        setTimeout(() => {
            router.visit(newUrl, {
                replace: true,
                preserveState: false,
                preserveScroll: false,
            });
        }, 150);
    };

    if (!languages || languages.length <= 1) return null;

    const activeLang =
        languages.find((l) => normalizeLang(l.code) === normalizedCurrent) ||
        languages[0];

    return (
        <>
            {/* BUTTON */}
            <button
                type="button"
                className="lang-switch__btn"
                onClick={() => setOpen(true)}
                aria-label="Sprache auswählen"
                title="Sprache auswählen"
            >
                <span className="lang-switch__btn-flag">
                    <img
                        src={`https://flagcdn.com/w40/${
                            flagMap[normalizeLang(activeLang.code)] ||
                            normalizeLang(activeLang.code)
                        }.png`}
                        alt=""
                        width={40}
                        height={30}
                        loading="lazy"
                        decoding="async"
                        aria-hidden="true"
                        onError={(e) => {
                            e.target.src = "https://flagcdn.com/w40/un.png";
                        }}
                    />
                </span>
                <span className="lang-switch__btn-code">
                    {normalizeLang(activeLang.code).toUpperCase()}
                </span>
            </button>

            {/* MODAL */}
            {open && typeof document !== "undefined" && createPortal(
                <div className="lang-modal">
                    {/* backdrop */}
                    <div
                        className="lang-modal__backdrop"
                        onClick={() => setOpen(false)}
                    />

                    {/* content */}
                    <div
                        className="lang-modal__content"
                        onClick={(e) => e.stopPropagation()}
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="language-dialog-title"
                    >
                        <div className="lang-modal__header">
                            <div>
                                <h2
                                    id="language-dialog-title"
                                    className="lang-modal__title"
                                >
                                    {t("header.select_language", "Sprache auswählen")}
                                </h2>
                                <span className="lang-modal__subtitle">
                                    {languages.length}{" "}
                                    {t("header.languages", "Sprachen")}
                                </span>
                            </div>
                            <button
                                type="button"
                                className="lang-modal__close-btn"
                                onClick={() => setOpen(false)}
                                aria-label="Sprachauswahl schließen"
                                title="Sprachauswahl schließen"
                            >
                                <FiX
                                    size={20}
                                    aria-hidden="true"
                                    focusable="false"
                                />
                            </button>
                        </div>

                        {/* LOADING */}
                        {isLoading && (
                            <div className="lang-modal__loading">
                                <Loading />
                            </div>
                        )}

                        {/* LIST */}
                        <div
                            className={`lang-modal__list ${
                                isLoading ? "disabled" : ""
                            }`}
                        >
                            {languages.map((l) => {
                                const codeNorm = normalizeLang(l.code);
                                const isActive = codeNorm === normalizedCurrent;

                                const flagCode = flagMap[codeNorm] || codeNorm;
                                const label = l.label || codeNorm.toUpperCase();

                                return (
                                    <button
                                        key={l.code}
                                        className={`lang-modal__item ${
                                            isActive ? "is-active" : ""
                                        }`}
                                        onClick={() =>
                                            handleLanguageChange(codeNorm)
                                        }
                                        disabled={isLoading}
                                    >
                                        {/* FLAG */}
                                        <div className="lang-modal__flag">
                                            <img
                                                src={`https://flagcdn.com/w40/${flagCode}.png`}
                                                alt={`${label} flag`}
                                                width={40}
                                                height={30}
                                                loading="lazy"
                                                decoding="async"
                                                onError={(e) => {
                                                    e.target.src =
                                                        "https://flagcdn.com/w40/un.png";
                                                }}
                                            />
                                        </div>

                                        {/* TEXT */}
                                        <div className="lang-modal__info">
                                            <span className="lang-modal__name">
                                                {label}
                                            </span>
                                            <span className="lang-modal__code">
                                                {codeNorm}
                                            </span>
                                        </div>

                                        {/* ACTIVE */}
                                        {isActive && (
                                            <span className="lang-modal__check">
                                                <FiCheck
                                                    size={13}
                                                    strokeWidth={3}
                                                    aria-hidden="true"
                                                    focusable="false"
                                                />
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </>
    );
};

export default LanguageSwitcher;
