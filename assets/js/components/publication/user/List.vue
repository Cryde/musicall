<template>

    <div>
        <DropdownPublicationCategory/>
        <h1>Mes publications</h1>

        <div class="content-box mt-3">
            <b-table
                    ref="table"
                    :busy.sync="isBusy"
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
                        <b-button variant="outline-danger" @click="showDeleteModal" :data-id="data.item.id"><i
                                class="far fa-trash-alt"></i></b-button>
                    </b-button-group>
                </template>
            </b-table>
        </div>
    </div>
</template>

<script>
  import DropdownPublicationCategory from './DropdownPublicationCategory'

  export default {
    components: {DropdownPublicationCategory},
    data() {
      return {
        isBusy: false,
        fields: [
          {key: 'title', label: 'Titre', sortable: true},
          {key: 'creation_datetime', label: 'Création', sortable: true},
          {key: 'edition_datetime', label: 'Mis à jour', sortable: true},
          {key: 'actions', sortable: false}
        ],
      }
    },
    methods: {
      publicationProvider(ctx) {
        return fetch(Routing.generate('api_user_publication_list'))
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