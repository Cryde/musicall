<template>
  <div>
    <div v-if="errors.length" class="columns">
      <div class="column is-6-desktop is-offset-3-desktop">
        <b-message type="is-danger">
          <span v-for="error in errors" class="is-block">{{ error }}</span>
        </b-message>
      </div>
    </div>

    <div class="columns">
      <form method="post" class="column is-6-desktop col-12 is-offset-3-desktop">
        <h1 class="subtitle is-3 mb-5 ">Changement de mot de passe</h1>

        <b-message v-if="isSent" type="is-success">
          Votre mot de passe a été correctement changé, vous pouvez désormais vous connecter avec celui-ci.
        </b-message>
        <div v-else>

          <b-field label="Nouveau mot de passe">
            <b-input type="password" id="password" v-model="password"></b-input>
          </b-field>

          <b-field label="Confirmation du mot de passe">
            <b-input type="password" id="password-confirmation" v-model="passwordConfirmation"></b-input>
          </b-field>

          <b-button
              type="is-info"
              size="lg"
              class="mt-4 mb-4 is-fullwidth"
              :disabled="!isPasswordValid"
              :loading="isLoading"
              @click.prevent @click="sendNewPassword">
            envoyer
          </b-button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import userApi from "../../api/user";

export default {
  data() {
    return {
      hasError: false,
      errors: [],
      isSent: false,
      isLoading: false,
      token: null,
      password: '',
      passwordConfirmation: '',
    }
  },
  computed: {
    isPasswordValid() {
      return this.password.length > 0 && this.password === this.passwordConfirmation;
    }
  },
  mounted() {
    this.token = this.$route.params.token;
  },
  methods: {
    async sendNewPassword() {
      this.errors = [];
      this.isLoading = true;
      try {
        await userApi.resetPassword({token: this.token, password: this.password});
        this.isSent = true;
      } catch (e) {
        if(e.response.data.message) {
          this.errors = [e.response.data.message];
        } else {
          this.errors = e.response.data.violations.map(violation => violation.title);
        }
      }
      this.isLoading = false;
    }
  }
}
</script>