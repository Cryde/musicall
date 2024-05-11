<template>
  <div class="box is-size-7">
    <template v-if="isTypeBand()">
      {{ authorUsername }} est un <strong>{{ instrumentName.toLocaleLowerCase() }}</strong> et cherche un groupe jouant du
      <strong>{{
          styles.map(style => style.name.toLocaleLowerCase()).join(', ')
        }}</strong>
      dans les alentours de {{ locationName }}
    </template>
    <template v-if="isTypeMusician()">
      {{ authorUsername }} cherche pour son groupe un <strong>{{ instrumentName.toLocaleLowerCase() }}</strong> jouant du
      <strong>{{
          styles.map(style => style.name.toLocaleLowerCase()).join(', ')
        }}</strong> dans les alentours de {{ locationName }}
    </template>
  </div>
</template>
<script setup>
</script>

<script>
import {TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN} from "../../constants/types";

export default {
  props: {
    type: {
      type: Number,
      required: true,
    },
    authorUsername: {
      type: String,
      required: true,
    },
    instrumentName: {
      type: String,
      required: true,
    },
    styles: {
      type: Array,
      required: true
    },
    locationName: {
      type: String,
      required: true
    },
  },
  methods: {
    isTypeBand() {
      return this.type === TYPES_ANNOUNCE_BAND;
    },
    isTypeMusician() {
      return this.type === TYPES_ANNOUNCE_MUSICIAN;
    }
  }
}
</script>