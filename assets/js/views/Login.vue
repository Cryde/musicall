<template>
  <div>
    <div class="columns">
      <div class="column is-12 is-4-desktop is-offset-4-desktop form-signin">
        <b-message show type="is-info">
          MusicAll a été mise à jour.<br/>
          Vous pouvez toujours utiliser votre précédent login/email.<br/>
          Vous allez devoir simplement
          <router-link :to="{name: 'user_request_lost_password'}" class="mt-3">reset votre password</router-link>
          la première fois
        </b-message>

        <b-message type="is-danger" v-if="hasError">
          {{ error.message }}
        </b-message>

        <form method="post">
          <h1 class="subtitle is-3 mb-3">Login</h1>
          <label for="inputUsername" class="sr-only">Username</label>
          <b-input
              id="inputUsername"
              v-model="username"
              required autofocus
              size="lg"
              placeholder="nom d'utilisateur"
              @keydown.native.enter="login"
          ></b-input>

          <label for="inputPassword" class="sr-only">Password</label>
          <b-input
              id="inputPassword"
              v-model="password"
              type="password"
              size="lg"
              required
              placeholder="mot de passe"
              @keydown.native.enter="login"
          ></b-input>

          <b-button
              type="is-info" block
              size="lg"
              class="is-fullwidth mt-3 mb-4"
              :disabled="!username.length || !password.length || isLoading"
              :loading="isLoading"
              @click.prevent @click="login">
            me connecter
          </b-button>

          <router-link :to="{name: 'user_request_lost_password'}" class="mt-3">Mot de passe oublié ?</router-link>
        </form>
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
      password: '',
    }
  },
  metaInfo() {
    return {
      title: 'Login - MusicAll',
    }
  },
  computed: {
    ...mapGetters('security', [
      'isLoading',
      'hasError',
      'error'
    ])
  },
  methods: {
    async login() {
      const username = this.username;
      const password = this.password;
      const redirect = this.$route.query.redirect;

      await this.$store.dispatch('security/login', {username, password});

      this.$nextTick(() => {
        if (!this.hasError) {
          if (typeof redirect !== "undefined") {
            this.$router.push({path: redirect});
          } else {
            this.$store.dispatch('user/refresh');
            this.$store.dispatch('notifications/loadNotifications');
            this.$router.push({name: "home"});
          }
        }
      })
    },
  },
  destroyed() {
    this.username = '';
    this.password = '';
    this.$store.dispatch('security/reset');
  }
}
</script>