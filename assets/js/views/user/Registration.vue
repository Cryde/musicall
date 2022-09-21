<template>
  <div v-if="!isSuccess">
    <div class="columns">
      <div class="column is-4-desktop is-offset-4-desktop">

        <b-message v-if="hasError" type="is-danger">
          <span class="is-block" v-for="error in errors">{{ error }}</span>
        </b-message>

        <form method="post" class="form-registration">
          <h1 class="subtitle is-3 mb-4 ">Inscription</h1>

          <b-field label="Nom d'utilisateur">
            <b-input
                id="inputUsername"
                v-model="username"
                required autofocus
                size="lg"
                placeholder="nom d'utilisateur"
            ></b-input>
          </b-field>

          <b-field label="Email">
            <b-input
                id="inputEmail"
                v-model="email"
                required autofocus
                size="lg"
                placeholder="email"
            ></b-input>
          </b-field>

          <b-field label="Mot de passe">
            <b-input
                id="inputPassword"
                password-reveal
                v-model="password"
                type="password"
                size="lg"
                required
                placeholder="mot de passe"
            ></b-input>
          </b-field>

          <b-button
              type="is-info"
              class="mt-3 is-fullwidth"
              :disabled="!canSubmit"
              :loading="isLoading"
              @click.prevent @click="register">
            m'inscrire
          </b-button>
        </form>
      </div>
    </div>
  </div>
  <div v-else>
    <div class="columns">
      <div class="column is-6-desktop is-offset-3-desktop pt-5 mt-5 columns">
        <div class="column is-2">
          <i class="fas fa-check fa-3x mb-3 has-text-success"></i>
        </div>
        <div class="column is-10">
          <strong>C'est presque fini ! </strong><br/>

          Il ne vous reste plus qu'à confirmer votre compte en cliquant sur le lien reçu par email.
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';

export default {
  data() {
    return {
      username: '',
      email: '',
      password: ''
    }
  },
  metaInfo() {
    return {
      title: 'Inscription - MusicAll',
    }
  },
  computed: {
    canSubmit() {
      return this.username.trim().length && this.email.trim().length && this.password.trim().length;
    },
    ...mapGetters('registration', [
      'isLoading',
      'isSuccess',
      'hasError',
      'errors'
    ])
  },
  methods: {
    async register() {
      await this.$store.dispatch('registration/register', {
        username: this.username,
        email: this.email,
        password: this.password
      });
    }
  }
}
</script>