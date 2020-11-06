<template>
    <b-row>
        <b-col cols="12" v-if="isLoading" class="has-text-centered pt-5">
            <b-spinner/>
        </b-col>
        <b-col cols="12" v-else>
            <h1 class="mb-5">
                <b-link :to="{name: 'admin_dashboard'}">Admin</b-link>
                / <b-link :to="{name: 'admin_artists_list'}">Liste des artistes</b-link>
                / Edition de {{ name }}
            </h1>

            <b-alert v-if="errors.length" variant="danger" show>
                <span v-for="error in errors" class="is-block">{{ error }}</span>
            </b-alert>

            <b-img v-if="cover" :src="cover" fluid class="mb-2"/>

            <b-button variant="primary" v-if="cover" v-b-modal.modal-upload-wiki-artist-cover>
                <i class="far fa-image"></i> Changer d'image de cover
            </b-button>
            <b-button variant="primary" v-else v-b-modal.modal-upload-wiki-artist-cover>
                <i class="far fa-image"></i> Ajouter une image de cover
            </b-button>

            <b-form-group description="La biographie du groupe" class="mt-4">
                <b-form-textarea v-model="biography"></b-form-textarea>
            </b-form-group>

            <b-form-group description="Les membres du groupe (un par ligne)">
                <b-form-textarea v-model="members"></b-form-textarea>
            </b-form-group>

            <b-form-group description="Le nom du label">
                <b-form-input v-model="labelName"></b-form-input>
            </b-form-group>

            <b-row>
                <b-col cols="6">
                    <v-select :options="countryOptions" v-model="selectedOptionsCountry"
                              placeholder="Pays"
                              style="background: white;"></v-select>
                </b-col>
            </b-row>

            <b-row class="mt-3">
                <b-col>
                    <v-select :options="networkOptions" v-model="selectedOptionsNetwork"
                              placeholder="Ajouter un lien d'un réseau"
                              style="background: white;"></v-select>
                </b-col>
                <b-col>
                    <b-button variant="primary" size="sm" :disabled="!selectedOptionsNetwork" @click="addLink">Add
                        link
                    </b-button>
                </b-col>
            </b-row>

            <b-row v-for="social in socials" class="mt-1" :key="social.tId">
                <b-col cols="8">

                    <b-input-group>
                        <template v-slot:prepend>
                            <b-input-group-text><i class="fas fa-link" v-if="social.type === 0"></i>
                                <i class="fab fa-fw fa-twitter" v-if="social.type === 1"></i>
                                <i class="fab fa-fw fa-instagram" v-if="social.type === 2"></i>
                                <i class="fab fa-fw fa-facebook" v-if="social.type === 3"></i>
                                <i class="fab fa-fw fa-youtube" v-if="social.type === 4"></i></b-input-group-text>
                        </template>
                        <b-input v-model="social.url" type="url"></b-input>
                    </b-input-group>
                </b-col>
                <b-col>
                    <b-button variant="danger" size="sm" class="mt-1" @click="removeLink(social)"><i
                            class="fas fa-times"/></b-button>
                </b-col>
            </b-row>

            <b-button variant="primary" @click="save" class="is-pulled-right mt-3">
                <b-spinner small v-if="isSubmitted"></b-spinner>
                <i class="far fa-save" v-else></i>
                Sauver
            </b-button>
        </b-col>

        <b-col cols="12" class="text-right">
            <em class="text-info" style="font-size: 0.8em ">Lors d'un changement de cover, l'image est sauvée (sans avoir à appuyer sur le bouton "Sauver")</em></b-col>
        <edit-cover-modal v-if="id" :artist-id="id"/>
    </b-row>
</template>


<script>
  import vSelect from "vue-select";
  import artistApi from "../../../api/admin/artist";
  import countryApi from "../../../api/attribute/country";
  import {uniqueId} from 'lodash';
  import EditCoverModal from "./modals/EditCoverModal";
  import {EVENT_ADMIN_UPDATE_ARTIST_COVER} from '../../../constants/events';

  export default {
    components: {EditCoverModal, vSelect},
    data() {
      return {
        errors: [],
        isLoading: true,
        isSubmitted: false,
        id: null,
        name: '',
        biography: '',
        members: '',
        labelName: '',
        cover: '',
        socials: [],
        networkOptions: [
          {type: 0, 'label': 'Website'},
          {type: 1, 'label': 'Twitter'},
          {type: 2, 'label': 'Instagram'},
          {type: 3, 'label': 'Facebook'},
          {type: 4, 'label': 'YouTube'},
        ],
        countryOptions: [],
        selectedOptionsNetwork: null,
        selectedOptionsCountry: null,
      }
    },
    async mounted() {
      this.isLoading = true;
      this.countryOptions = await countryApi.getCountries();
      this.id = this.$route.params.id;
      try {
        const {name, biography, members, label_name, socials, cover, country_code} = await artistApi.getArtist({id: this.id});
        this.name = name;
        this.biography = biography;
        this.members = members;
        this.labelName = label_name;
        this.socials = socials;
        this.cover = cover;
            this.selectedOptionsCountry = this.countryOptions.find(item => item.key === country_code);
        this.isLoading = false;
      } catch (e) {
        this.errors = e.response.data.violations.map(violation => violation.title);
      }

      this.$root.$on(EVENT_ADMIN_UPDATE_ARTIST_COVER, (cover) => {
        this.cover = cover;
      });
    },
    methods: {
      addLink() {
        this.socials.push({type: this.selectedOptionsNetwork.type, url: '', tId: uniqueId('tid')});
        this.selectedOptionsNetwork = null;
      },
      removeLink(removedItem) {
        this.socials = this.socials.filter(item => item.tId !== removedItem.tId);
      },
      async save() {
        this.isSubmitted = true;
        try {
          await artistApi.edit({
            id: this.id,
            biography: this.biography,
            members: this.members,
            labelName: this.labelName,
            socials: this.socials.filter(item => item.url.trim() !== ''),
            countryCode: this.selectedOptionsCountry ? this.selectedOptionsCountry.key : '',
          });
          this.isSubmitted = false;
        } catch (e) {
          this.errors = e.response.data.violations.map(violation => violation.title);
        }
      }
    }
  }
</script>