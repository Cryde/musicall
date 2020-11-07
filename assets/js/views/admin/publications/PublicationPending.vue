<template>
  <div>
    <breadcrumb
        :root="{to: {name: 'admin_dashboard'}, label: 'Admin'}"
        :current="{label: 'Publication en attente de validation'}"
    />

    <h1 class="subtitle is-3">Publication en attente de validation</h1>

    <b-table :data="publications" :loading="isLoading" mobile-cards>

      <b-table-column field="title" label="Titre" sortable v-slot="props">
        {{ props.row.title }}
      </b-table-column>

      <b-table-column field="sub_category" label="Catégorie" sortable v-slot="props">
        {{ props.row.sub_category.title }}
      </b-table-column>

      <b-table-column field="author" label="Auteur" sortable v-slot="props">
        {{ props.row.author.username }}
      </b-table-column>

      <b-table-column label="Actions" v-slot="props">
        <b-field size="is-small">
          <p class="control">
            <b-tooltip label="Voir la publication" type="is-black">
              <b-button size="is-small" type="is-info is-light"
                        target="_blank" tag="router-link"
                        icon-left="eye"
                        :to="{ name: 'publication_show', params: { slug: props.row.slug }}">
              </b-button>
            </b-tooltip>
          </p>

          <p class="control">
            <b-tooltip label="Valider la publication" type="is-black">
              <b-button size="is-small" type="is-success is-light"
                        icon-left="check"
                        @click="confirmApprove(props.row.id)">
              </b-button>
            </b-tooltip>
          </p>

          <p class="control">
            <b-tooltip label="Rejeter la publication" type="is-black">
              <b-button size="is-small" type="is-danger is-light"
                        icon-left="times"
                        @click="confirmReject(props.row.id)">
              </b-button>
            </b-tooltip>
          </p>
        </b-field>
      </b-table-column>

      <template #empty>
        <div class="has-text-centered" v-if="!isLoading">
          Il n'y a pas de publications en attente !
        </div>
      </template>
    </b-table>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import Breadcrumb from "../../../components/global/Breadcrumb";

export default {
  components: {Breadcrumb},
  data() {
    return {
      fields: [
        {key: 'title', label: 'Titre'},
        {key: 'subCategory', 'label': 'Categorie'},
        {key: 'author', label: "Auteur"},
        {key: 'actions'},
      ]
    }
  },
  computed: {
    ...mapGetters('adminPendingPublications', ['publications', 'isLoading'])
  },
  async mounted() {
    await this.$store.dispatch('adminPendingPublications/getPublications');
  },
  methods: {
    async confirmApprove(id) {
      const {result, dialog} = await this.$buefy.dialog.confirm({
        message: 'Valider cette publication ?',
        confirmText: 'Oui', cancelText: 'Annuler',
      });

      dialog.close();

      if (result) {
        await this.$store.dispatch('adminPendingPublications/approvePublication', {id});
        await this.$store.dispatch('adminPendingPublications/getPublications');
        this.$store.dispatch('notifications/loadNotifications');
      }
    },
    async confirmReject(id) {
      const {result, dialog} = await this.$buefy.dialog.confirm({
        message: 'Rejeter cette publication ?',
        confirmText: 'Oui', cancelText: 'Annuler',
      });
      dialog.close();
      if (result) {
        await this.$store.dispatch('adminPendingPublications/rejectPublication', {id});
        await this.$store.dispatch('adminPendingPublications/getPublications');
        this.$store.dispatch('notifications/loadNotifications');
      }
    }
  }
}
</script>