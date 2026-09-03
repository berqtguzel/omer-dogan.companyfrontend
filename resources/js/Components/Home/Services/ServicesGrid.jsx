import React, { memo } from "react";
import { useTranslation } from "react-i18next";
import { usePage } from "@inertiajs/react";
import ServiceCard from "./ServiceCard";
import SafeHtml from "@/Components/Common/SafeHtml";
import "../../../../css/ServicesGrid.css";

const ServicesGrid = () => {
    const { t } = useTranslation();
    const { props } = usePage();

    const content = props?.content || {};
    const globalCategories = props.global?.categories;

    const categories =
        Array.isArray(globalCategories) && globalCategories.length > 0
            ? globalCategories
            : props.categories || [];

    return (
        <section id="services" className="services-section">
            <div className="services-container">
                <h2 className="services-title">
                    <SafeHtml
                        html={content.services_title || t("servicesList.title")}
                        inline
                    />
                </h2>

                <div className="services-grid">
                    {categories.map((cat) => {
                        const name = cat.name || "Service";

                        return (
                            <ServiceCard
                                key={cat.id}
                                title={name}
                                image={cat.image}
                                slug={cat.slug}
                            />
                        );
                    })}

                    {!categories.length && (
                        <div className="no-services-message">
                            {t("servicesList.no_services")}
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
};

export default memo(ServicesGrid);
