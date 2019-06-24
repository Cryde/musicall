<template>
    <b-modal id="modal-publication-add" ref="test" size="lg" title="Ajouter une publication">

        <div v-if="!saved">
            <div v-if="loading.loaded">
                <b-form-group description="La catégorie de votre publication">
                    <v-select :options="categories" v-model="category" label="title"></v-select>
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
            <div v-else class="text-center">
                <b-spinner style="width: 3rem; height: 3rem;" label="Large Spinner"></b-spinner>
            </div>
        </div>
        <div v-else class="text-center p-5">
            <i class="fas fa-check fa-5x text-success mb-3"></i><br/>

            Votre publication est créé
        </div>

        <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
            <b-button v-if="!saved" variant="default" @click="cancel()">
                Annuler
            </b-button>

            <b-button v-if="!saved" variant="outline-success" @click="save" :disabled="loading.fetching || submitted  ">
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
  import vSelect from 'vue-select'

  export default {
    components: {
      'v-select': vSelect
    },
    data() {
      return {
        loading: {
          fetching: false,
          loaded: false
        },
        submitted: false,
        saved: false,

        categories: null,
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
    mounted() {
      this.$root.$on('bv::modal::shown', this.loadData);
      this.$root.$on('bv::modal::hidden', () => {
        this.categories = null;
        this.loading.fetching = false;
        this.loading.loaded = false;
        this.saved = false;
        this.editUrl = '';
        this.title = '';
        this.category = null;
        this.resetValidationState();
      });
    },
    methods: {
      loadData(bvEvent, modalId) {
        if (modalId !== 'modal-publication-add' || this.loading.fetching) {
          return;
        }

        this.loading.fetching = true;

        this.getCategories()
        .then((categories) => {
          this.loading.fetching = false;
          this.loading.loaded = true;
          this.categories = categories;
        });
      },
      save() {
        this.submitted = true;
        const categoryId = this.category ? this.category.id : null;
        this.savePublication({title: this.title, categoryId})
        .then((publication) => {
          this.submitted = false;
          this.saved = true;
          this.$root.$emit('reload-table');
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
      savePublication({title, categoryId}) {
        this.resetValidationState();
        return fetch(Routing.generate('api_user_publication_add'), {
          method: 'POST',
          body: JSON.stringify({title, category_id: categoryId})
        })
        .then(this.handleErrors)
        .then(resp => resp.json())
        .then(resp => resp.data.publication);
      },
      displayErrors(errors) {
        for (let error of errors) {
          const propertyPath = error.propertyPath;
          const message = error.title;

          this.validation[propertyPath].state = false;
          this.validation[propertyPath].message = message;
        }
      },
      async handleErrors(response) {
        console.log(response);
        if (!response.ok) {
          const data = await response.json();
          return Promise.reject(data)
        }
        return response;
      },
      resetValidationState() {
        this.validation.title = {state: null, message: ''};
        this.validation.subCategory = {state: null, message: ''};
      },
      getCategories() {
        return fetch(Routing.generate('api_publication_category_list'))
        .then(resp => resp.json())
        .then(resp => resp.data.categories)
      },
    },
    destroyed() {
      this.$root.$off('bv::modal::shown', this.loadData);
    }
  }
</script>