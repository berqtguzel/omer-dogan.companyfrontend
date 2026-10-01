import { useTranslation } from "react-i18next";
import SiteLink from "@/Components/Navigation/SiteLink";

const images = [
    ["imgi_8_werrapark-heubacherhohe.png", "Werrapark Heubacherhöhe", "https://xn--werrapark-heubacher-hhe-slc.de/"],
    ["imgi_10_werrapark-sportcenter.png", "Werrapark Sportcenter", "https://werrapark-sportcenter.de/"],
    ["imgi_13_hamburgoi.png", "Ö&I Clean Group Hamburg", "https://oi-clean.de/"],
    ["imgi_14_hannoveroi.png", "Ö&I Clean Group Hannover", "https://www.oi-clean-teams.de/"],
    ["imgi_15_dhs-scaled.png", "DHS Deutsche Hotelreinigung Service GmbH", "https://www.dhs-gmbh.eu/"],
    ["imgi_17_dhi.png", "Deutsche Hotelbetreiber und Investment GmbH", "https://www.dhi-gmbh.eu/"],
].map(([file, title, href]) => ({
    image: `/images/sirket-resimleri/${file}`,
    title,
    link: { href, external: true, newTab: true },
}));

const websiteLink = (value) => {
    const link = typeof value === "string" ? { href: value } : value;
    if (!link?.href) return null;
    const external = /^https?:\/\//i.test(link.href);
    return {
        ...link,
        external: link.external ?? external,
        newTab: link.newTab ?? external,
    };
};

export default function CompanyImageWall({ companies = [] }) {
    const { i18n, t } = useTranslation();
    const apiItems = companies
        .filter((c) => c.logo || c.image)
        .map((c) => ({
            image: c.logo || c.image,
            title: c.name,
            link: websiteLink(c.website) || websiteLink(c.link),
        }));
    const items = apiItems.length
        ? apiItems
        : images.map((image) => {
              const company = companies.find(
                  (c) => c.name?.toLowerCase() === image.title.toLowerCase(),
              );
              return {
                  ...image,
                  link: websiteLink(company?.website) || image.link || websiteLink(company?.link),
              };
          });
    const language = i18n.resolvedLanguage?.split("-")[0];
    const labels =
        language === "tr"
            ? ["Şirket görselleri"]
            : language === "en"
              ? ["Company images"]
              : ["Unternehmensbilder"];
    return (
        <div
            className="company-image-wall"
            role="region"
            aria-label={labels[0]}
        >
            <div className="company-image-wall__motion">
                {items.map((item) => (
                    <SiteLink
                        key={item.image}
                        item={item.link || {}}
                        className="company-image-wall__logo"
                        aria-label={`${item.title} – ${t("corporateCatalog.website")}`}
                        newTabLabel={t("corporateCatalog.newTab")}
                    >
                        <img
                            src={item.image}
                            alt={item.title}
                            loading="lazy"
                            decoding="async"
                            width="520"
                            height="360"
                        />
                    </SiteLink>
                ))}
            </div>
        </div>
    );
}
