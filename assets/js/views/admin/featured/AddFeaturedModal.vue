<template>
    <b-modal id="modal-featured-add" title="Ajouter une publication mise en avant" ref="modal-featured-add" size="xl">

        <v-select @search="onSearch" :filtrable="false" :options="results" label="title" @input="selectValue">
            <template slot="no-options">
                Rechercher parmi les publications
            </template>
            <template slot="option" slot-scope="option">
                <div class="d-center">
                    <b-img :src="option.cover_image" style="max-width: 50px"/>
                    {{ option.title }}
                </div>
            </template>
            <template slot="selected-option" slot-scope="option">
                <div class="selected d-center">
                    <b-img :src="option.cover_image" style="max-width: 50px"/>
                    {{ option.title }}
                </div>
            </template>
        </v-select>

        <b-alert v-show="errors.length" variant="danger" class="mt-3" show>
            <span v-for="error in errors" class="d-block">{{ error }}</span>
        </b-alert>

        <b-form class="mt-5" v-if="publication">
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
        <div v-else class="mt-5 pt-5 pb-5 text-center">
            Choissisez une publication avec le select de recherche
        </div>

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
  import {debounce} from 'lodash';
  import vSelect from "vue-select";
  import {mapGetters} from "vuex";

  export default {
    components: {vSelect},
    props: ['level'],
    data() {
      return {
        isSubmitted: false,
        title: '',
        description: '',
        publication: null,
        showOverlay: false,
        errors: [],
      }
    },
    computed: {
      ...mapGetters('adminFeatured', ['results'])
    },
    methods: {
      async save() {
        this.isSubmitted = true;
        this.showOverlay = true;
        try {
          await this.$store.dispatch('adminFeatured/save', {
            level: this.level,
            publicationId: this.publication.id,
            title: this.title,
            description: this.description,
          });
          this.$refs['modal-featured-add'].hide();
        } catch (e) {
          this.errors = e.response.data.violations.map(violation => violation.title);
        }
        this.showOverlay = false;
        this.isSubmitted = false;
      },
      selectValue(val) {
        this.publication = val;
        this.title = val.title;
        this.description = val.description;
      },
      onSearch(search, loading) {
        loading(true);
        this.search(loading, search, this);
      },
      search: debounce(async (loading, search, vm) => {
        await vm.$store.dispatch('adminFeatured/searchPublication', search);
        loading(false)
      }, 350)
    }
  }
</script>