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
                              v-b-tooltip.hover title="Modifier la publication"
                              :to="{ name: 'user_gallery_edit', params: { id: data.item.id }}"><i
                            class="far fa-edit"></i>
                    </b-button>
                    <!--
                    <b-button v-if="data.item.status === 0" variant="outline-success" v-b-tooltip.hover
                              title="Publier la publication"
                              @click="publishPublication(data.item.id)">
                        <i class="far fa-paper-plane"></i>
                    </b-button>
                    <b-button variant="outline-primary" v-b-tooltip.hover title="Voir la publication"
                              :to="{ name: 'publication_show', params: { slug: data.item.slug }}"
                              target="_blank"><i
                            class="far fa-eye"></i></b-button>
                    <b-button v-if="data.item.status === 0" variant="outline-success"
                              v-b-tooltip.hover title="Modifier la publication"
                              :to="{ name: 'user_publications_edit', params: { id: data.item.id }}"><i
                            class="far fa-edit"></i>
                    </b-button>
                    <b-button v-if="data.item.status === 0" variant="outline-danger" v-b-tooltip.hover
                              title="Supprimer la publication"
                              @click="showDeleteModal(data.item.id)">
                        <i class="far fa-trash-alt"></i>
                    </b-button>-->
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
        fields: ['title', 'status', 'actions'],
      }
    },
    computed: {
      ...mapGetters('userGalleries', ['isLoading', 'galleries'])
    },
    mounted() {
      this.$store.dispatch('userGalleries/load');
    }
  }
</script>