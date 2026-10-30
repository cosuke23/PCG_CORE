/**
 * Compiles the Tailwind utility classes used by the Blade views into
 * public/css/tailwind.css (committed), replacing the Tailwind Play CDN.
 *
 * Only needed when you add Tailwind classes that are not used anywhere yet:
 *   npm install && npm run build:css
 *
 * New pages should prefer public/css/pcg-ui.css (see CLAUDE.md).
 */
module.exports = {
    content: [
        './resources/views/**/*.blade.php',
        './public/js/**/*.js',
    ],
    theme: {
        extend: {
            // Previously defined inline in layouts/app.blade.php for the CDN
            colors: {
                pcgblue: '#0033A0',
                mepcomGold: '#FFD700',
            },
        },
    },
    plugins: [],
};
