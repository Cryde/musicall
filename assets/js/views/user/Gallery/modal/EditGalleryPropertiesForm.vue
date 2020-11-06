<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Paramètres de la galerie</p>
      <button
          type="button"
          class="delete"
          @click="$emit('close')"/>
    </header>

    <section class="modal-card-body">

      <b-field label="Le titre de votre galerie">
        <b-input v-model="currentTitle"
                 placeholder="Votre titre ici"></b-input>
      </b-field>

      <b-field label="Description de votre galerie">
        <b-input type="textarea" v-model="currentDescription"
                 placeholder=""></b-input>
      </b-field>

      <div v-if="coverImage">
        Image de couverture de la galerie.
        <b-image :src="coverImage.sizes.medium" class="mt-2"/>
      </div>
      <div v-else>
        Vous n'avez pas encore défini d'image de couverture.<br/>

        <span v-if="images.length">Vous pouvez le faire en cliquant sur l'icone <span class="btn btn-primary"><i
            class="fas fa-image"></i></span> depuis les images envoyés ci dessous.</span>
        <span v-else>Vous devez uploader des images pour pouvoir definir une image de couverture</span>
      </div>
    </section>

    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')">Annuler</b-button>
      <b-button type="is-success"
                :loading="submitted"
                icon-left="save"
                :disabled="submitted" @click="save">
        Enregistrer
      </b-button>
    </footer>
  </div>
</template>

<script>
import {mapGetters} from "vuex";

export default {
  data() {
    return {
      currentTitle: '',
      currentDescription: '',
      submitted: false,
    }
  },
  computed: {
    ...mapGetters('userGallery', [
      'gallery',
      'coverImage',
      'images'
    ])
  },
  mounted() {
    this.currentTitle = this.gallery.title;
    this.currentDescription = this.gallery.description;
  },
  methods: {
    async save() {
      this.submitted = true;
      await this.$store.dispatch('userGallery/edit', {
        title: this.currentTitle,
        description: this.currentDescription,
        id: this.gallery.id
      });
      this.submitted = false;
      this.$emit('close')
    }
  }
};
</script>