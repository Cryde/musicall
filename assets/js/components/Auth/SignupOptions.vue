<template>
  <div>
    <SocialSignInButtons
      :return-url="returnUrl"
      :google-label="$t('auth_modal.register_with_google')"
      :apple-label="$t('auth_modal.register_with_apple')"
      class="mb-4"
    />

    <!-- Secondary signup link. Both link texts start with a space, on top of ml-1, as they always have. -->
    <div class="text-center mb-4">
      <span class="text-surface-500 dark:text-surface-400 text-sm">{{ $t('auth_modal.no_social_account') }}</span>
      <router-link
        :to="routeReturningTo('app_register', returnUrl)"
        class="text-primary hover:text-primary-emphasis text-sm ml-1"
        @click="$emit('navigate')"
      >
        {{ ' ' }}{{ $t('auth_modal.register_with_email') }}
      </router-link>
    </div>

    <Divider />

    <!-- Login link -->
    <div class="text-center">
      <span class="text-surface-500 dark:text-surface-400 text-sm">{{ $t('auth_modal.has_account') }}</span>
      <router-link
        :to="routeReturningTo('app_login', returnUrl)"
        class="text-primary hover:text-primary-emphasis text-sm ml-1 font-medium"
        @click="$emit('navigate')"
      >
        {{ ' ' }}{{ $t('common.login') }}
      </router-link>
    </div>
  </div>
</template>

<script setup>
import Divider from 'primevue/divider'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { isSafeReturnUrl } from '../../utils/returnUrl.js'
import { returnPathOf, routeReturningTo } from '../../utils/unauthenticatedRedirect.js'
import SocialSignInButtons from './SocialSignInButtons.vue'

/**
 * Google and Apple first, then the other ways in, all bringing the visitor back to the page they are
 * on, or to `returnPath`. Relative, because the login page refuses an absolute return address and
 * dropped the visitor on the homepage after an email sign in (#1084).
 */
const props = defineProps({
  returnPath: { type: String, default: null }
})
defineEmits(['navigate'])

const route = useRoute()
// Checked here too: the Google and Apple links hand it to the server without going through the router.
const returnUrl = computed(() =>
  isSafeReturnUrl(props.returnPath) ? props.returnPath : returnPathOf(route)
)
</script>
