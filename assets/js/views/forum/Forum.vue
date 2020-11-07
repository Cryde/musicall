<template>
  <div>
    <breadcrumb
        :root="{to: {name: 'home'}, label: 'Home'}"
        :current="{label: 'Forum'}"
    />

    <h1 class="subtitle is-3">Forum</h1>

    <b-message>
      Les forums seront bientôt de retour !
    </b-message>
  </div>
</template>
<script>
import Breadcrumb from "../../components/global/Breadcrumb";

export default {
  components: {Breadcrumb}
}
</script>