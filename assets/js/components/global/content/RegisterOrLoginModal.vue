<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Connectez vous</p>
      <button
          type="button"
          class="delete"
          @click="$emit('close')"/>
    </header>
    <section class="modal-card-body">
      <b-message type="is-info" has-icon><span v-html="message"></span></b-message>
    </section>

    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')">Fermer</b-button>

      <b-button type="is-info" class="ml-4" @click="go('user_registration')">
        <strong>S'inscrire</strong>
      </b-button>
      <b-button type="is-success" @click="go('login')">
        Se connecter
      </b-button>
    </footer>
  </div>
</template>
<script>
export default {
  props: {
    message: {
      type: String,
      required: true,
    },
  },
  methods: {
    go(name) {
      this.$router.push({name});
      this.$emit('close');
    }
  },
}
</script>
