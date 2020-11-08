<template>
  <div>
    <h1 class="subtitle is-3">Contact</h1>

    <div v-if="isSent" class="mt-5 mb-5 has-text-centered">
      <i class="fas fa-check fa-5x has-text-success  mb-3"></i><br/>
      Votre message a été envoyé !<br/>
    </div>
    <div v-else>
      <b-message v-if="errors.length" type="is-danger">
        <span v-for="error in errors" class="is-block">{{ error }}</span>
      </b-message>

      <p>
        Une question ? Une suggestion ?<br/>
        Remplissez le formulaire ci-dessous.
      </p>

      <b-field label="Votre nom" class="mt-5">
        <b-input v-model="name"></b-input>
      </b-field>

      <b-field label="Votre email">
        <b-input v-model="email"></b-input>
      </b-field>

      <b-field label="Votre message">
        <b-input v-model="message" type="textarea"></b-input>
      </b-field>

      <b-button type="is-success" icon-left="paper-plane" :loading="isSending"
                :disabled="!canSend || isSending" @click="send">
        Envoyer
      </b-button>
    </div>
  </div>
</template>

<script>
import contactApi from "../../api/contact/contact";

export default {
  data() {
    return {
      name: '',
      email: '',
      message: '',
      isSending: false,
      isSent: false,
      errors: [],
    }
  },
  computed: {
    canSend() {
      return this.name.trim().length && this.email.trim().length && this.message.trim().length;
    }
  },
  methods: {
    async send() {
      this.isSending = true;
      try {
        await contactApi.send({name: this.name, message: this.message, email: this.email});
        this.isSent = true;
      } catch (e) {
        this.errors = e.response.data.violations.map(item => item.title);
      }
      this.isSending = false;
    }
  }
}
</script>