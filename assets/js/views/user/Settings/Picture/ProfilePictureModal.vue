<template>
  <b-modal id="profile-picture-modal" title="aaaa" ref="profile-picture-modal" size="lg">
    <b-alert v-if="errors.length" show variant="danger">
      <span v-for="error in errors">{{ error }}</span>
    </b-alert>

    <b-row class="mt-1 mb-3">
      <b-col xl="10" offset-xl="1">
        <cropper
            ref="cropper"
            :src="image"
            :min-height="450"
            :min-width="450"
            :size-restrictions-algorithm="pixelsRestriction"
            :stencil-component="$options.components.CircleStencil"
        />
      </b-col>
    </b-row>

    <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
      <b-button variant="default" @click="cancel()">Annuler</b-button>

      <b-button variant="outline-success" @click="save">
        <b-spinner small v-if="isSubmitted"></b-spinner>
        <i class="far fa-save" v-else></i>
        Sauver
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import {CircleStencil, Cropper} from 'vue-advanced-cropper';
import {EVENT_PROFILE_PICTURE_MODAL_CLOSE, EVENT_PROFILE_PICTURE_SUCCESS} from "../../../../constants/events";
import userApi from "../../../../api/user";

export default {
  components: {Cropper, CircleStencil},
  props: {
    image: String
  },
  data() {
    return {
      errors: [],
      isSubmitted: false,
      coordinates: {
        width: 0,
        height: 0,
        left: 0,
        top: 0,
      },
    }
  },
  mounted() {
    this.$refs['profile-picture-modal'].$on('hidden', () => {
      this.$root.$emit(EVENT_PROFILE_PICTURE_MODAL_CLOSE);
    });
    this.$bvModal.show('profile-picture-modal');
  },
  methods: {
    pixelsRestriction({ minWidth, minHeight, maxWidth, maxHeight, imageWidth, imageHeight }) {
      return {
        minWidth: minWidth,
        minHeight: minHeight,
        maxWidth: maxWidth,
        maxHeight: maxHeight,
      };
    },
    save() {
      this.isSubmitted = true;
      const {canvas} = this.$refs.cropper.getResult();
      if (canvas) {
        const form = new FormData();
        canvas.toBlob(async blob => {
          form.append('image_upload[imageFile][file]', blob);
          try {
            await userApi.changePicture(form);
            this.$root.$emit(EVENT_PROFILE_PICTURE_SUCCESS)
            await this.$store.dispatch('user/refresh');
            this.$bvModal.hide('profile-picture-modal');
          } catch(e) {
            this.errors = e.response.data.map(error => error.message);
          }
        }, 'image/jpeg');
      }
      this.isSubmitted = false;
    }
  }
}
</script>