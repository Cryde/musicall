<template>
    <div class="position-fixed w-100 alert-development pl-2 pr-2 pb-2">
        <b-alert v-model="show" dismissible class="mb-0"
                 @dismissed="closeAlert"
        >
            <i class="fas fa-exclamation-circle"></i> MusicAll est cours de <strong>re-développement</strong>.
            <router-link :to="{ name: 'todo' }">Pour voir l'avancée c'est ici</router-link>
        </b-alert>
    </div>
</template>

<script>
  export default {
    data() {
      return {
        show: false
      }
    },
    mounted() {
      this.show = localStorage.getItem('show_alert') || true;
    },
    methods: {
      closeAlert() {
        localStorage.setItem('show_alert', '0');
      }
    }
  }
</script>