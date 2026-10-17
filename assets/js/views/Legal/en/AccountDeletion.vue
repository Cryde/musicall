<template>
  <div class="py-10 md:py-20">
    <div class="max-w-4xl mx-auto bg-surface-0 dark:bg-surface-900 p-6 md:p-12 rounded-3xl">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0 mb-8">
        Delete your MusicAll account
      </h1>

      <p class="text-surface-600 dark:text-surface-300 mb-6">
        Last updated: {{ lastUpdate }}
      </p>

      <!--
        Google Play's "Delete account URL" (#1130), like ../AccountDeletion.vue: it must agree with
        DeleteAccountProcedure and the French page. The mobile app is French only (Cryde/musicall-app
        supports fr alone), so its labels are quoted in French; the site's come from the catalogue.
      -->
      <div class="prose dark:prose-invert max-w-none space-y-8">
        <section>
          <p class="text-surface-600 dark:text-surface-300">
            This page explains how to delete your MusicAll account, whether you use it from the MusicAll mobile
            app or from the www.musicall.com website. The app and the website are published by Jérémy Tonneau
            and share the same account: deleting it from one deletes it everywhere.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            1. How to delete your account
          </h2>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li>
              <strong>In the app</strong>: open <strong>Profil</strong> (Profile), then <strong>Réglages</strong>
              (Settings), and tap "Supprimer mon compte" (Delete my account).
            </li>
            <li>
              <strong>On the website</strong>: log in, open your
              <router-link :to="{ name: 'app_user_settings' }" class="text-primary hover:text-primary-emphasis">settings</router-link>
              and click "{{ t('profile.settings.general.delete_account.title') }}", in the
              "{{ t('profile.settings.general.danger_zone') }}" section.
            </li>
            <li>
              <strong>If you can no longer log in</strong>: write to
              <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>
              from your account's email address, stating your username. We may ask you to confirm that the
              account belongs to you, and we handle the request within one month.
            </li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Deletion is immediate and cannot be undone. If you are a member of a Band Space, remember to retrieve
            the files you want to keep beforehand.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            2. What is deleted
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            As soon as you confirm, your personal data is deleted or anonymised:
          </p>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li>your username, email address, password and linked Google account;</li>
            <li>your profile picture, cover image, display name, biography, location and links to your social networks;</li>
            <li>your musician and teacher profiles, which are no longer accessible, and your ads, which are no longer shown;</li>
            <li>your notification preferences and the users you had blocked;</li>
            <li>your sessions: you are logged out on all your devices, and your phone no longer receives push notifications.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            3. What is kept
          </h2>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li>
              Your public content (publications, lessons, galleries, comments, forum posts) and the private messages
              you sent remain visible, attached to an anonymous profile shown as "{{ t('shared.user.deleted') }}".
            </li>
            <li>
              You leave each of your Band Spaces. The content you added there remains available to the band,
              attached to the same anonymous profile. If you were the only administrator of a space, its
              longest-standing member becomes administrator. If you were its only member, the space is scheduled
              for deletion and permanently erased, with all its content, after 30 days.
            </li>
            <li>
              The anonymised record of your account is kept, without any data that could identify you, so that
              public content and Band Spaces remain consistent.
            </li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            4. For how long
          </h2>
          <ul class="list-none text-surface-600 dark:text-surface-300 space-y-2">
            <li><strong>Your account data:</strong> deleted or anonymised immediately</li>
            <li><strong>A Band Space of which you were the only member:</strong> 30 days, then permanent erasure</li>
            <li><strong>Encrypted backups:</strong> your data may remain in them for up to 1 year, before being overwritten</li>
            <li><strong>Server logs:</strong> 52 days for access logs, 90 days at most for system logs, 10 days for application logs</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Details of the processing and of your rights are in the
            <router-link :to="{ name: 'app_privacy' }" class="text-primary hover:text-primary-emphasis">privacy policy</router-link>
            and the
            <router-link :to="{ name: 'app_terms' }" class="text-primary hover:text-primary-emphasis">terms of use</router-link>.
          </p>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
// The English version of ../AccountDeletion.vue: change both together, i18n/legalPages.test.js
// checks that they keep the same markup.
import { useTitle } from '@vueuse/core'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

useTitle('Delete your account - MusicAll')

const lastUpdate = '7 October 2026'
</script>
