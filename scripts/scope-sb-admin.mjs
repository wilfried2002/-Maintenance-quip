// Ponctuel : produit une copie de sb-admin-2.css dont les classes Bootstrap qui portent
// EXACTEMENT le même nom que des utilitaires Tailwind (p-4, m-4, rounded, shadow, border,
// text-center, d-flex, w-100...) sont scopées, pour ne jamais écraser silencieusement les
// classes Tailwind d'un composant Vue pas encore converti au template (ces classes
// Bootstrap sont déclarées !important — vérifié empiriquement : .px-4 rendait 24px, valeur
// Bootstrap, au lieu de 16px, valeur Tailwind, avant scoping).
//
// DEUX classes déclencheuses distinctes, jamais la même — c'est le point important :
//   .sb2-item  -> variante COMPOSÉE (.sb2-item.maclasse, sans espace). L'élément doit
//                 lui-même porter .sb2-item ; ça n'affecte aucun descendant. Utilisée sur
//                 chaque élément de la coquille (AuthenticatedLayout : sidebar, topbar,
//                 footer...) qui contient structurellement le <slot/> d'une page pas
//                 encore convertie (imposé par la mise en page SB Admin 2 :
//                 #wrapper > #content-wrapper > ... > <slot/>).
//   .sb2-page  -> variante DESCENDANTE (.sb2-page .maclasse). Toute la sous-arborescence
//                 est couverte. Réservée à une page entièrement isolée et déjà 100%
//                 convertie (ex. Login.vue), qui ne contient AUCUN contenu Tailwind non
//                 converti en dessous.
// Avoir deux classes séparées (plutôt qu'une seule portant les deux jeux de règles) évite
// qu'un élément marqué .sb2-item pour un usage composé n'active AUSSI, par accident, les
// règles descendantes et ne fuie vers le <slot/> qu'il contient — bug réel rencontré et
// corrigé pendant l'intégration (cf. mémoire du projet).
//
// Restent volontairement globaux (jamais préfixés) : le reboot d'éléments bruts (body,
// html, a, h1-h6, p...), les sélecteurs #id (jamais en collision avec une classe), et la
// grille Bootstrap (.container, .container-fluid, .row, .col-*) qui n'a pas d'équivalent
// de même nom côté Tailwind.
//
// Usage : node scripts/scope-sb-admin.mjs

import fs from 'node:fs';
import postcss from 'postcss';
import prefixSelector from 'postcss-prefix-selector';

const SRC = 'public/vendor/sb-admin-2/sb-admin-2.css';
const OUT = 'public/vendor/sb-admin-2/sb-admin-2.scoped.css';

const css = fs.readFileSync(SRC, 'utf8');

const bareElementRe = /^(html|body|:root|\*|a|h[1-6]|p|abbr|blockquote|small|sub|sup|dfn|address|ol|ul|dl|dd|figure|hr|img|table|caption|th|td|label|button|input|select|optgroup|textarea|fieldset|legend|progress|summary|template|\[hidden\]|::selection|::-moz-selection|::before|::after)\b/i;
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
    ]).process(css, { from: SRC, to: OUT }).css;
}

const combined = [
    '/* --- .sb2-item : composé, opt-in élément par élément (coquille AuthenticatedLayout) --- */',
    scopedRules('compound', '.sb2-item'),
    '/* --- .sb2-page : descendant, page isolée déjà 100% convertie (ex. Login.vue) --- */',
    scopedRules('descendant', '.sb2-page'),
].join('\n\n');

fs.writeFileSync(OUT, combined);
console.log(`Écrit ${OUT} (${(combined.length / 1024).toFixed(0)} Ko)`);
