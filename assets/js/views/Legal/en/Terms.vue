<template>
  <div class="py-10 md:py-20">
    <div class="max-w-4xl mx-auto bg-surface-0 dark:bg-surface-900 p-6 md:p-12 rounded-3xl">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0 mb-8">
        Terms of use
      </h1>

      <p class="text-surface-600 dark:text-surface-300 mb-6">
        Last updated: {{ lastUpdate }}
      </p>

      <div class="prose dark:prose-invert max-w-none space-y-8">
        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            1. Purpose and publisher
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            MusicAll is a community platform for musicians and music lovers. It lets you publish articles, tutorials and lessons, take part in a forum, post ads, browse directories of musicians and teachers, and manage a band workspace (Band Space).
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            The site is published by Jérémy Tonneau. Contact:
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            2. Acceptance of the terms
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            Using MusicAll means accepting these terms. If you do not accept them, do not use the site.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            3. Registration and account
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            You need an account to access some features. Accounts are restricted to people aged 15 or over. You can register with your email address or with your Google account.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            By creating an account, you agree to:
          </p>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li>Provide accurate information</li>
            <li>Keep your login details confidential and not share them</li>
            <li>Tell us without delay about any unauthorised use of your account</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            You are responsible for the activity carried out from your account.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            4. Content published in the public area
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            By publishing content on MusicAll (articles, comments, forum posts, ads, images), you guarantee that you are its author or that you hold the necessary rights.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            You keep ownership of your content and grant MusicAll a non-exclusive, free licence to display and distribute it as part of the operation of the site.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            If your account is deleted, your public content remains visible but is attached to an anonymous profile. Your public, musician and teacher profiles are no longer accessible.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            5. Band Space
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            <strong>5.1 Nature of the service.</strong> Band Space is a free, private workspace made available to bands. It lets them manage a calendar, notes, tasks, setlists, files and forecast financial tracking.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            <strong>5.2 Roles and visibility.</strong> A space is created by a user, who becomes its administrator. Any content added to a space is accessible to all its members. By joining a space, you accept that the content you add to it is visible to the other members.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            <strong>5.3 Content you add.</strong> You remain the owner of the content you add and guarantee that you hold the necessary rights to the files you upload.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            <strong>5.4 Leaving a space and deleting your account.</strong> When you leave a space, the content you added remains available there to the other members, so that the band's work remains consistent. Your recurring personal finance entries are deactivated and their future scheduled occurrences are deleted. If you delete your account, you leave each of your spaces: this content also stays in place, attached to an anonymous identifier. If you were the only administrator of a space, its longest-standing member becomes administrator; if you were its only member, the space is scheduled for deletion under the conditions of article 5.5.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            <strong>5.5 Deleting a space.</strong> Only an administrator of the space can delete it. Deletion is scheduled, not immediate: the space remains accessible for 30 days, so that each member can retrieve their files, and any administrator can cancel the deletion during that period. Members are informed as soon as a deletion is scheduled or cancelled. At the end of the 30 days, the space and all its content, files included, are permanently deleted and can no longer be recovered. A file deleted individually follows the same 30-day period before it is permanently erased.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            <strong>5.6 Storage.</strong> Each space has a storage quota of 5 GB. This quota may change. Band Space is not a general purpose file hosting service: its use must remain linked to the band's activity.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            <strong>5.7 Finances module.</strong> The Finances module is a tracking and forecasting tool for internal use. It is not an accounting tool, an invoicing tool or a tax filing tool, and it does not replace the legal obligations of the band or its members. The amounts entered are the sole responsibility of the users. Entries marked as personal remain visible to all members of the space: they are only left out of the band's totals and can only be edited by their author.
          </p>
          <!-- Like the French 5.8, never promise a "backup" here: see the comment in ../Terms.vue. -->
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            <strong>5.8 Protection and retention of your files.</strong> Files added to a Band Space are stored on a service with a versioning mechanism: when a file is replaced by a new version, the previous version remains recoverable for 30 days. This mechanism is not an independent backup and does not protect against every loss scenario. We recommend that you keep your own copies of important documents.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            6. Prohibited conduct
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            You must not:
          </p>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li>publish content that is illegal, defamatory, discriminatory, hateful, violent or pornographic;</li>
            <li>harass, threaten or intimidate other users;</li>
            <li>impersonate another person;</li>
            <li>post spam or unsolicited promotional content;</li>
            <li>attempt to undermine the security or the operation of the site;</li>
            <li>collect other users' personal data without their consent;</li>
            <li>infringe the intellectual property rights of third parties;</li>
            <li>use Band Space to store files unrelated to the activity of a band;</li>
            <li>circumvent the quotas or technical limits of the service.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            7. Moderation, reporting and appeals
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            <strong>7.1 Reporting.</strong> In accordance with Regulation (EU) 2022/2065, you can report any content or account that you consider illegal or contrary to these terms, with the "{{ t('shared.report.button') }}" button on that content or on the profile concerned, or by writing to
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a> with the URL concerned, the nature of the problem and the reasons for your report. You will receive an acknowledgement of receipt and will be told what action has been taken.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            <strong>7.2 How content is moderated.</strong> Moderation is carried out by people, after a report or when reviewing the publications and galleries submitted for approval. The site also applies automatic technical measures that limit the amount of content that can be created over a given period, and filters the HTML code of published content to remove dangerous elements. These technical measures do not lead to content being removed or an account being restricted: no moderation decision is taken on a purely automated basis.
          </p>
          <!-- 7.3 is only partly implemented, as the comment in ../Terms.vue explains. -->
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            <strong>7.3 Statement of reasons.</strong> When content is removed or made inaccessible, or when an account is suspended or deleted for a moderation reason, the user concerned is informed of the reason for the decision, the ground relied on (a legal provision or a clause of these terms), the facts taken into account and the means of redress available to them.
          </p>
          <p id="recours" class="text-surface-600 dark:text-surface-300 mt-4">
            <strong>7.4 Appeals.</strong> You can contest a moderation decision by writing to
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>. You remain free to bring the matter before the competent courts.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            8. Availability of the service
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            MusicAll is provided free of charge, as is and as available, with no service level commitment. The service may be interrupted temporarily, in particular for maintenance.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            Features may change, be modified or be removed. If a service holding user data is permanently discontinued, reasonable notice will be given so that the data can be retrieved.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            9. Role as an intermediary
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            MusicAll puts users in touch with each other (musician ads, lessons, bands) but is not a party to the relationships, agreements or transactions that result. We do not check the identity, qualifications or intentions of registered users. It is up to you to be careful in your exchanges and when you meet people.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            10. Intellectual property
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            The MusicAll site, its design, its source code, its logos and its editorial content are protected by copyright. Any unauthorised reproduction or use is prohibited.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            11. Liability
          </h2>
          <p class="text-surface-600 dark:text-surface-300 mb-4">
            To the extent permitted by law:
          </p>
          <ul class="list-disc list-inside text-surface-600 dark:text-surface-300 space-y-2">
            <li>MusicAll is not liable for content published by users, under the conditions laid down in Regulation (EU) 2022/2065;</li>
            <li>MusicAll is not liable for relationships formed between users;</li>
            <li>MusicAll is not liable for indirect loss resulting from the use of the service;</li>
            <li>MusicAll takes reasonable measures to preserve data but does not guarantee that it will never be lost or altered.</li>
          </ul>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            These limitations do not apply in the event of wilful misconduct or gross negligence, nor to damage resulting from death or personal injury. They do not affect the mandatory rights that consumers enjoy under the applicable law.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            12. Changes to the terms
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            These terms may be changed. In the event of a substantial change, registered users are informed before it takes effect. Continuing to use the service after that date constitutes acceptance.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            13. Termination
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            You can delete your account at any time from your profile settings.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            We may suspend or delete an account in the event of a breach of these terms, under the conditions set out in article 7.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            On deletion, your personal data is anonymised immediately: username, email address, password, profile picture, cover image, biography, location, social links and linked Google account. Your public content and your Band Space content stay in place, attached to an anonymous profile, as described in articles 4 and 5.4.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            14. Governing law and disputes
          </h2>
          <!-- The language sentence is English only (#1195): a doubtful term is read in the consumer's favour (CDE art. VI.37, Civil Code art. 5.66), so French cannot prevail against them. -->
          <p class="text-surface-600 dark:text-surface-300">
            The service is published and operated from Belgium. These terms are governed by Belgian law, without prejudice to the mandatory consumer protection provisions applicable in the user's country of habitual residence. These terms were written in French and this English version is a translation. If the two versions differ, the French version prevails, except where the English version is more favourable to you as a consumer.
          </p>
          <p class="text-surface-600 dark:text-surface-300 mt-4">
            In the event of a dispute, an amicable solution will be sought first, by writing to
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>. Failing that, the competent courts will be determined in accordance with the applicable rules, and a user who is a consumer keeps the right to bring proceedings before the courts of their place of residence.
          </p>
        </section>

        <section>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
            15. Contact
          </h2>
          <p class="text-surface-600 dark:text-surface-300">
            <a href="mailto:contact@musicall.com" class="text-primary hover:text-primary-emphasis">contact@musicall.com</a>
          </p>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
// The English version of ../Terms.vue: change both together, i18n/legalPages.test.js checks that
// they keep the same markup. The anchor ids stay French, since links such as /terms#recours use them.
import { useTitle } from '@vueuse/core'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

useTitle('Terms of use - MusicAll')

const lastUpdate = '7 October 2026'
</script>
