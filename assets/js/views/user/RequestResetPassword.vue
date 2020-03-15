<template>
    <div>
        <div v-if="hasError" class="row">
            <div class="col-12 col-lg-6 offset-lg-3">
                <div class="alert alert-danger" role="alert">
                    {{ error.message }}
                </div>
            </div>
        </div>

        <div class="row">
            <form method="post" class="col-lg-6 col-12 offset-lg-3 form-signin">
                <h1 class="h3 mb-5 font-weight-normal">Mot de passe oublié</h1>

                <div v-if="isSent">
                    Vous allez très rapidement recevoir un email pour réinitialiser votre mot de passe.
                </div>
                <div v-else>
                    <b-form-input
                            id="inputUsername"
                            v-model="login"
                            required autofocus
                            size="lg"
                            placeholder="nom d'utilisateur ou email"
                    ></b-form-input>

                    <b-button
                            variant="primary" block
                            size="lg"
                            class="mt-3 mb-4"
                            :disabled="!login.length || isLoading"
                            type="submit" @click.prevent @click="sendUserInfo">
                        <b-spinner small type="grow" v-if="isLoading"></b-spinner>
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
          this.error = e;
        }
        this.isLoading = false;
      }
    }
  }
</script>