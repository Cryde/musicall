<template>
    <div>
        <h1>
            <b-link :to="{name: 'admin_dashboard'}">Admin</b-link>
            / Gallery en attente de validation
        </h1>

        <b-table class="mt-5" striped hover :fields="fields" :items="galleries" :busy="isLoading" show-empty>
            <template v-slot:table-busy>
                <div class="text-center text-danger my-2">
                    <b-spinner class="align-middle"></b-spinner>
                </div>
            </template>

            <template v-slot:empty="scope">
                <h4>Il n'y a pas de galeries en attente !</h4>
            </template>

            <template v-slot:cell(author)="data">
                {{ data.item.author.username }}
            </template>
            <template v-slot:cell(actions)="data">
                <b-button-group size="sm">
                    <b-button variant="outline-primary" v-b-tooltip.hover title="Voir la galerie"
                              :to="{ name: 'gallery_show', params: { slug: data.item.slug }}"
                              target="_blank">
                        <i class="far fa-eye fa-fw"></i>
                    </b-button>

                    <b-button variant="outline-success" v-b-tooltip.hover title="Valider la publication"
                              @click="confirmApprove(data.item.id)">
                        <i class="fas fa-check fa-fw"></i>
                    </b-button>

                    <b-button variant="outline-danger" v-b-tooltip.hover title="Rejeter la publication"
                              @click="confirmReject(data.item.id)">
                        <i class="fas fa-times fa-fw"></i>
                    </b-button>

                </b-button-group>
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