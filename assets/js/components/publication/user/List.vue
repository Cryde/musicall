<template>

    <div>
        <b-button v-b-modal.modal-publication-add variant="outline-success" class="float-right">
            <i class="fas fa-plus"></i> Ajouter une publication
        </b-button>

        <h1>Mes publications</h1>

        <div class="content-box mt-3">
            <b-table
                    id="publication-table"
                    ref="table"
                    :busy.sync="isBusy"
                    :sort-by.sync="sortBy"
                    :sort-desc.sync="sortDesc"
                    show-empty
                    borderless
                    stacked="md"
                    :items="publicationProvider"
                    :fields="fields"
            >
                <div slot="table-busy" class="text-center my-2">
                    <b-spinner class="align-middle"></b-spinner>
                </div>

                <template slot="actions" slot-scope="data">
                    <b-button-group>
                        <b-button variant="outline-primary" :href="data.item.url_show" target="_blank"><i
                                class="far fa-eye"></i></b-button>
                        <b-button variant="outline-success"
                                  :to="{ name: 'user_publications_edit', params: { id: data.item.id }}"><i
                                class="far fa-edit"></i>
                        </b-button>
                        <b-button variant="outline-danger" @click="showDeleteModal" :data-id="data.item.id">
                            <i class="far fa-trash-alt" :data-id="data.item.id"></i>
                        </b-button>
                    </b-button-group>
                </template>
            </b-table>
        </div>
        <AddPublicationModal/>
    </div>
</template>

<script>
  import AddPublicationModal from './AddPublicationModal'

  export default {
    components: {AddPublicationModal},
    data() {
      return {
        isBusy: false,
        sortBy: '',
        sortDesc: true,
        fields: [
          {key: 'title', label: 'Titre', sortable: true},
          {key: 'creation_datetime', label: 'Création', sortable: true},
          {key: 'edition_datetime', label: 'Mis à jour', sortable: true},
          {key: 'status', label: 'Statut', sortable: true},
          {key: 'actions', sortable: false}
        ],
      }
    },
    mounted() {
      this.$root.$on('reload-table', () => {
        this.$refs.table.refresh();
      });
    },
    methods: {
      publicationProvider(ctx) {
        return fetch(Routing.generate('api_user_publication_list'), {method: 'POST', body: JSON.stringify(ctx)})
        .then(resp => resp.json())
        .then((data) => {
          return data.publications.map((publication) => {
            return Object.assign({}, publication, {
              url_show: Routing.generate('publications_show', {slug: publication.slug})
            });
          });
        }).catch(error => {
          console.error(error);
          return []
        })
      },
      showDeleteModal(item) {
        this.$bvModal.msgBoxConfirm('Êtes vous sur ?')
        .then(value => {

          if (!value) {
            return;
          }

          const id = item.target.dataset.id;

          this.deleteItem(id)
          .then(() => {
            this.$refs.table.refresh();
          })
          .catch((error) => {
            console.error(error);
          });
        })
        .catch(err => {
          // An error occurred
        })
      },
      deleteItem(id) {
        return fetch(Routing.generate('api_user_publication_delete', {id}))
      }
    }
  }
</script>