<template>

    <div>
        <b-dropdown id="dropdown-1" text="Ajouter" right variant="outline-success" class="float-right">
            <b-dropdown-item v-b-modal.modal-publication-add><i class="far fa-edit"></i> une publication
            </b-dropdown-item>
            <b-dropdown-item v-b-modal.modal-video-add><i class="fas fa-video"></i> une video</b-dropdown-item>
        </b-dropdown>

        <h1>Mes publications</h1>

        <div v-if="total">
            Vous avez posté {{ total }} publications
        </div>

        <b-pagination
                v-model="currentPage"
                :total-rows="total"
                :per-page="perPage"
                align="right"
        ></b-pagination>

        <div class="content-box mt-3">
            <b-table
                    id="publication-table"
                    ref="table"
                    :busy.sync="isBusy"
                    :sort-by.sync="sortBy"
                    :sort-desc.sync="sortDesc"
                    :per-page="perPage"
                    :current-page="currentPage"
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
                    <b-button-group size="sm">
                        <b-button v-if="data.item.status_id === 0" variant="outline-success" v-b-tooltip.hover
                                  title="Publier la publication"
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
                        <b-button v-if="data.item.status_id === 0" variant="outline-danger" v-b-tooltip.hover
                                  title="Supprimer la publication"
                                  @click="showDeleteModal(data.item.id)">
                            <i class="far fa-trash-alt"></i>
                        </b-button>
                    </b-button-group>
                </template>
            </b-table>
        </div>
        <add-publication-modal/>
        <add-video-modal/>

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
  import userPublicationApi from "../../../../api/userPublication";
  import AddPublicationModal from './AddPublicationModal';
  import AddVideoModal from './AddVideoModal';
  import {mapGetters} from 'vuex';

  export default {
    components: {AddPublicationModal, AddVideoModal},
    data() {
      return {
        currentPage: 1,
        perPage: 0,
        total: null,
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
    computed: {
      ...mapGetters('security', ['isRoleAdmin'])
    },
    mounted() {
      this.$root.$on('reload-table', () => {
        this.$refs.table.refresh();
      });
    },
    methods: {
      async publicationProvider(ctx) {
        try {
          const resp = await userPublicationApi.getPublications(ctx);
          this.total = resp.meta.total;
          this.perPage = resp.meta.items_per_page;
          return resp.publications;
        } catch (e) {
          console.error(e);
          return []
        }
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
          await userPublicationApi.deleteItem(id);
          this.$refs.table.refresh();
        } catch (error) {
          console.error(error);
        }
      },
      async publishPublication(id) {
        this.errors = [];
        const value = await this.$bvModal.msgBoxConfirm('Une fois mise en ligne vous ne pourrez plus modifier la publication.', {
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
          await userPublicationApi.publishPublicationApi(id);
          this.$refs.table.refresh();

          this.loadingControlPublication = false;
        } catch (e) {
          const data = e.response.data;
          this.loadingControlPublication = false;
          for (let error of data.violations) {
            this.errors.push(error.title);
          }
        }
      },
    }
  }
</script>