<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Ajouter une publication</p>
      <button type="button" class="delete" @click="$emit('close')"/>
    </header>

    <section class="modal-card-body" style="min-height: 300px;">
      <div v-if="!saved">
        <b-field label="La catégorie de votre publication">
          <v-select :options="publicationCategories" v-model="category" label="title"></v-select>
        </b-field>

        <b-field label="Le titre de votre publication">
          <b-input v-model="title" placeholder="Votre titre ici"></b-input>
        </b-field>
      </div>
      <div v-else class="has-text-centered p-5">
        <i class="fas fa-check fa-5x has-text-success mb-3"></i><br/>
        Votre publication est créée
      </div>
    </section>

    <footer class="modal-card-foot">
      <b-button v-if="!saved" type="is-light" @click="$emit('close')">Annuler</b-button>

      <b-button type="is-success" v-if="!saved"
                :loading="submitted"
                :disabled="submitted || !canSend"
                icon-left="save"
                @click="save">
        Enregistrer
      </b-button>

      <b-button v-if="saved" type="is-success" :to="editUrl" tag="router-link">
        Editer la publication
      </b-button>
    </footer>
  </div>
</template>

<script>
import vSelect from 'vue-select';
import {mapGetters} from 'vuex';
import userPublication from "../../../../../api/userPublication";
import {EVENT_PUBLICATION_CREATED} from "../../../../../constants/events";

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
    }
  },
  computed: {
    ...mapGetters('publicationCategory', ['publicationCategories']),
    canSend() {
      return this.category && this.title.trim().length > 0;
    },
  },
  methods: {
    async save() {
      this.submitted = true;
      const categoryId = this.category ? this.category.id : null;
      try {
        const publication = await userPublication.addPublication({title: this.title, categoryId})

        this.saved = true;
        this.$root.$emit(EVENT_PUBLICATION_CREATED);
        this.editUrl = {name: 'user_publications_edit', params: {id: publication.id}};

        this.$buefy.toast.open({
          message: 'Votre publication a été enregistrée',
          type: 'is-success',
          position: 'is-bottom-left',
        });
      } catch (e) {
        this.errors = e.response.data.violations.map(item => item.title);
      }
      this.submitted = false;
    },
  },
  destroyed() {
    this.errors = [];
    this.saved = false;
    this.editUrl = '';
    this.title = '';
    this.category = null;
  }
}
</script>