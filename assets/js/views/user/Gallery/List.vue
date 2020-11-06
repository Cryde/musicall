<template>
  <div>
    <b-button icon-left="images" type="is-info" class="is-pulled-right" @click="$refs['modal-gallery-add'].open()">
      Ajouter une galerie
    </b-button>

    <h1 class="subtitle is-3">Mes galeries</h1>

    <b-table :data="galleries" :loading="isLoading">
      <template #empty>
        <div class="has-text-centered" v-if="!isLoading">
          Vous n'avez pas encore de galeries
        </div>
      </template>

      <b-table-column field="title" label="Titre" sortable v-slot="props">
        {{ props.row.title }}
      </b-table-column>

      <b-table-column field="status" label="Status" sortable v-slot="props">
        <span class="tag is-success" v-if="props.row.status === 0">En ligne</span>
        <span class="tag is-light" v-if="props.row.status === 1">Brouillon</span>
        <span class="tag is-warning" v-if="props.row.status === 2">En validation</span>
      </b-table-column>

      <b-table-column label="Actions" v-slot="props">
        <b-field size="is-small">
          <p class="control">
            <b-tooltip label="Modifier la galerie" type="is-black" v-if="props.row.status === 1">
              <b-button size="is-small" type="is-success is-light"
                        icon-left="edit" tag="router-link"
                        :to="{ name: 'user_gallery_edit', params: { id: props.row.id }}">
              </b-button>
            </b-tooltip>
          </p>
          <p class="control">
            <b-tooltip label="Voir la galerie" type="is-black">
              <b-button size="is-small" type="is-info is-light"
                        target="_blank" tag="router-link"
                        icon-left="eye"
                        :to="{ name: 'gallery_show', params: { slug: props.row.slug }}">
              </b-button>
            </b-tooltip>
          </p>
          <p class="control">
            <b-tooltip label="Publier la galerie" type="is-black" v-if="props.row.status === 1">
              <b-button size="is-small" type="is-success is-light"
                        icon-left="paper-plane"
                        @click="publish(props.row.id)">
              </b-button>
            </b-tooltip>
          </p>
        </b-field>
      </b-table-column>
    </b-table>

    <errors-modal :errors="errors" ref="modal-errors"/>
    <add-gallery-modal ref="modal-gallery-add"/>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import ErrorsModal from "./modal/ErrorsModal";
import AddGalleryModal from "./modal/AddGalleryModal";

export default {
  components: {ErrorsModal, AddGalleryModal},
  data() {
    return {
      errors: [],
      fields: ['title', 'status', 'actions'],
    }
  },
  computed: {
    ...mapGetters('userGalleries', ['isLoading', 'galleries'])
  },
  mounted() {
    this.$store.dispatch('userGalleries/load');
  },
  methods: {
    async publish(id) {

      const { result, dialog } = await this.$buefy.dialog.confirm({
        title: 'Êtes vous sur ?',
        message: 'Une fois mise en ligne vous ne pourrez plus modifier la galerie',
        closeOnConfirm: false,
        cancelText: 'Annuler',
        confirmText: 'Oui',
      });

      if (!result) {
        return;
      }

      dialog.close();

      try {
        await this.$store.dispatch('userGalleries/publish', id);
      } catch (e) {
        this.errors = e.response.data.violations.map(violation => violation.title);

        this.$nextTick(() => {
          this.$refs['modal-errors'].open();
        });
      }
    }
  }
}
</script>