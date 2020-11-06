<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Ajouter une galerie</p>
      <button type="button" class="delete" @click="$emit('close')"/>
    </header>

    <section class="modal-card-body">
      <div v-if="!saved">
        <b-input v-model="name" placeholder="Le titre de la galerie"></b-input>

        <b-message type="is-warning" size="is-small" has-icon class="mt-3">
          Privilégiez une forme de titre comme suit :
          Artiste - date - salle/festival - ville<br/>
          <strong>Exemple :</strong><br/>
          Metallica - 30 mars 2020 - Botanique - Bruxelles<br/>
          Metallica - 30 juin 2020 - Rock Werchter - Werchter
        </b-message>

        <b-message type="is-info">
          La galerie ne sera pas mise en ligne directement.<br/>
          Vous pourrez encore modifier le titre plus tard
        </b-message>
      </div>
      <div v-else class="has-text-centered">
        <i class="fas fa-check fa-5x has-text-success mb-3"></i><br/>
        La galerie a été créée.<br/>
        Vous pouvez désormais lui ajouter des photos
      </div>
    </section>


    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')">Annuler</b-button>

      <b-button
          v-if="!saved"
          type="is-success"
          :loading="submitted"
          icon-left="save"
          :disabled="submitted" @click="saveGallery">
        Enregistrer
      </b-button>

      <b-button v-if="saved" type="is-success" :to="galleryUrl" tag="router-link">
        Editer la galerie
      </b-button>
    </footer>
  </div>
</template>

<script>
import galleryApi from "../../../../api/publication/gallery";

export default {
  data() {
    return {
      name: '',
      saved: false,
      submitted: false,
      galleryUrl: null
    }
  },
  methods: {
    async saveGallery() {
      try {
        this.submitted = true;
        const gallery = await galleryApi.addGallery({title: this.name});
        await this.$store.dispatch('userGalleries/load');
        this.galleryUrl = {name: 'user_gallery_edit', params: {id: gallery.id}};
        this.saved = true;
      } catch (e) {
        this.submitted = false;
        console.error(e);
      }
    },
    reset() {
      this.name = '';
      this.saved = false;
      this.submitted = false;
      this.galleryUrl = null;
    }
  },
  destroyed() {
    this.reset();
  }
}
</script>