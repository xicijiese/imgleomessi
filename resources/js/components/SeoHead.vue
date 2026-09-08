<script setup lang="ts">
import type { SeoPayload } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    seo: SeoPayload;
}>();
</script>

<template>
    <Head :title="seo.title">
        <meta
            head-key="description"
            name="description"
            :content="seo.description"
        />
        <meta
            v-if="seo.robots"
            head-key="robots"
            name="robots"
            :content="seo.robots"
        />
        <link head-key="canonical" rel="canonical" :href="seo.canonical_url" />

        <meta head-key="og:title" property="og:title" :content="seo.og.title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="seo.og.description"
        />
        <meta head-key="og:type" property="og:type" :content="seo.og.type" />
        <meta head-key="og:url" property="og:url" :content="seo.og.url" />
        <meta
            v-if="seo.og.image"
            head-key="og:image"
            property="og:image"
            :content="seo.og.image"
        />

        <meta
            head-key="twitter:card"
            name="twitter:card"
            :content="seo.twitter.card"
        />
        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="seo.twitter.title"
        />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="seo.twitter.description"
        />
        <meta
            v-if="seo.twitter.image"
            head-key="twitter:image"
            name="twitter:image"
            :content="seo.twitter.image"
        />
    </Head>
</template>
