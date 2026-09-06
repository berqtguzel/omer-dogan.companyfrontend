export default function SectionHeader({ eyebrow, title, description, id }) {
    return <div className="group-section-heading">
        <p className="group-eyebrow">{eyebrow}</p>
        <h2 id={id} className="corporate-heading corporate-heading--section">{title}</h2>
        {description && <p className="group-section-heading__description">{description}</p>}
    </div>;
}
