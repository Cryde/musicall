<template>
  <div>
    <b-dropdown aria-role="list" class="is-pulled-right">
      <b-button type="is-info" slot="trigger" slot-scope="{ active }">
        <span>Ajouter</span>
        <b-icon :icon="active ? 'chevron-up' : 'chevron-down'"></b-icon>
      </b-button>

      <b-dropdown-item aria-role="listitem" @click="$refs['modal-publication-add'].open()">
        <i class="far fa-edit"></i> une publication
      </b-dropdown-item>
      <b-dropdown-item aria-role="listitem" @click="$refs['modal-video-add'].open()">
        <i class="fas fa-video"></i> une video
      </b-dropdown-item>
    </b-dropdown>

    <h1 class="subtitle is-3">Mes publications</h1>

    <div v-if="total">
      Vous avez posté {{ total }} publications
    </div>

    <div class="columns mt-3">
      <div class="column is-3">
        <v-select :options="publicationCategories"
                  :value="filter.category_id"
                  @input="changeCategory"
                  placeholder="Catégories"
                  :reduce="item => item.id"
                  class="has-background-white"
                  label="title"></v-select>
      </div>
      <div class="column is-3">
        <v-select :options="status"
                  @input="changeStatus"
                  :value="filter.status"
                  placeholder="Statut"
                  class="has-background-white"
                  :reduce="item => item.id"
        ></v-select>
      </div>
    </div>

    <b-table
        :data="publications"

        paginated
        backend-pagination
        :total="total"
        :per-page="perPage"
        @page-change="onPageChange"
        pagination-position="both"

        backend-sorting
        :default-sort-direction="defaultSortOrder"
        :default-sort="[sortField, sortOrder]"
        @sort="onSort"
    >
      <template #empty>
        <div class="has-text-centered" v-if="!isBusy">
          Vous n'avez pas encore de publication
        </div>
      </template>

      <b-table-column field="title" label="Titre" sortable v-slot="props">
        {{ props.row.title }}
      </b-table-column>

      <b-table-column field="creation_datetime" label="Création" sortable v-slot="props">
        {{ props.row.creation_datetime | prettyDate }}
      </b-table-column>

      <b-table-column field="edition_datetime" label="Édition" sortable v-slot="props">
        <span v-if="props.row.edition_datetime">{{ props.row.edition_datetime | prettyDate }}</span>
        <span v-else></span>
      </b-table-column>

      <b-table-column field="status_label" label="Status" v-slot="props">
        <b-tag :type="tagStatusColor(props.row.status_id)">{{ props.row.status_label }}</b-tag>
      </b-table-column>

      <b-table-column label="Actions" v-slot="props">
        <b-field>
          <p class="control">
            <b-tooltip label="Publier la publication" type="is-black" v-if="props.row.status_id === 0">
              <b-button size="is-small" type="is-success is-light"
                        icon-left="paper-plane"
                        @click="publishPublication(props.row.id)">
              </b-button>
            </b-tooltip>
          </p>
          <p class="control">
            <b-tooltip label="Voir la publication" type="is-black">
              <b-button size="is-small" type="is-info is-light"
                        target="_blank" tag="router-link"
                        icon-left="eye"
                        :to="{ name: 'publication_show', params: { slug: props.row.slug }}">
              </b-button>
            </b-tooltip>
          </p>
          <p class="control">
            <b-tooltip label="Modifier la publication" type="is-black" v-if="props.row.status_id === 0">
              <b-button size="is-small" type="is-success is-light"
                        icon-left="edit" tag="router-link"
                        :to="{ name: 'user_publications_edit', params: { id: props.row.id }}">
              </b-button>
            </b-tooltip>
          </p>
          <p class="control">
            <b-tooltip label="Supprimer la publication" type="is-black" v-if="props.row.status_id === 0">
              <b-button size="is-small" type="is-danger is-light"
                        icon-left="trash-alt"
                        @click="showDeleteModal(props.row.id)">
              </b-button>
            </b-tooltip>
          </p>
        </b-field>
      </b-table-column>
    </b-table>

    <b-loading :active="isPublishing"/>
    <add-publication-modal ref="modal-publication-add"/>
    <add-video-modal ref="modal-video-add"/>
  </div>
</template>

<script>
import vSelect from 'vue-select';
import userPublicationApi from "../../../../api/userPublication";
import AddPublicationModal from '../add/modal/AddPublicationModal';
import AddVideoModal from '../add/video/AddVideoModal';
import {mapGetters} from 'vuex';
import {EVENT_PUBLICATION_CREATED} from "../../../../constants/events";

export default {
  components: {AddPublicationModal, AddVideoModal, vSelect},
  data() {
    return {
      publications: [],
      perPage: 0,
      total: null,
      isBusy: false,
      status: [{label: 'Brouillon', id: 0}, {label: 'Publié', id: 1}, {label: 'En validation', id: 2},],

      sortField: '',
      sortOrder: 'desc',
      defaultSortOrder: 'desc',

      filter: {category_id: null, status: null},
      page: 1,
      errors: [],
    }
  },
  computed: {
    ...mapGetters('security', ['isRoleAdmin']),
    ...mapGetters('publicationCategory', ['publicationCategories']),
    ...mapGetters('publicationEdit', ['isPublishing', 'errorsPublish']),
  },
  mounted() {
    this.$root.$on(EVENT_PUBLICATION_CREATED, () => {
      this.getPublications();
    });
    this.getPublications();
  },
  methods: {
    async getPublications() {
      this.isBusy = true;
      try {
        const resp = await userPublicationApi.getPublications(this.buildFilter());
        this.total = resp.meta.total;
        this.perPage = resp.meta.items_per_page;

        this.publications = resp.publications;
      } catch (e) {
        console.error(e);
        return []
      }

      this.isBusy = false;
    },
    onPageChange(page) {
      this.page = page
      this.getPublications()
    },
    onSort(field, order) {
      this.sortField = field
      this.sortOrder = order
      this.getPublications()
    },
    changeCategory(categoryId) {
      this.filter.category_id = categoryId;
      this.getPublications();
    },
    changeStatus(statusId) {
      this.filter.status = statusId;
      this.getPublications();
    },
    buildFilter() {
      return {
        "filter": this.filter,
        "sortBy": this.sortField,
        "sortDesc": this.sortOrder === 'desc',
        "currentPage": this.page,
      };
    },
    async showDeleteModal(id) {

      const {result, dialog} = await this.$buefy.dialog.confirm({
        message: 'Êtes vous sur ?',
        type: 'is-danger',
        confirmText: 'Oui',
        cancelText: 'Annuler',
      });

      dialog.close();

      if (!result) {
        return;
      }

      try {
        await userPublicationApi.deleteItem(id);
        this.getPublications();
      } catch (error) {
        console.error(error);
      }
    },
    async publishPublication(id) {

      const {result, dialog} = await this.$buefy.dialog.confirm({
        title: 'Êtes vous sur ?',
        message: 'Une fois mise en ligne vous ne pourrez plus modifier la publication.',
        confirmText: 'Oui',
        cancelText: 'Annuler'
      });

      dialog.close();

      if (!result) {
        return;
      }

      await this.$store.dispatch('publicationEdit/publish', id);

      if (!this.errorsPublish.length) {
        this.$buefy.dialog.alert({
          message: `Votre publication a été publiée !`,
          type: 'is-success',
          hasIcon: true
        })
        this.getPublications();
      } else {
        this.$buefy.dialog.alert({
          title: 'Erreur lors de la publication',
          message: `
                <b>Veuillez corriger ces erreurs avant de publier:</b> <br/>
                <ul>${this.errorsPublish.map(error => `<li>${error}</li>`).join('')}</ul>
            `,
          type: 'is-danger',
          hasIcon: true
        })
      }
    },
    tagStatusColor(statusId) {
      if (statusId === 1) {
        return 'is-success';
      }

      if (statusId === 2) {
        return 'is-warning';
      }

      return '';
    }
  }
}
</script>