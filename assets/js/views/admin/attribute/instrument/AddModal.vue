<template>
    <b-modal id="modal-instrument-add" title="Ajouter un instrument de musique" ref="modal-instrument-add" size="sm">

        <b-alert v-if="errors.length" show variant="danger">
            <span v-for="error in errors">{{error}}</span>
        </b-alert>

        <b-form class="mt-1">
            <b-form-group
                    label="Nom de l'instrument"
                    label-for="title"
            >
                <b-input v-model="value" id="title" placeholder="ex: Batterie"></b-input>
            </b-form-group>
            <b-form-group
                    label="Nom du musicien"
                    label-for="title"
            >
                <b-input v-model="valueMusician" id="title" placeholder="ex: Batteur/Batteuse"></b-input>
            </b-form-group>
        </b-form>

        <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
            <b-button variant="default" @click="cancel()">Annuler</b-button>

            <b-button variant="outline-success" @click="save" :disabled="value.trim().length === 0">
                <b-spinner small v-if="isSubmitted"></b-spinner>
                <i class="far fa-save" v-else></i>
                Sauver
            </b-button>
        </template>
    </b-modal>
</template>


<script>
  import attributeApi from "../../../../api/admin/attribute";
  import {EVENT_ADMIN_ADD_INSTRUMENT} from '../../../../constants/events';

  export default {
    data() {
      return {
        value: '',
        valueMusician: '',
        isSubmitted: false,
        errors: [],
      }
    },
    methods: {
      async save() {
        this.isSubmitted = true;
        try {
          await attributeApi.addInstrument({name: this.value, musicianName: this.valueMusician});
          this.value = '';
          this.valueMusician = '';
          this.$bvModal.hide('modal-instrument-add');
          this.$root.$emit(EVENT_ADMIN_ADD_INSTRUMENT);
        } catch (e) {
          this.errors = e.response.data.violations.map(violation => violation.title);
        }
        this.isSubmitted = false;
      }
    }
  }
</script>