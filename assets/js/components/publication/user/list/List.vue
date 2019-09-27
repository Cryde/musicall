<template>

    <div>
        <b-dropdown id="dropdown-1" text="Ajouter" right variant="outline-success" class="float-right">
            <b-dropdown-item v-b-modal.modal-publication-add><i class="far fa-edit"></i> une publication</b-dropdown-item>
            <b-dropdown-item v-b-modal.modal-video-add><i class="fas fa-video"></i> une video</b-dropdown-item>
        </b-dropdown>

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

                <template v-slot:cell(actions)="data">
                    <b-button-group>
                        <b-button v-if="data.item.status_id === 0" variant="outline-success" v-b-tooltip.hover title="Publier la publication"
                                  @click="publishPublication(data.item.id)">
                            <i class="far fa-paper-plane"></i>
                        </b-button>
                        <b-button variant="outline-primary" v-b-tooltip.hover title="Voir la publication"
                                  :to="{ name: 'publication_show', params: { slug: data.item.slug }}"
                                  target="_blank"><i
                                class="far fa-eye"></i></b-button>
                        <b-button v-if="data.item.status_id === 0" variant="outline-success"
                                  v-b-tooltip.hover title="Modifier la publication"
                                  :to="{ name: 'user_publications_edit', params: { id: data.item.id }}"><i
                                class="far fa-edit"></i>
                        </b-button>
                        <b-button v-if="data.item.status_id === 0" variant="outline-danger" v-b-tooltip.hover title="Supprimer la publication"
                                  @click="showDeleteModal(data.item.id)">
                            <i class="far fa-trash-alt"></i>
                        </b-button>
                    </b-button-group>
                </template>
            </b-table>
        </div>
        <AddPublicationModal/>
        <AddVideoModal/>

        <b-modal id="modal-publication-control" centered title="Publier la publication">
            <div v-if="loadingControlPublication" class="p-5 text-center">
                <b-spinner label="Spinning"></b-spinner>
            </div>
            <div v-else>
                <div v-if="errors.length">
                    <ul>
                        <li v-for="error in errors">{{ error }}</li>
                    </ul>
                </div>
                <div v-else class="text-center">
                    <i class="fas fa-check fa-5x text-success mb-3"></i><br/>
                    Votre publication a été publiée !
                </div>
            </div>


            <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
                <b-button variant="default" @click="cancel()">
                    Fermer
                </b-button>

            </template>
        </b-modal>
    </div>
</template>

<script>
  import AddPublicationModal from './AddPublicationModal'
  import AddVideoModal from './AddVideoModal'

  export default {
    components: {AddPublicationModal, AddVideoModal},
    data() {
      return {
        loadingControlPublication: false,
        isBusy: false,
        errors: [],
        showPublicationUrl: '',
        sortBy: '',
        sortDesc: true,
        fields: [
          {key: 'title', label: 'Titre', sortable: true},
          {key: 'creation_datetime', label: 'Création', sortable: true},
          {key: 'edition_datetime', label: 'Mis à jour', sortable: true},
          {key: 'status_label', label: 'Statut', sortable: true},
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
        .then((data) => data.publications)
        .catch(error => {
          console.error(error);
          return []
        })
      },
      async showDeleteModal(id) {
        const value = await this.$bvModal.msgBoxConfirm('Êtes vous sur ?', {
          okTitle: 'Oui',
          cancelTitle: 'Annuler',
        });

        if (!value) {
          return;
        }

        try {
          await this.deleteItem(id)
          this.$refs.table.refresh();
        } catch (error) {
          console.error(error);
        }
      },
      async publishPublication(id) {
        this.errors = [];
        const value = await this.$bvModal.msgBoxConfirm('Une fois mise en ligne vous me pourrez plus modifier la publication.', {
          title: 'Êtes vous sur ?',
          okTitle: 'Oui',
          cancelTitle: 'Annuler',
          centered: true
        });

        if (!value) {
          return;
        }
        this.loadingControlPublication = true;
        this.$bvModal.show('modal-publication-control');

        try {
          const resp = await this.publishPublicationApi(id);
          this.$refs.table.refresh();

          this.loadingControlPublication = false;
        } catch (data) {
          this.loadingControlPublication = false;
          for (let error of data.data.errors.violations) {
            const message = error.title;
            this.errors.push(message);
          }
        }
      },
      publishPublicationApi(id) {
        return fetch(Routing.generate('api_user_publication_publish', {id}))
        .then(this.handleErrors)
        .then(resp => resp.json());
      },
      deleteItem(id) {
        return fetch(Routing.generate('api_user_publication_delete', {id}))
      },
      async handleErrors(response) {
        if (!response.ok) {
          const data = await response.json();
          return Promise.reject(data)
        }
        return response;
      }
    }
  }
</script>