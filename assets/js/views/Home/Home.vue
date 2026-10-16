<template>
  <!-- Nothing until the login is known: either view first would flash into the other. -->
  <div v-if="userSecurityStore.isAuthenticatedLoading" class="min-h-[60vh]" aria-busy="true" />
  <HomeMember v-else-if="userSecurityStore.isAuthenticated" />
  <HomeVisitor v-else />
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { useUserSecurityStore } from '../../store/user/security.js'
import HomeMember from './HomeMember.vue'
import HomeVisitor from './HomeVisitor.vue'

/** A visitor gets the pitch (#1074), a member what moves for them (#1078). */
const { t } = useI18n()
useTitle(() => t('home.title'))

const userSecurityStore = useUserSecurityStore()
</script>
