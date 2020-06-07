<template>
    <b-modal id="modal-artist-add" title="Ajouter un artiste" ref="modal-artist-add">

        <b-alert v-if="errors.length" show variant="danger">
            <span v-for="error in errors">{{error}}</span>
        </b-alert>

        <b-form class="mt-1" v-if="!isSent">
            <b-form-group
                    label="Nom de l'artiste / groupe"
                    label-for="title"
            >
                <b-input v-model="value" id="title" placeholder="Ex: Counterpart"></b-input>
            </b-form-group>
        </b-form>
        <b-row v-else>
            <b-col cols="12" class="text-center">
                <i class="fas fa-check fa-2x"></i><br/>
                Artiste enregistré
            </b-col>
        </b-row>

        <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
            <b-button variant="default" @click="cancel()">Annuler</b-button>

            <b-button v-if="isSent" @click="addNew" variant="primary">
                <i class="fas fa-plus"></i>
                Ajouter un nouveau
            </b-button>

            <b-button v-if="isSent" variant="success">
                <i class="far fa-edit"></i>
                Editer
            </b-button>

            <b-button v-if="!isSent" variant="outline-success" @click="save" :disabled="value.trim().length === 0">
                <b-spinner small v-if="isSubmitted"></b-spinner>
                <i class="far fa-save" v-else></i>
                Sauver
            </b-button>
        </template>
    </b-modal>
</template>


<script>
  import artistApi from "../../../../api/admin/artist";
  import {EVENT_ADMIN_ADD_ARTIST} from '../../../../constants/events';

  export default {
    data() {
      return {
        value: '',
        isSubmitted: false,
        isSent: false,
        errors: [],
      }
    },
    mounted() {
      this.$refs['modal-artist-add'].$on('hide', () => {
        this.reset();
      });
    },
    methods: {
      addNew() {
        this.reset();
      },
      async save() {
        this.isSubmitted = true;
        try {
          await artistApi.addArtist({name: this.value});
          this.value = '';
          this.errors = [];
          this.isSent = true;
          this.$root.$emit(EVENT_ADMIN_ADD_ARTIST);
        } catch (e) {
          this.errors = e.response.data.violations.map(violation => violation.title);
        }
        this.isSubmitted = false;
      },
      reset() {
        this.value = '';
        this.isSubmitted = false;
        this.isSent = false;
        this.errors = [];
      }
    }
  }
</script>