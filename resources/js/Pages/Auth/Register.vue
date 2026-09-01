<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    organisation_code: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Créer un compte" />

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
                            <h4 class="mb-1">Créer un compte</h4>
                            <p class="mb-5">
                                Votre compte sera rattaché à votre organisation et devra être activé
                                par un de ses administrateurs avant la première connexion.
                            </p>

                            <form @submit.prevent="submit">
                                <div class="form-floating form-floating-outline mb-5">
                                    <input
                                        id="organisation_code"
                                        v-model="form.organisation_code"
                                        type="text"
                                        :class="['form-control', form.errors.organisation_code ? 'is-invalid' : '']"
                                        placeholder="Code organisation"
                                        required
                                        autocomplete="off"
                                    />
                                    <label for="organisation_code">Code organisation (ex : DEMO01)</label>
                                    <div v-if="form.errors.organisation_code" class="invalid-feedback d-block">{{ form.errors.organisation_code }}</div>
                                </div>

                                <div class="form-floating form-floating-outline mb-5">
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        :class="['form-control', form.errors.name ? 'is-invalid' : '']"
                                        placeholder="Nom complet"
                                        required
                                        autocomplete="name"
                                        autofocus
                                    />
                                    <label for="name">Nom complet</label>
                                    <div v-if="form.errors.name" class="invalid-feedback d-block">{{ form.errors.name }}</div>
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

                                <div class="form-floating form-floating-outline mb-5">
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        :class="['form-control', form.errors.password ? 'is-invalid' : '']"
                                        placeholder="Mot de passe"
                                        required
                                        autocomplete="new-password"
                                    />
                                    <label for="password">Mot de passe</label>
                                    <div v-if="form.errors.password" class="invalid-feedback d-block">{{ form.errors.password }}</div>
                                </div>

                                <div class="form-floating form-floating-outline mb-5">
                                    <input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        :class="['form-control', form.errors.password_confirmation ? 'is-invalid' : '']"
                                        placeholder="Confirmer le mot de passe"
                                        required
                                        autocomplete="new-password"
                                    />
                                    <label for="password_confirmation">Confirmer le mot de passe</label>
                                    <div v-if="form.errors.password_confirmation" class="invalid-feedback d-block">{{ form.errors.password_confirmation }}</div>
                                </div>

                                <div class="mb-5">
                                    <button type="submit" class="btn btn-primary d-grid w-100" :disabled="form.processing">
                                        Créer mon compte
                                    </button>
                                </div>
                            </form>

                            <p class="text-center mb-0 small">
                                <span>Déjà inscrit ?</span>
                                <Link :href="route('login')" class="ms-1">Se connecter</Link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
