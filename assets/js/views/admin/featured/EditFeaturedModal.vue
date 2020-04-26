<template>
    <b-modal id="modal-featured-edit" title="Editer la publication mise en avant" ref="modal-featured-edit">

        <b-alert v-show="errors.length" variant="danger" class="mt-3" show>
            <span v-for="error in errors" class="d-block">{{ error }}</span>
        </b-alert>

        <b-form class="mt-2">
            <b-form-group
                    label="Titre de la publication qui sera affiché sur la homepage"
                    label-for="title"
            >
                <b-input v-model="title" id="title" placeholder="Le titre qui sera affiché sur la homepage"></b-input>
            </b-form-group>
            <b-form-group
                    label="Description (non-obligatoire)"
                    label-for="description"
            >
                <b-textarea v-model="description" id="description"></b-textarea>
            </b-form-group>
        </b-form>

        <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
            <b-button variant="default" @click="cancel()">Annuler</b-button>

            <b-button variant="outline-success" @click="save">
                <b-spinner small v-if="isSubmitted"></b-spinner>
                <i class="far fa-save" v-else></i>
                Sauver
            </b-button>
        </template>
        <b-overlay no-wrap :show="showOverlay"></b-overlay>
    </b-modal>
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
        this.isSubmitted = true;
        this.showOverlay = true;
        try {
          await this.$store.dispatch('adminFeatured/edit', {
            featuredId: this.featured.id,
            title: this.title,
            description: this.description
          });
          this.$refs['modal-featured-edit'].hide();
        } catch (e) {

        }
        this.showOverlay = false;
        this.isSubmitted = false;
      },
    }
  }
</script>