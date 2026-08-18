// Thème visuel : une seule couleur de marque désormais (celle du template Materio,
// #8c57ff — voir maintenance-equipement-materio-migration), plus une échelle de teintes
// exactement extraite de public/vendor/materio/css/core.css (--bs-primary*) via classes
// Tailwind arbitraires, pour un rendu identique au pixel près à Bootstrap/Materio.
// Toutes les clés historiques (une par module : orange/green/blue...) pointent vers le
// même objet — ça remplace l'ancienne identité "une couleur par module" par une couleur
// de marque unique et uniforme, sans toucher aux ~15 fichiers qui font themes[props.theme].
const materio = {
    header: 'bg-[#ede4ff] dark:bg-[#1c1133]/60 border-[#ddcdff] dark:border-[#543499]',
    accent: 'text-[#8c57ff] dark:text-[#ba9aff]',
    button: 'bg-[#8c57ff] hover:bg-[#7e4ee6] text-white focus:ring-[#8c57ff]',
    fileButton: 'file:bg-[#8c57ff] file:text-white file:hover:bg-[#7e4ee6]',
    badge: 'bg-[#ede4ff] text-[#382366] dark:bg-[#382366] dark:text-[#ba9aff]',
    ring: 'focus:ring-[#8c57ff] focus:border-[#8c57ff]',
    iconBg: 'bg-[#8c57ff]/10',
    icon: 'text-[#8c57ff] dark:text-[#ba9aff]',
    navActive: 'border-[#8c57ff] bg-[#ede4ff] text-[#382366] dark:bg-[#382366]/20 dark:text-[#ba9aff]',
};

export const themes = {
    orange: materio,
    green: materio,
    blue: materio,
    slate: materio,
    amber: materio,
    purple: materio,
    teal: materio,
};

export const statutOptions = [
    { value: 'en_service', label: 'En service' },
    { value: 'en_panne', label: 'En panne' },
    { value: 'en_maintenance', label: 'En maintenance' },
    { value: 'hors_service', label: 'Hors service' },
    { value: 'reforme', label: 'Réformé' },
];

export const typeInterventionOptions = [
    { value: 'preventive', label: 'Préventive' },
    { value: 'corrective', label: 'Corrective' },
    { value: 'predictive', label: 'Prédictive' },
];

export const statutInterventionOptions = [
    { value: 'planifiee', label: 'Planifiée' },
    { value: 'en_cours', label: 'En cours' },
    { value: 'terminee', label: 'Terminée' },
    { value: 'annulee', label: 'Annulée' },
];

export const prioriteOptions = [
    { value: 'basse', label: 'Basse' },
    { value: 'normale', label: 'Normale' },
    { value: 'haute', label: 'Haute' },
    { value: 'critique', label: 'Critique' },
];

export const criticiteOptions = [
    { value: 'basse', label: 'Basse' },
    { value: 'moyenne', label: 'Moyenne' },
    { value: 'haute', label: 'Haute' },
    { value: 'critique', label: 'Critique' },
];

// Classes littérales (voir remarque en tête de fichier) : indépendantes du thème du
// module, la criticité a toujours la même échelle de couleur partout.
export const criticiteBadgeClasses = {
    basse: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    moyenne: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    haute: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    critique: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};
