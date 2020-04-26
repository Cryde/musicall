<template>
    <div>
        <b-dropdown id="dropdown-1" text="Ajouter" right variant="outline-success" class="float-right">
            <b-dropdown-item v-b-modal.modal-gallery-add><i class="far fa-images"></i> une galerie
            </b-dropdown-item>
        </b-dropdown>

        <h1>Mes galeries</h1>

        <div class="content-box mt-3">
            <b-table
                    id="galleries-table"
                    ref="galleries"
                    :busy.sync="isLoading"
                    show-empty
                    borderless
                    stacked="md"
                    :items="galleries"
                    :fields="fields"
            >
                <div slot="table-busy" class="text-center my-2">
                    <b-spinner class="align-middle"></b-spinner>
                </div>

                <template v-slot:cell(status)="data">
                    <span v-if="data.item.status === 0">En ligne</span>
                    <span v-if="data.item.status === 1">Brouillon</span>
                    <span v-if="data.item.status === 2">En validation</span>
                </template>

                <template v-slot:cell(actions)="data">
                    <b-button-group size="sm">
                        <b-button v-if="data.item.status === 1" variant="outline-success"
                                  v-b-tooltip.hover title="Modifier la galerie"
                                  :to="{ name: 'user_gallery_edit', params: { id: data.item.id }}"><i
                                class="far fa-edit"></i>
                        </b-button>

                        <b-button variant="outline-primary" v-b-tooltip.hover title="Voir la galerie"
                                  :to="{name: 'gallery_show', params: {slug: data.item.slug}}"
                                  target="_blank"><i
                                class="far fa-eye"></i></b-button>

                        <b-button v-if="data.item.status === 1" variant="outline-success"
                                  v-b-tooltip.hover title="Publier la galerie"
                                  @click="publish(data.item.id)">
                            <i class="far fa-paper-plane"></i>
                        </b-button>
                        <!--
                        <b-button v-if="data.item.status === 1" variant="outline-danger" v-b-tooltip.hover
                                  title="Supprimer la publication"
                                  @click="showDeleteModal(data.item.id)">
                            <i class="far fa-trash-alt"></i>
                        </b-button>-->
                    </b-button-group>
                </template>
            </b-table>
        </div>
        <errors-modal :errors="errors"/>
        <add-gallery-modal/>
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
        const value = await this.$bvModal.msgBoxConfirm('Une fois mise en ligne vous ne pourrez plus modifier la galerie.', {
          title: 'Êtes vous sur ?',
          okTitle: 'Oui',
          cancelTitle: 'Annuler',
          centered: true
        });

        if (!value) {
          return;
        }

        try {
          await this.$store.dispatch('userGalleries/publish', id);
        } catch (e) {
          this.errors = e.response.data.violations.map(violation => violation.title);
          this.$bvModal.show('modal-errors');
        }
      }
    }
  }
</script>