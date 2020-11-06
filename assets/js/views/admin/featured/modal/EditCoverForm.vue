<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Uploader une image de cover</p>
      <button type="button" class="delete" @click="$emit('close')"/>
    </header>
    <section class="modal-card-body" style="min-height: 350px">
      <b-message v-if="errors.length" type="is-danger">
        <span v-for="error in errors" class="is-block">{{ error }}</span>
      </b-message>

      <p>
        L'image doit être de la taille 1500x360 précisement.
      </p>

      <vue-dropzone
          v-if="dropzoneOptions"
          id="dropzone"
          @vdropzone-success="uploaded"
          @vdropzone-error="error"
          @vdropzone-file-added="start"
          :options="dropzoneOptions"
      >
      </vue-dropzone>
    </section>

    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')">Annuler</b-button>
      <b-button type="is-success" :disabled="!validImage">Ok</b-button>
    </footer>
  </div>
</template>

<script>
import vue2Dropzone from "vue2-dropzone";

export default {
  components: {
    vueDropzone: vue2Dropzone
  },
  props: ['featured'],
  data() {
    return {
      imageSrc: "",
      dropzoneOptions: null,
      errors: [],
    };
  },
  async mounted() {
    const url = Routing.generate('api_admin_publication_featured_cover', {id: this.featured.id});
    this.dropzoneOptions = {
      url,
      headers: {'Authorization': 'Bearer ' + await this.$store.dispatch('security/getAuthToken', {displayLoading: false})},
      paramName: 'image_upload[imageFile][file]',
      thumbnailWidth: 200,
      maxFilesize: 4,
      dictFileTooBig: 'Le fichier est trop volumineux ({{filesize}}M). Sa taille ne doit pas dépasser {{maxFilesize}} M.',
      dictDefaultMessage: "<i class=\"fas fa-cloud-upload-alt\"></i> Uploader une image"
    };
  },
  computed: {
    validImage() {
      return this.imageSrc.match(/\.(jpeg|jpg|gif|png)$/) != null && this.errors.length === 0;
    }
  },
  methods: {
    error(error, messages) {
      if (!Array.isArray(messages)) {
        this.errors.push(messages);
        return;
      }

      for (const message of messages) {
        this.errors.push(message.message);
      }
    },
    start() {
      this.errors = [];
    },
    async uploaded(file, resp) {
      this.imageSrc = resp.data.uri;
      await this.$store.dispatch('adminFeatured/refreshFeatured');
      this.$emit('close')
    },
  }
};
</script>