import React from "react";
import { createPortal } from "react-dom";
import { FaCheckCircle, FaTimes } from "react-icons/fa";
import "../../css/quote-modal.css";

const SuccessModal = ({
    isOpen,
    onClose,
    title,
    message,
    subMessage,
    buttonText,
}) => {
    if (!isOpen) return null;

    return createPortal(
        <div className="qdock" style={{ zIndex: 9999 }}>
            <button
                className="qdock__scrim"
                onClick={onClose}
                aria-label="Kapat"
            />

            <div
                className="qdock__dialog qdock-anim-in"
                role="dialog"
                aria-modal="true"
            >
                <div className="qdock__head">
                    <h2 className="qdock__title">{title}</h2>
                    <button
                        className="qdock__close"
                        onClick={onClose}
                        aria-label="Kapat"
                    >
                        <FaTimes />
                    </button>
                </div>

                <div className="qdock__ok">
                    <div className="qdock__ok-badge" aria-hidden>
                        <FaCheckCircle />
                    </div>

                    <h3 className="text-xl font-bold mt-4 mb-2">{title}</h3>

                    <p className="text-gray-600 dark:text-gray-300 mb-1">
                        {message}
                    </p>

                    {subMessage && (
                        <p className="text-sm text-gray-400">{subMessage}</p>
                    )}

                    <button
                        className="btn btn--primary mt-6 w-full"
                        onClick={onClose}
                    >
                        {buttonText}
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
};

export default SuccessModal;
