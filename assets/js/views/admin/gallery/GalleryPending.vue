<template>
    <div>
      <breadcrumb
          :root="{to: {name: 'admin_dashboard'}, label: 'Admin'}"
          :current="{label: 'Gallery en attente de validation'}"
      />
      <h1 class="subtitle is-3">Gallery en attente de validation</h1>


      <b-table :data="galleries" :loading="isLoading" mobile-cards>

        <b-table-column field="title" label="Titre" sortable v-slot="props">
          {{ props.row.title }}
        </b-table-column>
        <b-table-column field="author" label="Auteur" sortable v-slot="props">
          {{ props.row.author.username }}
        </b-table-column>

        <b-table-column label="Actions" v-slot="props">
          <b-field size="is-small">
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
              <b-tooltip label="Valider la galerie" type="is-black">
                <b-button size="is-small" type="is-success is-light"
                          icon-left="check"
                          @click="confirmApprove(props.row.id)">
                </b-button>
              </b-tooltip>
            </p>

            <p class="control">
              <b-tooltip label="Rejeter la galerie" type="is-black">
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
            Il n'y a pas de galleries en attente !
          </div>
        </template>
      </b-table>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    data() {
      return {
        fields: [
          {key: 'title', label: 'Titre'},
          {key: 'author', label: "Auteur"},
          {key: 'actions'},
        ]
      }
    },
    computed: {
      ...mapGetters('adminPendingGalleries', ['galleries', 'isLoading'])
    },
    async mounted() {
      await this.$store.dispatch('adminPendingGalleries/loadPendingGalleries');
    },
    methods: {
      async confirmApprove(id) {
        const value = await this.$bvModal.msgBoxConfirm('Valider cette publication ?');
        if (value) {
          await this.$store.dispatch('adminPendingGalleries/approveGallery', {id});
          await this.$store.dispatch('adminPendingGalleries/loadPendingGalleries');
          this.$store.dispatch('notifications/loadNotifications');
        }
      },
      async confirmReject(id) {
        const value = await this.$bvModal.msgBoxConfirm('Rejeter cette publication ?');
        if (value) {
          await this.$store.dispatch('adminPendingGalleries/rejectGallery', {id});
          await this.$store.dispatch('adminPendingGalleries/loadPendingGalleries');
          this.$store.dispatch('notifications/loadNotifications');
        }
      }
    }
  }
</script>