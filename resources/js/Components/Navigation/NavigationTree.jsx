import { usePage } from '@inertiajs/react';
import SiteLink from './SiteLink';

const path = (value) => value.split(/[?#]/)[0].replace(/\/+$/, '') || '/';
const isCurrent = (item, url) => !item.external && item.href && !item.href.includes('#') && path(item.href) === path(url);
const containsCurrent = (item, url) => isCurrent(item, url) || item.children.some(child => containsCurrent(child, url));

export default function NavigationTree({ items, labels, onNavigate, nested = false }) {
    const { url } = usePage();
    return (
        <ul className={nested ? 'corporate-nav__children' : 'corporate-nav__list'}>
            {items.map((item, index) => {
                const active = isCurrent(item, url);
                const link = <SiteLink item={item} className="corporate-nav__link" onClick={onNavigate}
                    aria-current={active ? 'page' : undefined} newTabLabel={labels.newTab} />;
                return <li key={`${item.id}-${index}`} className="corporate-nav__item">
                    {item.children.length ? <details className="corporate-nav__branch">
                        <summary className="corporate-nav__summary" data-active={containsCurrent(item, url) || undefined}>
                            <span>{item.label}</span><span className="corporate-nav__chevron" aria-hidden="true">⌄</span>
                        </summary>
                        <div className="corporate-nav__panel">
                            {item.href && link}
                            <NavigationTree items={item.children} labels={labels} onNavigate={onNavigate} nested />
                        </div>
                    </details> : link}
                </li>;
            })}
        </ul>
    );
}
