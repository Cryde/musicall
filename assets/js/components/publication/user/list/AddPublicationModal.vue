<template>
    <b-modal id="modal-publication-add" title="Ajouter une publication">

        <div v-if="!saved">
            <b-form-group description="La catégorie de votre publication">
                <v-select :options="publicationCategories" v-model="category" label="title"></v-select>
                <b-form-invalid-feedback :state="validation.subCategory.state">
                    {{ validation.subCategory.message }}
                </b-form-invalid-feedback>
            </b-form-group>


            <b-form-group description="Le titre de votre publication">
                <b-form-input v-model="title" :state="validation.title.state"
                              placeholder="Votre titre ici"></b-form-input>
                <b-form-invalid-feedback :state="validation.title.state">
                    {{ validation.title.message }}
                </b-form-invalid-feedback>
            </b-form-group>
        </div>
        <div v-else class="text-center p-5">
            <i class="fas fa-check fa-5x text-success mb-3"></i><br/>
            Votre publication est créée
        </div>

        <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
            <b-button v-if="!saved" variant="default" @click="cancel()">
                Annuler
            </b-button>

            <b-button v-if="!saved" variant="outline-success" @click="save" :disabled="submitted">
                <b-spinner small v-if="submitted"></b-spinner>
                <i class="far fa-save" v-else></i>
                Enregistrer
            </b-button>

            <b-button v-if="saved" variant="outline-success" :to="editUrl">
                Editer la publication
            </b-button>

        </template>
    </b-modal>
</template>

<script>
  import vSelect from 'vue-select';
  import {mapGetters} from 'vuex';
  import userPublication from "../../../../api/userPublication";

  export default {
    components: {
      'v-select': vSelect
    },
    data() {
      return {
        submitted: false,
        saved: false,
        title: '',
        category: null,
        editUrl: '',
        validation: {
          title: {
            state: null,
            message: '',
          },
          subCategory: {
            state: null,
            message: '',
          }
        }
      }
    },
    computed: {
      ...mapGetters('publicationCategory', ['publicationCategories']),
    },
    mounted() {
      this.$root.$on('bv::modal::hidden', (bvEvent, modalId) => {
        if (modalId !== 'modal-publication-add') {
          return;
        }

        this.saved = false;
        this.editUrl = '';
        this.title = '';
        this.category = null;
        this.resetValidationState();
      });
    },
    methods: {
      save() {
        this.submitted = true;
        const categoryId = this.category ? this.category.id : null;
        this.resetValidationState();
        userPublication.addPublication({title: this.title, categoryId})
        .then((publication) => {
          this.submitted = false;
          this.saved = true;
          this.$root.$emit('publication-added');
          this.editUrl = { name: 'user_publications_edit', params: { id: publication.id }};
          this.$bvToast.toast('Votre publication a été enregistrée', {
            title: `Publication enregistrée`,
            variant: 'success',
            solid: true,
            toaster: 'b-toaster-bottom-left',
            append: true
          });
        })
        .catch(violation => {
          this.submitted = false;
          if (violation.data.errors.violations) {
            this.displayErrors(violation.data.errors.violations);
          }
        });
      },
      displayErrors(errors) {
        for (let error of errors) {
          const propertyPath = error.propertyPath;
          const message = error.title;

          this.validation[propertyPath].state = false;
          this.validation[propertyPath].message = message;
        }
      },
      resetValidationState() {
        this.validation.title = {state: null, message: ''};
        this.validation.subCategory = {state: null, message: ''};
      },
    }
  }
</script>