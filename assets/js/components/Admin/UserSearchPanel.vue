<template>
  <Panel header="Rechercher un utilisateur">
    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-2">
        <label for="admin-user-search" class="font-medium text-surface-700 dark:text-surface-300">
          Nom d'utilisateur ou email
        </label>
        <div class="flex gap-2">
          <InputText
            id="admin-user-search"
            v-model="term"
            class="flex-1"
            placeholder="Ex : jean_michel, jean@email.com"
            aria-describedby="admin-user-search-help"
            @keyup.enter="handleSearch"
          />
          <Button
            label="Rechercher"
            icon="pi pi-search"
            :loading="isLoading"
            :disabled="!isTermLongEnough || isLoading"
            @click="handleSearch"
          />
        </div>
        <small id="admin-user-search-help" class="text-surface-500 dark:text-surface-400">
          {{ USER_SEARCH_MIN_LENGTH }} caractères minimum. Les comptes fermés restent visibles.
        </small>
      </div>

      <Message v-if="errorMessage" severity="error" :closable="false">{{ errorMessage }}</Message>

      <DataTable v-if="hasSearched" :value="users" stripedRows class="text-sm">
        <Column field="username" header="Utilisateur">
          <template #body="{ data }">
            <RouterLink
              :to="{ name: 'admin_users_show', params: { id: data.id } }"
              class="font-medium underline"
            >
              {{ data.username }}
            </RouterLink>
            <Tag v-if="data.is_deleted" value="Compte fermé" severity="danger" class="ml-2" />
          </template>
        </Column>
        <Column field="email" header="Email" class="hidden md:table-cell" />
        <Column header="Rôles">
          <template #body="{ data }">
            <span v-if="!data.roles.length" class="text-surface-500 dark:text-surface-400">Aucun</span>
            <Tag
              v-for="role in data.roles"
              :key="role"
              :value="roleLabel(role)"
              severity="secondary"
              class="mr-1"
            />
          </template>
        </Column>
        <Column field="creation_datetime" header="Inscription">
          <template #body="{ data }">{{ formatDate(data.creation_datetime) }}</template>
        </Column>
        <template #empty>
          <div class="text-center py-6 text-surface-500 dark:text-surface-400">
            Aucun compte ne correspond à « {{ lastTerm }} ».
          </div>
        </template>
      </DataTable>

      <Paginator
        v-if="totalItems > ROWS_PER_PAGE"
        :rows="ROWS_PER_PAGE"
        :totalRecords="totalItems"
        :first="(page - 1) * ROWS_PER_PAGE"
        @page="handlePageChange"
      />
    </div>
  </Panel>
</template>

<script setup>
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Paginator from 'primevue/paginator'
import Panel from 'primevue/panel'
import Tag from 'primevue/tag'
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import adminUserApi from '../../api/admin/user.js'
import {
  GRANTABLE_ROLES,
  USER_SEARCH_MIN_LENGTH,
  USER_SEARCH_ROWS_PER_PAGE
} from '../../constants/adminUser.js'
import { formatDate } from '../../utils/date.js'

const ROWS_PER_PAGE = USER_SEARCH_ROWS_PER_PAGE

const term = ref('')
const lastTerm = ref('')
const users = ref([])
const totalItems = ref(0)
const page = ref(1)
const isLoading = ref(false)
const hasSearched = ref(false)
const errorMessage = ref('')

const isTermLongEnough = computed(() => term.value.trim().length >= USER_SEARCH_MIN_LENGTH)

function roleLabel(role) {
  return GRANTABLE_ROLES.find((candidate) => candidate.value === role)?.label ?? role
}

async function load() {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const data = await adminUserApi.search({ search: lastTerm.value, page: page.value })
    users.value = data.member
    totalItems.value = data.totalItems
    hasSearched.value = true
  } catch (e) {
    errorMessage.value = e?.response?.data?.detail || 'La recherche a échoué.'
  } finally {
    isLoading.value = false
  }
}

function handleSearch() {
  if (!isTermLongEnough.value) return
  // Pinned when the search runs rather than read live, so paging through results is not thrown off
  // by someone still typing in the box.
  lastTerm.value = term.value.trim()
  page.value = 1
  load()
}

function handlePageChange(event) {
  page.value = event.page + 1
  load()
}
</script>
