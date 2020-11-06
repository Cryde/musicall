<template>

  <div class="card">
    <div class="card-content">
      <h3 class="subtitle is-4">Changer son mot de passe</h3>

      <div class="pt-4 is-clearfix">

        <b-message v-if="success" type="is-success">
          Mot de passe changé avec succès !
        </b-message>

        <b-message v-if="displayErrors" type="is-danger">
          <span v-for="error in errors">{{ error }}</span>
        </b-message>

        <b-field label="Ancien mot de passe">
          <b-input type="password" id="old-password" v-model="oldPassword"
                   @input="checkPasswordValidity"></b-input>
        </b-field>

        <b-field label="Nouveau mot de passe">
          <b-input type="password" password-reveal v-model="newPassword"
                   @input="checkPasswordValidity"></b-input>
        </b-field>

        <b-field label="Confirmation">
          <b-input type="password" password-reveal v-model="confirmationPassword"
                   @input="checkPasswordValidity"></b-input>

        </b-field>

        <b-button type="is-info" class="mt-3 is-pulled-right" :loading="isLoading"
                  :disabled="!confirmationPassword || !newPassword || !oldPassword || !isPasswordOk || isLoading"
                  @click="sendPassword"
        >
          Changer son mot de passe
        </b-button>
      </div>
    </div>
  </div>
</template>

<script>
import {debounce} from 'lodash';
import userApi from '../../../api/user';

export default {
  data() {
    return {
      success: false,
      isLoading: false,
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
      this.isLoading = true;
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
        if (e.response.data.violations) {
          this.errors.push(...e.response.data.violations.map(item => item.title));
        } else {
          this.errors.push(...e.response.data);
        }
      }
      this.isLoading = false;
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