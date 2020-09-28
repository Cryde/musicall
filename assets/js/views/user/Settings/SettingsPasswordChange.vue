<template>
        <b-card title="Changer son mot de passe">
            <b-form @submit.stop.prevent class="pt-4">

                <b-alert v-model="success" variant="success">
                    Mot de passe changé avec succès !
                </b-alert>

                <b-alert v-model="displayErrors" variant="danger">
                    <span v-for="error in errors">{{ error }}</span>
                </b-alert>

                <label for="old-password">Ancien mot de passe</label>
                <b-input type="password" id="old-password" v-model="oldPassword"
                         @input="checkPasswordValidity"></b-input>

                <label for="new-password">Nouveau mot de passe</label>
                <b-input type="password" id="new-password" v-model="newPassword"
                         @input="checkPasswordValidity"></b-input>

                <label for="confirma-password">Confirmation</label>
                <b-input type="password" id="confirma-password" v-model="confirmationPassword"
                         @input="checkPasswordValidity"></b-input>

                <b-button variant="primary" class="mt-3 float-right"
                          :disabled="!confirmationPassword || !newPassword || !oldPassword || !isPasswordOk"
                          @click="sendPassword"
                >
                    Changer son mot de passe
                </b-button>
            </b-form>

        </b-card>
</template>

<script>
  import {debounce} from 'lodash';
  import userApi from '../../../api/user';

  export default {
    data() {
      return {
        success: false,
        errors: [],
        oldPassword: '',
        newPassword: '',
        confirmationPassword: ''
      }
    },
    computed: {
      displayErrors() {
        return this.errors.length > 0;
      },
      isPasswordOk() {
        return this.isNewPasswordAndConfirmationEqual() && !this.isOldAndNewPasswordEquals();
      }
    },
    methods: {
      async sendPassword() {
        try {
          await userApi.changePassword({
            oldPassword: this.oldPassword,
            newPassword: this.newPassword
          });
          this.success = true;
          this.oldPassword = '';
          this.newPassword = '';
          this.confirmationPassword = '';
        } catch (e) {
          this.errors.push(...e);
        }
      },
      checkPasswordValidity: debounce(function () {
        this.success = false;
        if (!this.oldPassword.length || !this.newPassword.length || !this.confirmationPassword.length) {
          return;
        }
        this.errors = [];
        if (!this.isNewPasswordAndConfirmationEqual()) {
          this.errors.push('Le nouveau mot de passe et sa confirmation ne sont pas identique');
          return;
        }

        if (this.isOldAndNewPasswordEquals()) {
          this.errors.push('Votre nouveau mot de passe est identique à l\'ancien');
        }
      }, 500),
      isNewPasswordAndConfirmationEqual() {
        return this.newPassword === this.confirmationPassword;
      },
      isOldAndNewPasswordEquals() {
        return this.oldPassword === this.newPassword;
      }
    }
  }
</script>