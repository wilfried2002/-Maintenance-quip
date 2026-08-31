<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
// Message d'erreur flashé par le serveur (ex. session expirée -> CheckOrganisationAccess,
// inscription en attente d'activation) : affiché au-dessus du formulaire.
const flashError = () => page.props.flash?.error;

const form = useForm({
    email: '',
    password: '',
    organisation_code: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Connexion" />

    <div class="materio-page">
    <div class="position-relative">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner py-6 mx-4">
                <div class="card p-sm-7 p-2">
                    <div class="app-brand justify-content-center mt-5">
                        <span class="app-brand-logo demo text-primary">
                            <i class="icon-base ri ri-tools-fill icon-32px"></i>
                        </span>
                        <span class="app-brand-text demo text-heading fw-semibold ms-2">Maintenance Équip.</span>
                    </div>

                    <div class="card-body mt-1">
                        <h4 class="mb-1">Bon retour !</h4>
                        <p class="mb-5">Connectez-vous pour accéder à votre espace de maintenance.</p>

                        <div v-if="status" class="alert alert-success small">{{ status }}</div>
                        <div v-if="flashError()" class="alert alert-danger small">{{ flashError() }}</div>

                        <form @submit.prevent="submit">
                            <div class="form-floating form-floating-outline mb-5">
                                <input
                                    id="organisation_code"
                                    v-model="form.organisation_code"
                                    type="text"
                                    :class="['form-control', form.errors.organisation_code ? 'is-invalid' : '']"
                                    placeholder="Code organisation"
                                    autofocus
                                    autocomplete="off"
                                />
                                <label for="organisation_code">Code organisation (ex : DEMO01)</label>
                                <div v-if="form.errors.organisation_code" class="invalid-feedback d-block">{{ form.errors.organisation_code }}</div>
                            </div>
                            <div class="form-floating form-floating-outline mb-5">
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    :class="['form-control', form.errors.email ? 'is-invalid' : '']"
                                    placeholder="Adresse email"
                                    required
                                    autocomplete="username"
                                />
                                <label for="email">Adresse email</label>
                                <div v-if="form.errors.email" class="invalid-feedback d-block">{{ form.errors.email }}</div>
                            </div>
                            <div class="mb-5">
                                <div class="form-floating form-floating-outline">
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        :class="['form-control', form.errors.password ? 'is-invalid' : '']"
                                        placeholder="Mot de passe"
                                        required
                                        autocomplete="current-password"
                                    />
                                    <label for="password">Mot de passe</label>
                                    <div v-if="form.errors.password" class="invalid-feedback d-block">{{ form.errors.password }}</div>
                                </div>
                            </div>
                            <div class="mb-5 pb-2 d-flex justify-content-between align-items-center">
                                <div class="form-check mb-0">
                                    <input id="remember-me" v-model="form.remember" class="form-check-input" type="checkbox" />
                                    <label class="form-check-label" for="remember-me">Se souvenir de moi</label>
                                </div>
                                <Link v-if="canResetPassword" :href="route('password.request')" class="small">Mot de passe oublié ?</Link>
                            </div>
                            <div class="mb-5">
                                <button type="submit" class="btn btn-primary d-grid w-100" :disabled="form.processing">
                                    Se connecter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</template>
