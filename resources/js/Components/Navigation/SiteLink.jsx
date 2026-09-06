import { Link } from '@inertiajs/react';

export default function SiteLink({ item, children, className, onClick, newTabLabel, ...props }) {
    if (!item.href) return <span className={className}>{children || item.label}</span>;
    const native = item.external || item.newTab || /^(?:#|mailto:|tel:)/i.test(item.href) || item.href.includes('#');
    const Tag = native ? 'a' : Link;
    return (
        <Tag href={item.href} className={className} onClick={onClick}
            target={item.newTab ? '_blank' : undefined}
            rel={item.newTab ? 'noopener noreferrer' : undefined} {...props}>
            {children || item.label}
            {item.external && <span className="corporate-external" aria-hidden="true">↗</span>}
            {item.newTab && newTabLabel && <span className="corporate-visually-hidden"> ({newTabLabel})</span>}
        </Tag>
    );
}
