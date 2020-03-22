<template>
    <div v-if="isLoading" class="text-center pb-3 pt-3">
        <b-spinner/>
    </div>
    <div v-else class="uploader">
        <h1>{{ gallery.title }} <span><i class="fas fa-cog"></i></span></h1>


        <vue-dropzone
                v-if="dropzoneOptions"
                ref="myVueDropzone"
                id="dropzone"
                @vdropzone-success="uploaded"
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

                    </div>
                </b-col>
            </b-row>
        </div>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';
  import vueDropzone from "vue2-dropzone";

  export default {
    components: {vueDropzone},
    data() {
      return {
        dropzoneOptions: null
      }
    },
    computed: {
      ...mapGetters('userGallery', [
        'isLoading',
        'isLoadingImages',
        'gallery',
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
    }

    .squared-image {
        display: block;
        background-position: 50%;
        background-repeat: no-repeat;
        background-size: cover;
    }
</style>