<template>
    <div>
        <div v-if="errors.length" class="row">
            <div class="col-12 col-lg-6 offset-lg-3">
                <div class="alert alert-danger" role="alert">
                    <span v-for="error in errors" class="d-block">{{ error }}</span>
                </div>
            </div>
        </div>

        <div class="row">
            <form method="post" class="col-lg-6 col-12 offset-lg-3 form-signin">
                <h1 class="h3 mb-5 font-weight-normal">Changement de mot de passe</h1>

                <div v-if="isSent">
                    Votre mot de passe a été correctement changé, vous pouvez désormais vous connecter avec celui-ci.
                </div>
                <div v-else>

                    <label for="password">Nouveau mot de passe</label>
                    <b-input type="password" id="password" v-model="password"></b-input>

                    <label for="password-confirmation" class="mt-3">Confirmation du mot de passe</label>
                    <b-input type="password" id="password-confirmation" v-model="passwordConfirmation"></b-input>

                    <b-button
                            variant="primary" block
                            size="lg"
                            class="mt-3 mb-4"
                            :disabled="!isPasswordValid"
                            type="submit" @click.prevent @click="sendNewPassword">
                        <b-spinner small type="grow" v-if="isLoading"></b-spinner>
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
          if (e.message !== undefined) {
            this.errors = [e.message];
          } else {
            this.errors = e.violations.map(violation => violation.title);
          }
        }
        this.isLoading = false;
      }
    }
  }
</script>