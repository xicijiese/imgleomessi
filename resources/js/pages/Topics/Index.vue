<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import { useArchiveBrowserMotion } from '@/composables/useArchiveBrowserMotion';
import type { SeoPayload } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ImageOff, Images } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import '../../../css/archive-browser.css';
import './Topics.css';

interface NavigationItem {
    label: string;
    url: string;
}

interface TopicCard {
    title: string;
    url: string;
    cover_image_url: string | null;
}

interface TopicIndexPayload {
    site: {
        name: string;
        logo_url: string | null;
        search_placeholder: string;
    };
    navigation: NavigationItem[];
    seo: SeoPayload;
    topics: {
        data: TopicCard[];
    };
}

const props = defineProps<{
    topicIndex: TopicIndexPayload;
}>();

const imageFailures = ref<Record<string, boolean>>({});

const hasTopics = computed(() => props.topicIndex.topics.data.length > 0);

const root = ref<HTMLElement | null>(null);
useArchiveBrowserMotion(root);
</script>
<template>
    <SeoHead :seo="topicIndex.seo" />
    <main ref="root" class="archive-browser archive-topics">
        <PublicHeader
            :navigation="topicIndex.navigation"
            :site="topicIndex.site"
            theme="afa"
        />
        <div class="browser-container">
            <div class="browser-heading topic-list-heading">
                <div>
                    <h1>专题</h1>
                    <p>
                        按世界杯、金球奖、生涯阶段和经典组合整理梅西影像线索。
                    </p>
                </div>
                <span>{{ topicIndex.topics.data.length }} 个公开专题</span>
            </div>
            <div v-if="hasTopics" class="topic-grid">
                <Link
                    v-for="topic in topicIndex.topics.data"
                    :key="topic.url"
                    :href="topic.url"
                    class="topic-tile"
                    :aria-label="`查看专题：${topic.title}`"
                    ><img
                        v-if="
                            topic.cover_image_url && !imageFailures[topic.url]
                        "
                        :src="topic.cover_image_url"
                        :alt="topic.title"
                        loading="lazy"
                        @error="imageFailures[topic.url] = true"
                    />
                    <div
                        v-else
                        class="topic-image-error"
                        role="img"
                        :aria-label="topic.title + '，封面暂不可用'"
                    >
                        <ImageOff :size="32" aria-hidden="true" />
                    </div>
                    <span class="topic-mask" aria-hidden="true"></span>
                    <div class="topic-caption">
                        <h2>{{ topic.title }}</h2>
                        <span class="topic-arrow" aria-hidden="true">↗</span>
                    </div></Link
                >
            </div>
            <section v-else class="browser-empty-state">
                <Images :size="36" aria-hidden="true" />
                <h2>还没有可展示的公开专题</h2>
                <p>后台在首页配置中添加并启用专题后，这里会展示专题入口。</p>
                <div>
                    <Link href="/albums" class="browser-primary">查看相册</Link
                    ><Link href="/photos" class="browser-outline"
                        >浏览图库</Link
                    >
                </div>
            </section>
        </div>
    </main>
</template>
