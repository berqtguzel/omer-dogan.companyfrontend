// Only this boundary knows panel color field names. Missing values leave the
// corporate token defaults intact instead of inheriting legacy theme defaults.
const panelTokens = {
    site_primary_color: '--brand-primary',
    site_secondary_color: '--brand-secondary',
    site_accent_color: '--brand-accent',
};

export function corporateTheme(colors = {}) {
    if (!colors || typeof colors !== 'object') return {};

    return Object.fromEntries(
        Object.entries(panelTokens)
            .filter(([key]) => typeof colors[key] === 'string' && colors[key].trim())
            .map(([key, token]) => [token, colors[key].trim()]),
    );
}
