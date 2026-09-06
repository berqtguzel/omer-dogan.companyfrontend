export default function Container({ as: Tag = 'div', className = '', children, ...props }) {
    return <Tag className={`corporate-container ${className}`.trim()} {...props}>{children}</Tag>;
}
