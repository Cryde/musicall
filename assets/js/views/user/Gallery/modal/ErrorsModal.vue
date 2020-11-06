<template>
  <b-modal size="500" id="modal-errors" has-modal-card v-model="isOpen">
    <template #default="props">
      <div class="modal-card">
        <header class="modal-card-head">
          <p class="modal-card-title">Erreurs lors de la publication</p>
          <button type="button" class="delete" @click="$emit('close')"/>
        </header>

        <section class="modal-card-body">


          <b-message type="is-danger" has-icon>
            <p><strong>Il y a une ou plusieurs erreurs lors de la publication.</strong></p>
            <p><span class="is-block" v-for="error in errors">{{ error }}</span></p>
          </b-message>
        </section>

        <footer class="modal-card-foot">
          <b-button type="is-light" class="is-pulled-right" @click="props.close">Ok</b-button>
        </footer>
      </div>
    </template>
  </b-modal>
</template>

<script>
export default {
  props: ['errors'],
  data() {
    return {
      isOpen: false,
    }
  },
  methods: {
    open() {
      this.isOpen = true;
    }
  }
}
</script>