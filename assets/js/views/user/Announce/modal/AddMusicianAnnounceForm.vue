<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Ajouter une annonce</p>
      <button type="button" class="delete" @click="$emit('close')"/>
    </header>

    <section class="modal-card-body" style="min-height: 300px;">
      <b-loading :active="isSending"/>
      <div class="column" v-if="isSuccess">
        <div class="has-text-centered p-5">
          <i class="fas fa-check fa-5x has-text-success  mb-3"></i><br/>
          Votre annonce est créée.<br/>
          <div v-if="!isFromAnnounce">
            Vous pouvez
            <span @click="goToMyAnnounceList()" class="has-text-info is-clickable">retrouver vos annonces ici</span>
          </div>
        </div>
      </div>
      <b-steps v-else type="is-info" :has-navigation="false" icon-pack="fa" v-model="currentStep">
        <search-type-step @next-step="nextStep"/>
        <instrument-choice-step @next-step="nextStep" :titles="titles"/>
        <style-choice-step @next-step="nextStep" :titles="titles"/>
        <localisation-step @next-step="nextStep" :titles="titles"/>
        <note-step @next-step="nextStep"/>
        <summary-step v-if="isValid"/>
      </b-steps>
    </section>
    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')" v-if="isSuccess">Fermer</b-button>
      <b-button type="is-light" @click="$emit('close')" v-else>Annuler</b-button>

      <b-button class="is-pulled-right" :disabled="isSending" v-if="displayPrevious && !isSuccess"
                icon-left="caret-left"
                @click="prevStep">Précédent
      </b-button>
      <b-button class="is-pulled-right" :disabled="isSending" v-if="displayNext && !isSuccess" icon-right="caret-right"
                @click="nextStep">Suivant
      </b-button>
      <b-button class="is-pulled-right" :loading="isSending" type="is-success" icon-left="paper-plane" @click="save"
                v-if="isValid && haveSeenAllStep && !isSuccess">Sauver
      </b-button>
    </footer>
  </div>
</template>

<script>
import SearchTypeStep from "./step/SearchTypeStep";
import InstrumentChoiceStep from "./step/InstrumentChoiceStep";
import StyleChoiceStep from "./step/StyleChoiceStep";
import LocalisationStep from "./step/LocalisationStep";
import NoteStep from "./step/NoteStep";
import SummaryStep from "./step/SummaryStep";
import {mapGetters} from "vuex";
import {EVENT_ANNOUNCE_MUSICIAN_CREATED} from "../../../../constants/events";

export default {
  props: {
    isFromAnnounce: {
      type: Boolean,
      default: false,
    }
  },
  components: {SummaryStep, NoteStep, LocalisationStep, StyleChoiceStep, InstrumentChoiceStep, SearchTypeStep},
  data() {
    return {
      currentStep: 0,
      haveSeenAllStep: false,
    };
  },
  computed: {
    ...mapGetters('announceMusician', ['isSending', 'isSuccess', 'isValid', 'isStepStylesValid', 'isStepLocationValid']),
    displayNext() {
      if (this.currentStep === 5) {
        return false;
      }

      if (this.currentStep === 2 && this.isStepStylesValid) {
        return true;
      }

      if (this.currentStep === 3 && this.isStepLocationValid) {
        return true;
      }

      if (this.isValid) {
        return true;
      }

      return false;
    },
    displayPrevious() {
      return !!this.isValid && this.currentStep > 0;
    },
    titles() {
      return {
        musician: {
          instrument: 'Quel instrument cherchez vous ?',
          styles: 'Quels styles cherchez vous ?',
        },
        band: {
          instrument: 'Quel instrument jouez vous ?',
          styles: 'Quels styles jouez vous ?',
        }
      };
    },
  },
  async mounted() {
    this.$store.dispatch('styles/loadStyles');
    this.$store.dispatch('instruments/loadInstruments');
  },
  methods: {
    prevStep() {
      this.currentStep -= 1;
    },
    nextStep() {
      this.currentStep += 1;
      if (this.currentStep > 4) {
        this.haveSeenAllStep = true;
      }
    },
    async save() {
      await this.$store.dispatch('announceMusician/send');
      this.$root.$emit(EVENT_ANNOUNCE_MUSICIAN_CREATED);
    },
    goToMyAnnounceList() {
      this.$router.push({name: "user_musician_announce"});
      this.$emit('close')
    }
  },
  destroyed() {
    this.$store.dispatch('announceMusician/reset');
  }
}
</script>

<style>
.choice-button {
  display: inline-block;
  width: 70%;
}
</style>