<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Ajouter une publication mise en avant</p>
      <button type="button" class="delete" @click="$emit('close')"/>
    </header>

    <section class="modal-card-body" style="min-height: 350px">

      <v-select @search="onSearch" style="max-width: 500px" :filtrable="false" :options="results" label="title" @input="selectValue">
        <template slot="no-options">
          Rechercher parmi les publications
        </template>
        <template slot="option" slot-scope="option">
          <div class="is-flex is-align-items-center">
            <b-image :src="option.cover_image" style="width: 50px" class="mr-2 "/>
            {{ option.title }}
          </div>
        </template>
        <template slot="selected-option" slot-scope="option">
          <div class="selected d-center">
            <b-image :src="option.cover_image" style="max-width: 50px"/>
            {{ option.title }}
          </div>
        </template>
      </v-select>

      <b-message v-if="errors.length" type="is-danger" class="mt-3">
        <span v-for="error in errors" class="is-block">{{ error }}</span>
      </b-message>

      <div class="mt-5" v-if="publication">
        <b-field
            label="Titre de la publication qui sera affiché sur la homepage"
            label-for="title"
        >
          <b-input v-model="title" id="title" placeholder="Le titre qui sera affiché sur la homepage"></b-input>
        </b-field>
        <b-field
            label="Description (non-obligatoire)"
            label-for="description"
        >
          <b-input type="textarea" v-model="description" id="description"></b-input>
        </b-field>
      </div>
      <div v-else class="mt-5 pt-5 pb-5 has-text-centered">
        Choissisez une publication avec le select de recherche
      </div>
    </section>

    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')">Annuler</b-button>
      <b-button type="is-success" :loading="isSubmitted" :disabled="isSubmitted" icon-left="save" @click="save">
        Sauver
      </b-button>
    </footer>
    <b-loading :active="showOverlay"></b-loading>
  </div>
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
        this.$emit('close');
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