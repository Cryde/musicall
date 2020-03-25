<template>
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
            </template>

            <template v-slot:cell(actions)="data">
                <b-button-group size="sm">
                    <b-button v-if="data.item.status === 1" variant="outline-success"
                              v-b-tooltip.hover title="Modifier la galerie"
                              :to="{ name: 'user_gallery_edit', params: { id: data.item.id }}"><i
                            class="far fa-edit"></i>
                    </b-button>

                    <b-button variant="outline-primary" v-b-tooltip.hover title="Voir la galerie"
                              target="_blank"><i
                            class="far fa-eye"></i></b-button>

                    <b-button v-if="data.item.status === 1" variant="outline-success"
                              v-b-tooltip.hover title="Publier la galerie"
                              @click="publish(data.item.id)">
                        <i class="far fa-paper-plane"></i>
                    </b-button>
                    <!--
                    <b-button v-if="data.item.status === 0" variant="outline-danger" v-b-tooltip.hover
                              title="Supprimer la publication"
                              @click="showDeleteModal(data.item.id)">
                        <i class="far fa-trash-alt"></i>
                    </b-button>-->
                </b-button-group>
            </template>
        </b-table>
        <errors-modal :errors="errors"/>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';
  import ErrorsModal from "./ErrorsModal";

  export default {
    components: {ErrorsModal},
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
          this.errors = e.violations.map(violation => violation.title);
          this.$bvModal.show('modal-errors');
        }
      }
    }
  }
</script>