<template>
  <b-step-item :step="2" label="Style" icon="vest-patches">
    <h3 class="subtitle mb-5" v-if="selectedType">{{ titles[selectedType].styles }}</h3>

    <div v-if="selectedStyles.length > 0" class="is-clearfix mb-3 align-bottom">
      Vous avez choisi {{ selectedStyles.length }} style(s).
    </div>

    <span v-for="style in firstStyles"
          class="box is-clickable is-inline-block mb-1 pl-4 pr-4 pt-2 pb-2 mr-1"
          :class="[selectedStyles.includes(style) ? 'has-background-info has-text-white' : 'has-background-info-light has-text-info']"
          @click="addSelectedStyle(style)"
    >
                            {{ style.name }}
                        </span>

    <div></div>
    <b-button v-if="!seeMoreStyle" size="sm" type="is-default" class="mt-2 mb-2"
              @click="seeMoreStyle = true">
      Voir plus de styles
    </b-button>
    <b-button v-else variant="primary" size="sm" class="mt-2 mb-2" @click="seeMoreStyle = false">
      Voir moins de styles
    </b-button>
    <div></div>
    <span v-for="style in restStyles"
          v-if="seeMoreStyle"
          class="box is-clickable is-inline-block mb-1 pl-4 pr-4 pt-2 pb-2 mr-1"
          :class="[selectedStyles.includes(style) ? 'has-background-info has-text-white' : 'has-background-info-light has-text-info']"
          @click="addSelectedStyle(style)"
    >
                    {{ style.name }}
                </span>
  </b-step-item>
</template>

<script>
import {mapGetters} from "vuex";

export default {
  props: ['titles'],
  data() {
    return {
      seeMoreStyle: false,
    }
  },
  computed: {
    ...mapGetters('styles', ['styles']),
    ...mapGetters('announceMusician', ['selectedStyles', 'selectedType']),
    firstStyles() {
      return [...this.styles].slice(0, 5);
    },
    restStyles() {
      return [...this.styles].slice(5);
    },
  },
  methods: {
    addSelectedStyle(style) {
      this.$store.dispatch('announceMusician/updateSelectedStyles', {style});
    },
    next() {
      this.$emit('next-step');
    }
  }
}
</script>