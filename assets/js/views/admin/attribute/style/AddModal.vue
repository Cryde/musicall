<template>
    <b-modal id="modal-style-add" title="Ajouter un style de musique" ref="modal-style-add" size="sm">

        <b-alert v-if="errors.length" show variant="danger">
            <span v-for="error in errors">{{error}}</span>
        </b-alert>

        <b-form class="mt-1">
            <b-form-group
                    label="Nom du style"
                    label-for="title"
            >
                <b-input v-model="value" id="title"></b-input>
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
  import {EVENT_ADMIN_ADD_STYLE} from '../../../../constants/events';

  export default {
    data() {
      return {
        value: '',
        isSubmitted: false,
        errors: [],
      }
    },
    methods: {
      async save() {
        this.isSubmitted = true;
        try {
          await attributeApi.addStyle({name: this.value});
          this.value = '';
          this.$bvModal.hide('modal-style-add');
          this.$root.$emit(EVENT_ADMIN_ADD_STYLE);
        } catch (e) {
          this.errors = e.response.data.violations.map(violation => violation.title);
          console.log(this.errors);
        }
        this.isSubmitted = false;
      }
    }
  }
</script>