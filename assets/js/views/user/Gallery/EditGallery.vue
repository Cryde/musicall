<template>
  <b-loading v-if="isLoading" active/>
  <div v-else class="uploader">
    <b-button icon-left="cog" class="is-pulled-right" @click="$refs['modal-edit-gallery-properties'].open()">
      Configuration
    </b-button>

    <h1 class="subtitle is-3">
      <router-link :to="{name:'user_gallery'}" class="mr-2"><i class="fas fa-chevron-left"></i></router-link>
      {{ gallery.title }}
    </h1>

    <vue-dropzone
        v-if="dropzoneOptions"
        ref="myVueDropzone"
        id="dropzone"
        @vdropzone-success="uploaded"
        @vdropzone-error="error"
        :options="dropzoneOptions"
    >
    </vue-dropzone>

    <div class="columns mt-5 is-multiline">
      <div class="column is-3" v-for="image in images" :key="image.id">
        <div class="preview-image">
          <div class="squared-image"
               :style="{ backgroundImage: `url(${image.sizes.medium})`}"></div>
          <div class="actions">
            <b-tooltip label="Supprimer cette image" type="is-black" v-show="!coverImage || coverImage.id !== image.id">
              <b-button
                  icon-left="trash-alt" type="is-danger"
                  size="is-small"
                  @click="remove(image)">
              </b-button>
            </b-tooltip>

            <b-tooltip v-show="!coverImage || coverImage.id !== image.id"
                       class="is-pulled-right" type="is-black"
                       label="Définir cette image comme image de couverture">
              <b-button
                  type="is-info" size="is-small" icon-left="image" @click="editCover(image)"></b-button>
            </b-tooltip>
          </div>
        </div>
      </div>
    </div>

    <edit-gallery-properties-modal ref="modal-edit-gallery-properties"/>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import vueDropzone from "vue2-dropzone";
import EditGalleryPropertiesModal from './modal/EditGalleryPropertiesModal';

export default {
  components: {vueDropzone, EditGalleryPropertiesModal},
  data() {
    return {
      dropzoneOptions: null
    }
  },
  computed: {
    ...mapGetters('userGallery', [
      'isLoading',
      'gallery',
      'coverImage',
      'images',
    ])
  },
  async mounted() {
    const galleryId = this.$route.params.id;
    this.$store.dispatch('userGallery/loadGallery', galleryId);
    this.$store.dispatch('userGallery/loadImages', galleryId);

    const url = Routing.generate('api_user_gallery_upload_image', {id: galleryId});
    this.dropzoneOptions = {
      url,
      headers: {'Authorization': 'Bearer ' + await this.$store.dispatch('security/getAuthToken', {displayLoading: false})},
      paramName: 'image_upload[imageFile][file]',
      thumbnailWidth: 200,
      dictDefaultMessage: "<i class=\"fas fa-cloud-upload-alt\"></i> Choisir une ou plusieurs images"
    };
  },
  methods: {
    uploaded(xhr, image) {
      this.$store.dispatch('userGallery/addImage', image);
      this.$buefy.toast.open({
        message: 'Image ajoutée à la galerie',
        type: 'is-success',
        position: 'is-bottom-left',
        duration: 1000,
      });
    },
    remove(image) {
      this.$store.dispatch('userGallery/removeImage', image);
      this.$buefy.toast.open({
        message: 'Image supprimée de la galerie',
        type: 'is-warning',
        position: 'is-bottom-left',
        duration: 1000,
      });
    },
    error(resp) {
      console.log(resp)
    },
    editCover(image) {
      this.$store.dispatch('userGallery/editCover', {image});

      this.$buefy.toast.open({
        message: 'La cover de la galerie a été correctement modifiée',
        type: 'is-success',
        position: 'is-bottom-left',
        duration: 5000,
      });
    }
  }
}
</script>

<style>
.uploader .dropzone {
  height: 300px;
  max-height: 300px;
  overflow: auto;
}

.preview-image {
  position: relative;
}

.squared-image {
  width: 100%;
  padding-bottom: 100%;
  margin: 15px auto;
  display: block;
  background-position: 50%;
  background-repeat: no-repeat;
  background-size: cover;
}

.preview-image:hover .actions {
  opacity: 1;
}

.actions {
  opacity: 0;
  position: absolute;
  top: 10px;
  left: 10px;
  right: 10px;
  transition: all .4s;
}
</style>