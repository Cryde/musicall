<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Photo de profile</p>
      <button
          type="button"
          class="delete"
          @click="$emit('close')"/>
    </header>

    <section class="modal-card-body">
      <b-message v-if="errors.length" type="is-danger">
        <span v-for="error in errors">{{ error }}</span>
      </b-message>

      <div class="columns mt-1 mb-3">
        <div class="column is-10 is-offset-1">
          <cropper
              ref="cropper"
              :src="image"
              :min-height="450"
              :min-width="450"
              :size-restrictions-algorithm="pixelsRestriction"
              :stencil-component="$options.components.CircleStencil"
          />
        </div>
      </div>
    </section>

    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')">Annuler</b-button>
      <b-button type="is-success"
                :loading="isSubmitted"
                :disabled="isSubmitted"
                icon-left="save"
                @click="save">
        Sauver
      </b-button>
    </footer>
  </div>
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
  methods: {
    pixelsRestriction({minWidth, minHeight, maxWidth, maxHeight, imageWidth, imageHeight}) {
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
          form.append('imageFile', blob);
          try {
            await userApi.changePicture(form);
            this.$root.$emit(EVENT_PROFILE_PICTURE_SUCCESS)
            await this.$store.dispatch('user/refresh');
            this.$root.$emit(EVENT_PROFILE_PICTURE_MODAL_CLOSE);
            this.$emit('close');
          } catch (e) {
            this.errors = e.response.data.violations.map(error => error.message);
          }
        }, 'image/jpeg');
      }
      this.isSubmitted = false;
    }
  }
}
</script>