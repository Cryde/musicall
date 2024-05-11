<template>
    <b-row>
        <b-col :cols="12">
            <b-button variant="primary" v-b-modal.modal-style-add class="is-pulled-right">Ajouter un style de musique
            </b-button>
            <h1><b-link :to="{name: 'admin_dashboard'}">Admin</b-link> / Liste des styles de musique</h1>
        </b-col>

        <b-col cols="12" v-if="styles.length">
            <b-list-group class="mt-4 mb-4">
                <b-list-group-item v-for="style in styles" :key="style.id">
                    {{ style.name }}
                </b-list-group-item>
            </b-list-group>
        </b-col>

        <add-modal/>
    </b-row>
</template>

<script>
  import styleApi from "../../../../api/attribute/style";
  import AddModal from './AddModal.vue';
  import {EVENT_ADMIN_ADD_STYLE} from '../../../../constants/events';

  export default {
    components: {AddModal},
    data() {
      return {
        styles: [],
        loading: false,
      }
    },
    mounted() {
      this.load();

      this.$root.$on(EVENT_ADMIN_ADD_STYLE, () => {
        this.load();
      });
    },
    methods: {
      async load() {
        this.styles = await styleApi.listStyle();
      }
    }
  }
</script>