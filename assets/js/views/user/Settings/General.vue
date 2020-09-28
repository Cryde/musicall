<template>
  <div v-if="isLoading" class="text-center p-5">
    <b-spinner/>
  </div>
  <b-card v-else title="Paramètres généraux du compte">
    <b-row class="mt-5 mb-3">
      <b-col xl="5">Nom d'utilisateur</b-col>
      <b-col xl="7">{{ user.username }}</b-col>
    </b-row>
    <b-row class="mt-4 mb-4">
      <b-col xl="5">Adresse email</b-col>
      <b-col xl="7">{{ user.email }}</b-col>
    </b-row>
  </b-card>
</template>

<script>
import userApi from "../../../api/user";

export default {
  data() {
    return {
      isLoading: true,
      user: null,
    }
  },
  async mounted() {
    this.isLoading = true;
    this.user = await userApi.me();
    this.isLoading = false;
  }
}
</script>