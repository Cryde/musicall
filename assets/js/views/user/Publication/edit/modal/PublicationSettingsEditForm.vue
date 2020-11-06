<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Propriété de la publication</p>
      <button type="button" class="delete" @click="$emit('close')"/>
    </header>
    <section class="modal-card-body">

      <b-message v-if="errors.length" type="is-danger">
        <span v-for="error in errors" class="is-block">{{ error }}</span>
      </b-message>

      <b-field label="Le titre de votre publication">
        <b-input v-model="currentTitle"
                 placeholder="Votre titre ici"></b-input>
      </b-field>

      <b-field label="Cette courte description apparaitra sur la page d'accueil">
        <b-input
            type="textarea"
            v-model="currentDescription"
            id="textarea"
            placeholder="Une courte description de l'article"
            rows="3"
        ></b-input>
      </b-field>

      <p class="alert alert-info">
        Attention, l'image doit faire max 4000px de largeur ou hauteur. (max 4mb)
      </p>
      <b-message v-if="imageErrors.length" variant="is-danger">
        <span v-for="error in imageErrors" class="is-block">{{ error }}</span>
      </b-message>
      <div class="columns">
        <div class="column" v-if="currentCover">
          <b-image :src="currentCover"></b-image>
        </div>
        <div class="column" v-else>
          Il n'y a pas encore de cover pour cette publication
        </div>
        <div class="column">
          <vue-dropzone
              v-if="dropzoneOptions"
              ref="myVueDropzone"
              id="dropzone"
              @vdropzone-success="vfileUploaded"
              @vdropzone-error="error"
              @vdropzone-file-added="start"
              :options="dropzoneOptions"
          >
          </vue-dropzone>
        </div>
      </div>
    </section>

    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')">Annuler</b-button>
      <b-button type="is-success"
                :loading="isSaving" icon-left="save" :disabled="isSaving" @click="save">
        Enregistrer
      </b-button>
    </footer>
  </div>
</template>

<script>
import vue2Dropzone from "vue2-dropzone";
import {mapGetters} from 'vuex';

export default {
  components: {
    vueDropzone: vue2Dropzone
  },
  data() {
    return {
      currentTitle: '',
      currentDescription: '',
      currentCover: '',
      dropzoneOptions: null,
      imageErrors: [],
    }
  },
  computed: {
    ...mapGetters('publicationEdit', [
      'id',
      'content',
      'title',
      'description',
      'cover',
      'errors',
      'isSaving',
    ]),
  },
  async mounted() {
    this.currentTitle = this.title;
    this.currentDescription = this.description;
    this.currentCover = this.cover;

    this.dropzoneOptions = {
      url: Routing.generate('api_user_publication_upload_cover', {id: this.id}),
      headers: {'Authorization': 'Bearer ' + await this.$store.dispatch('security/getAuthToken', {displayLoading: false})},
      paramName: 'image_upload[imageFile][file]',
      thumbnailWidth: 200,
      maxFilesize: 4,
      dictFileTooBig: 'Le fichier est trop volumineux ({{filesize}}M). Sa taille ne doit pas dépasser {{maxFilesize}} M.',
      dictDefaultMessage: "<i class=\"fas fa-cloud-upload-alt\"></i> Uploader une cover"
    };
  },
  methods: {
    async save() {
      await this.$store.dispatch('publicationEdit/save', {
        title: this.currentTitle,
        description: this.currentDescription,
        content: this.content
      });

      this.$buefy.toast.open({
        message: 'Les propriétés de votre publication ont été enregistrées',
        type: 'is-success',
        position: 'is-bottom-left',
      });

      this.$emit('close');
    },
    start() {
      this.imageErrors = [];
    },
    vfileUploaded(file, resp) {
      if (resp.error) {
        alert('Erreur lors de l\'upload');
      } else {
        this.currentCover = resp.data.uri;
      }
    },
    error(error, messages) {
      if (!Array.isArray(messages)) {
        this.imageErrors.push(messages);
        return;
      }

      for (const message of messages) {
        this.imageErrors.push(message.message);
      }
    },
  }
}
</script>