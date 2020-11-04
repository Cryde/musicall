<template>
    <div>
        <h1><b-link :to="{name: 'admin_dashboard'}">Admin</b-link> / Publication en attente de validation</h1>

        <b-table class="mt-5" striped hover :fields="fields" :items="publications" :busy="isLoading" show-empty>
            <template v-slot:table-busy>
                <div class="has-text-centered text-danger my-2">
                    <b-spinner class="align-middle"></b-spinner>
                </div>
            </template>

            <template v-slot:empty="scope">
                <h4>Il n'y a pas de publications en attente !</h4>
            </template>

            <template v-slot:cell(subCategory)="data">
                {{ data.item.sub_category.title }}
            </template>

            <template v-slot:cell(author)="data">
                {{ data.item.author.username }}
            </template>
            <template v-slot:cell(actions)="data">
                <b-button-group size="sm">
                    <b-button variant="outline-primary" v-b-tooltip.hover title="Voir la publication"
                              :to="{ name: 'publication_show', params: { slug: data.item.slug }}"
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
        const value = await this.$bvModal.msgBoxConfirm('Valider cette publication ?');
        if(value) {
          await this.$store.dispatch('adminPendingPublications/approvePublication', {id});
          await this.$store.dispatch('adminPendingPublications/getPublications');
          this.$store.dispatch('notifications/loadNotifications');
        }
      },
      async confirmReject(id) {
        const value = await this.$bvModal.msgBoxConfirm('Rejeter cette publication ?');
        if(value) {
          await this.$store.dispatch('adminPendingPublications/rejectPublication', {id});
          await this.$store.dispatch('adminPendingPublications/getPublications');
          this.$store.dispatch('notifications/loadNotifications');
        }
      }
    }
  }
</script>