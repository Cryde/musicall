<template>
  <nav class="breadcrumb is-right" aria-label="breadcrumbs">
    <ul>
      <li>
        <router-link :to="root.to">{{ root.label }}</router-link>
      </li>
      <li v-if="level1">
        <router-link :to="level1.to">{{ level1.label }}</router-link>
      </li>
      <li v-if="level2">
        <router-link :to="level2.to">{{ level2.label }}</router-link>
      </li>
      <li class="is-active"><a href="#" aria-current="page">{{ current.label }}</a></li>
    </ul>
  </nav>
</template>

<script>
export default {
  props: {
    root: {
      type: Object,
      required: true
    },
    current: {
      type: Object,
      required: true
    },
    level1: {
      type: Object,
      required: false,
      default: null,
    },
    level2: {
      type: Object,
      required: false,
      default: null,
    },
  }
}
</script>