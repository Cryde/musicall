<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Editer la publication mise en avant</p>
      <button type="button" class="delete" @click="$emit('close')"/>
    </header>

    <section class="modal-card-body">
      <b-message v-if="errors.length" type="is-danger" class="mt-3">
        <span v-for="error in errors" class="is-block">{{ error }}</span>
      </b-message>

      <div class="mt-2">
        <b-field
            label="Titre de la publication qui sera affiché sur la homepage"
            label-for="title">
          <b-input v-model="title" id="title" placeholder="Le titre qui sera affiché sur la homepage"></b-input>
        </b-field>
        <b-field
            label="Description (non-obligatoire)"
            label-for="description"
        >
          <b-input type="textarea" v-model="description" id="description"></b-input>
        </b-field>
      </div>
    </section>

    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')">Annuler</b-button>

      <b-button type="is-success" :loading="isSubmitted" :disabled="isSubmitted" icon-left="save" @click="save">
        Sauver
      </b-button>
    </footer>

    <b-loading :active="showOverlay"></b-loading>
  </div>
</template>

<script>
export default {
  props: ['featured'],
  data() {
    return {
      isSubmitted: false,
      title: '',
      description: '',
      showOverlay: false,
      errors: [],
    }
  },
  mounted() {
    this.title = this.featured.title;
    this.description = this.featured.description;
  },
  methods: {
    async save() {
      this.errors = [];
      this.isSubmitted = true;
      this.showOverlay = true;
      try {
        await this.$store.dispatch('adminFeatured/edit', {
          featuredId: this.featured.id,
          title: this.title,
          description: this.description
        });
        this.$emit('close');
      } catch (e) {
        this.errors = e.response.data.violations.map(item => item.title)
      }
      this.showOverlay = false;
      this.isSubmitted = false;
    },
  }
}
</script>