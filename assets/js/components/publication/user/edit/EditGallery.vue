<template>
    <div v-if="isLoading" class="text-center pb-3 pt-3">
        <b-spinner/>
    </div>
    <div v-else class="uploader">
        <h1>
            <router-link :to="{name:'user_publications'}" class="mr-2"><i class="fas fa-chevron-left"></i></router-link>
            {{ gallery.title }}
            <span class="p-1 cursor-pointer" v-b-modal.modal-edit-gallery-properties><i class="fas fa-cog"></i></span>
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

        <div v-if="isLoading" class="text-center pb-3 pt-3">
            <b-spinner/>
        </div>
        <div v-else>
            <b-row cols="4" class="mt-5">
                <b-col v-for="image in images" :key="image.id">
                    <div class="preview-image">
                        <div class="squared-image"
                             :style="{ backgroundImage: `url(${image.sizes.medium})`}"></div>
                        <div class="actions">
                            <span
                                    v-show="!coverImage || coverImage.id !== image.id"
                                    class="btn btn-danger" v-b-tooltip title="Supprimer cette image" @click="remove(image)">
                                <i class="fas fa-trash-alt"></i>
                            </span>

                            <span
                                    v-show="!coverImage || coverImage.id !== image.id"
                                    class="btn btn-primary float-right" v-b-tooltip title="Définir cette image comme image de couverture" @click="editCover(image)">
                                <i class="fas fa-image"></i>
                            </span>
                        </div>
                    </div>
                </b-col>
            </b-row>
        </div>

        <edit-gallery-properties-modal/>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';
  import vueDropzone from "vue2-dropzone";
  import EditGalleryPropertiesModal from './Modal/EditGalleryPropertiesModal';

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
      },
      remove(image) {
        this.$store.dispatch('userGallery/removeImage', image);
      },
      error(resp) {
        console.log(resp)
      },
      editCover(image) {
        this.$store.dispatch('userGallery/editCover', {image});
        this.$bvToast.toast(`La cover de la galerie a été correctement modifiée`, {
          title: 'Galerie',
          toaster: 'b-toaster-bottom-left',
          variant: 'success',
          autoHideDelay: 3000,
          appendToast: false
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