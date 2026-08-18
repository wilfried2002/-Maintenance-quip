// Même mécanisme que scripts/scope-sb-admin.mjs (voir ce fichier pour l'explication complète
// du problème et des deux classes déclencheuses), appliqué au nouveau template Materio
// (Bootstrap 5) qui remplace progressivement SB Admin 2. Les deux coexistent pendant la
// migration page par page — d'où deux préfixes distincts (.materio-item/.materio-page),
// jamais les mêmes que .sb2-item/.sb2-page, pour ne jamais les faire interférer.
//
// Usage : node scripts/scope-materio.mjs

import fs from 'node:fs';
import postcss from 'postcss';
import prefixSelector from 'postcss-prefix-selector';

const SOURCES = [
    'public/vendor/materio/css/core.css',
    'public/vendor/materio/css/demo.css',
    'public/vendor/materio/css/node-waves.css',
    'public/vendor/materio/css/perfect-scrollbar.css',
    'public/vendor/materio/css/page-auth.css',
    'public/vendor/materio/css/page-misc.css',
];
const OUT = 'public/vendor/materio/css/materio.scoped.css';

const css = SOURCES.map((f) => fs.readFileSync(f, 'utf8')).join('\n');

const bareElementRe = /^(html|body|:root|\*|a|h[1-6]|p|abbr|blockquote|small|sub|sup|dfn|address|ol|ul|dl|dd|figure|hr|img|table|caption|th|td|label|button|input|select|optgroup|textarea|fieldset|legend|progress|summary|template|\[hidden\]|::selection|::-moz-selection|::before|::after|::placeholder|::-webkit-scrollbar.*)\b/i;
const safeGlobalRe = /^(#|\.container\b|\.container-fluid\b|\.row\b|\.col(-|\b))/;

function scopedRules(mode, prefix) {
    return postcss([
        prefixSelector({
            prefix,
            transform(p, selector) {
                const trimmed = selector.trim();
                if (bareElementRe.test(trimmed) || safeGlobalRe.test(trimmed)) {
                    return selector;
                }

                if (mode === 'descendant') {
                    return `${p} ${trimmed.replace(/^(html|body)\.?/, '')}`;
                }

                // Composé (sans espace), sur le premier segment seulement.
                return trimmed.replace(/^([^\s>+~]+)/, (firstSegment) => {
                    const withoutTag = firstSegment.replace(/^(html|body)/, '');
                    return `${p}${withoutTag}`;
                });
            },
        }),
    ]).process(css, { from: SOURCES[0], to: OUT }).css;
}

const combined = [
    '/* --- .materio-item : composé, opt-in élément par élément (coquille AuthenticatedLayout) --- */',
    scopedRules('compound', '.materio-item'),
    '/* --- .materio-page : descendant, page isolée déjà 100% convertie --- */',
    scopedRules('descendant', '.materio-page'),
].join('\n\n');

fs.writeFileSync(OUT, combined);
console.log(`Écrit ${OUT} (${(combined.length / 1024).toFixed(0)} Ko)`);
