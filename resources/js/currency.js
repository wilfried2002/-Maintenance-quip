// Devise choisie par l'organisation à sa création (voir Organisations/Index.vue et
// App\Models\Organisation::DEVISES côté backend) — un seul point de vérité pour le
// symbole, pour ne pas re-hardcoder "€"/"FCFA"/"$" à chaque endroit qui affiche un montant.
export const DEVISE_SYMBOLES = {
    XOF: 'FCFA',
    EUR: '€',
    USD: '$',
};

export const deviseOptions = [
    { value: 'XOF', label: 'Franc CFA (XOF)' },
    { value: 'EUR', label: 'Euro (EUR)' },
    { value: 'USD', label: 'Dollar (USD)' },
];

export function symboleDevise(devise) {
    return DEVISE_SYMBOLES[devise] ?? devise ?? '';
}

export function formatMontant(valeur, devise, options = {}) {
    const { decimales = 0 } = options;
    const nombre = Number(valeur ?? 0).toLocaleString('fr-FR', { maximumFractionDigits: decimales, minimumFractionDigits: decimales });
    return `${nombre} ${symboleDevise(devise)}`;
}
