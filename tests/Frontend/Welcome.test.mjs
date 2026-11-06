import { compileScript, parse } from '@vue/compiler-sfc';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';
import * as Vue from 'vue';

// 渲染真实首页，验证视觉整改后仍完整保留配置、模块和切换行为。
const filename = fileURLToPath(
    new URL(
        '../../resources/js/components/../pages/Welcome.vue',
        import.meta.url,
    ),
);
const loadModule = createRequire(import.meta.url);
const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename });
const script = compileScript(descriptor, {
    id: 'public-header-test',
    inlineTemplate: true,
});
const compiled = ts.transpileModule(script.content, {
    compilerOptions: {
        module: ts.ModuleKind.CommonJS,
        target: ts.ScriptTarget.ES2022,
    },
}).outputText;
const componentModule = { exports: {} };
new Function('require', 'module', 'exports', compiled)(
    (name) => {
        if (name === 'gsap')
            return {
                gsap: {
                    matchMedia: () => ({ add() {}, revert() {} }),
                    fromTo: () => ({ revert() {} }),
                },
            };
        if (name.startsWith('@/components/'))
            return {
                default: {
                    setup:
                        (_, { slots }) =>
                        () =>
                            Vue.h('div', slots.trigger?.({ open() {} })),
                },
            };
        if (name === '@inertiajs/vue3')
            return {
                Link: {
                    setup:
                        (_, { attrs, slots }) =>
                        () =>
                            Vue.h('a', attrs, slots.default?.()),
                },
                router: { visit() {}, post() {} },
                usePage: () => ({ props: { auth: { user: null } } }),
            };
        return loadModule(name);
    },
    componentModule,
    componentModule.exports,
);
const Welcome = componentModule.exports.default;

function node(tag, text = '') {
    return {
        tag,
        tagName: tag.toUpperCase(),
        text,
        props: {},
        children: [],
        parent: null,
        addEventListener() {},
        removeEventListener() {},
    };
}

function detach(child) {
    if (!child.parent) return;
    const siblings = child.parent.children;
    siblings.splice(siblings.indexOf(child), 1);
    child.parent = null;
}

const renderer = Vue.createRenderer({
    createElement: node,
    createText: (text) => node('#text', text),
    createComment: (text) => node('#comment', text),
    setText: (element, text) => {
        element.text = text;
    },
    setElementText: (element, text) => {
        element.text = text;
        element.children = [];
    },
    patchProp: (element, key, _, value) => {
        element.props[key] = value;
    },
    insert(child, parent, anchor) {
        detach(child);
        const position = anchor ? parent.children.indexOf(anchor) : -1;
        parent.children.splice(
            position < 0 ? parent.children.length : position,
            0,
            child,
        );
        child.parent = parent;
    },
    remove: detach,
    parentNode: (element) => element.parent,
    nextSibling: (element) =>
        element.parent?.children[
            element.parent.children.indexOf(element) + 1
        ] ?? null,
});

function all(root, predicate) {
    return [
        root,
        ...root.children.flatMap((child) => all(child, predicate)),
    ].filter(predicate);
}

function mount(t, options = {}) {
    const previousWindow = global.window;
    let interval;
    let intervalDelay;
    let cleared = false;
    const listeners = new Map();
    global.window = {
        scrollY: 0,
        matchMedia: () => ({ matches: options.reduced ?? true }),
        addEventListener: (event, callback) => listeners.set(event, callback),
        removeEventListener: (event) => listeners.delete(event),
        setInterval: (callback, delay) => {
            interval = callback;
            intervalDelay = delay;
            return 7;
        },
        clearInterval: () => {
            cleared = true;
        },
        scrollTo() {},
    };
    const item = (id) => ({
        id,
        type: 'album',
        title: `相册${id}`,
        url: `/albums/${id}`,
        image_url: `/photo-${id}.webp`,
        alt: `照片${id}`,
    });
    const home = {
        site: { name: '档案库', logo_url: null, search_placeholder: '搜索' },
        navigation: [],
        seo: {},
        hero_slides: [
            {
                title: '配置头图一',
                subtitle: '配置说明',
                button_label: '原始按钮',
                button_url: 'https://example.com/original',
                desktop_image_url: '/desktop.webp',
                mobile_image_url: '/mobile.webp',
            },
            {
                title: '配置头图二',
                subtitle: null,
                button_label: null,
                button_url: null,
                desktop_image_url: null,
                mobile_image_url: '/second.webp',
            },
        ],
        category_module: {
            enabled: true,
            title: '原始模块名称',
            more_url: '/albums?original=1',
            tabs: [
                { label: '全部', items: [item('a'), item('b')] },
                { label: '另一分类', items: [item('c')] },
                { label: '空分类', items: [] },
            ],
        },
        latest_photos: {
            enabled: true,
            title: '最新配置',
            display_count: 17,
            items: Array.from({ length: 17 }, (_, i) => ({
                ...item(`p${i}`),
                url: `/photos/p${i}`,
            })),
        },
        topic_module: {
            enabled: true,
            title: '专题配置',
            items: [
                {
                    title: '真实专题',
                    url: '/topics/original',
                    cover_image_url: '/topic.webp',
                },
            ],
        },
        ...options.home,
    };
    const root = node('root');
    const app = renderer.createApp(Welcome, { home, canRegister: true });
    let mounted = true;
    const unmount = () => {
        if (mounted) {
            app.unmount();
            mounted = false;
        }
    };
    app.mount(root);
    t.after(() => {
        unmount();
        global.window = previousWindow;
    });
    return {
        root,
        unmount,
        interval: () => interval?.(),
        intervalDelay: () => intervalDelay,
        cleared: () => cleared,
        links: () => all(root, (e) => e.tag === 'a'),
        async click(label) {
            const button = all(
                root,
                (e) =>
                    e.tag === 'button' &&
                    (e.text === label || e.props['aria-label'] === label),
            )[0];
            assert.ok(button, label);
            button.props.onClick();
            await Vue.nextTick();
        },
    };
}

test('完整保留后台配置的图片数量、模块标题及目标链接', (t) => {
    const page = mount(t);
    assert.equal(
        page.links().filter((e) => e.props.href?.startsWith('/photos/p'))
            .length,
        17,
    );
    for (const href of [
        'https://example.com/original',
        '/albums?original=1',
        '/topics/original',
        '/albums',
    ])
        assert.ok(page.links().some((e) => e.props.href === href));
    assert.ok(
        all(page.root, (e) => e.tag === 'h2' && e.text === '原始模块名称')
            .length,
    );
    assert.equal(
        all(page.root, (e) => e.tag === 'source')[0].props.srcset,
        '/mobile.webp',
    );
});

test('分类切换保留对应内容、选中状态及空分类提示', async (t) => {
    const page = mount(t);
    await page.click('另一分类');
    assert.ok(page.links().some((e) => e.props.href === '/albums/c'));
    assert.ok(!page.links().some((e) => e.props.href === '/albums/a'));
    assert.equal(
        all(
            page.root,
            (e) => e.props.role === 'tab' && e.text === '另一分类',
        )[0].props['aria-selected'],
        true,
    );
    await page.click('空分类');
    assert.ok(all(page.root, (e) => e.text.includes('当前分类还没有')).length);
});

test('手动轮播保留各页文案、图片和可选按钮规则', async (t) => {
    const page = mount(t);
    await page.click('切换到第 2 张头图');
    assert.equal(all(page.root, (e) => e.tag === 'h1')[0].text, '配置头图二');
    assert.ok(
        !page
            .links()
            .some((e) => e.props.href === 'https://example.com/original'),
    );
    assert.ok(
        all(page.root, (e) => e.tag === 'img' && e.props.src === '/second.webp')
            .length,
    );
});

test('自动轮播保留6500毫秒节奏，离开页面清理定时器', async (t) => {
    const page = mount(t, { reduced: false });
    assert.equal(page.intervalDelay(), 6500);
    page.interval();
    await Vue.nextTick();
    assert.equal(all(page.root, (e) => e.tag === 'h1')[0].text, '配置头图二');
    page.unmount();
    assert.equal(page.cleared(), true);
});

test('关闭模块时不渲染内容，减少动态效果时不启动自动轮播', (t) => {
    const page = mount(t, {
        home: {
            category_module: { enabled: false, tabs: [] },
            latest_photos: { enabled: false, items: [] },
            topic_module: { enabled: false, items: [] },
        },
    });
    assert.equal(all(page.root, (e) => e.tag === 'h2').length, 0);
    assert.equal(page.intervalDelay(), undefined);
});
