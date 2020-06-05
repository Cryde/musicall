<template>
    <b-row>
        <b-col :cols="12">
            <b-button variant="primary" v-b-modal.modal-instrument-add class="float-right">Ajouter un style de musique
            </b-button>
            <h1>
                <b-link :to="{name: 'admin_dashboard'}">Admin</b-link>
                / Liste des instruments de musique
            </h1>
        </b-col>

        <b-col cols="12" v-if="instruments.length">
            <b-list-group class="mt-4 mb-4">
                <b-list-group-item v-for="instrument in instruments" :key="instrument.id">
                    {{ instrument.name }} - {{ instrument.musician_name }}
                </b-list-group-item>
            </b-list-group>
        </b-col>

        <add-modal/>
    </b-row>
</template>

<script>
  import instrumentApi from "../../../../api/attribute/instrument";
  import AddModal from './AddModal';
  import {EVENT_ADMIN_ADD_INSTRUMENT} from '../../../../constants/events';

  export default {
    components: {AddModal},
    data() {
      return {
        instruments: [],
        loading: false,
      }
    },
    mounted() {
      this.load();

      this.$root.$on(EVENT_ADMIN_ADD_INSTRUMENT, () => {
        this.load();
      });
    },
    methods: {
      async load() {
        this.instruments = await instrumentApi.listInstrument();
      }
    }
  }
</script>