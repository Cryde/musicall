<template>
  <div>
    <div v-if="hasError" class="columns">
      <div class="column is-6-desktop is-offset-3-desktop">
        <b-message type="is-danger">
          {{ error.message }}
        </b-message>
      </div>
    </div>

    <div class="columns">
      <form method="post" class="column is-6-desktop is-offset-3-desktop">
        <h1 class="subtitle is-3 mb-5 ">Mot de passe oublié</h1>

        <b-message type="is-success" v-if="isSent">
          Vous allez très rapidement recevoir un email pour réinitialiser votre mot de passe.
        </b-message>
        <div v-else>
          <b-input
              id="inputUsername"
              v-model="login"
              required autofocus
              size="lg"
              placeholder="nom d'utilisateur ou email"
          ></b-input>

          <b-button
              type="is-info"
              size="lg"
              class="mt-3 mb-4 is-fullwidth"
              :disabled="!login.length || isLoading"
              :loading="isLoading"
              @click.prevent @click="sendUserInfo">
            envoyer
          </b-button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import userApi from '../../api/user';

export default {
  data() {
    return {
      login: '',
      hasError: false,
      isLoading: false,
      isSent: false,
    }
  },
  methods: {
    async sendUserInfo() {
      this.hasError = false;
      this.isLoading = true;
      try {
        await userApi.requestResetPassword(this.login);
        this.isSent = true;
      } catch (e) {
        this.hasError = true;
        this.error = e.response.data;
      }
      this.isLoading = false;
    }
  }
}
</script>