import { compileScript, parse } from '@vue/compiler-sfc';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';
import * as Vue from 'vue';

// 编译实际导航组件，用 Vue 渲染器验证滚动和菜单交互，不依赖浏览器或新增测试依赖。
const filename = fileURLToPath(
    new URL('../../resources/js/components/PublicHeader.vue', import.meta.url),
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
        if (name === '@/routes') return { logout: () => '/logout' };
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
const PublicHeader = componentModule.exports.default;

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

function mount(
    t,
    {
        variant = 'overlay',
        scrollY = 0,
        light = '/white.png',
        dark = '/dark.png',
    } = {},
) {
    const listeners = new Map();
    const previousWindow = global.window;
    const previousImage = global.Image;
    global.window = {
        scrollY,
        addEventListener: (event, callback) => listeners.set(event, callback),
        removeEventListener: (event) => listeners.delete(event),
    };
    global.Image = class {};
    const root = node('root');
    const app = renderer.createApp(PublicHeader, {
        variant,
        navigation: [],
        site: {
            name: '站点标识',
            logo_url: dark,
            logo_light_url: light,
            search_placeholder: '搜索',
        },
    });
    app.mount(root);
    t.after(() => {
        app.unmount();
        global.window = previousWindow;
        global.Image = previousImage;
    });
    return {
        root,
        logos: () =>
            all(
                root,
                (element) =>
                    element.tag === 'img' && element.props.alt === '站点标识',
            ),
        async scroll(value) {
            global.window.scrollY = value;
            listeners.get('scroll')();
            await Vue.nextTick();
        },
        async menu() {
            const button = all(
                root,
                (element) =>
                    element.tag === 'button' &&
                    ['打开导航', '关闭导航'].includes(
                        element.props['aria-label'],
                    ),
            )[0];
            button.props.onClick();
            await Vue.nextTick();
        },
    };
}

function expectLogos(header, source) {
    assert.equal(header.logos().length, 2, '桌面和移动导航均显示标识');
    for (const logo of header.logos()) assert.equal(logo.props.src, source);
}

test('首页透明导航使用白色标识，滚动后改为深色，返回顶部恢复', async (t) => {
    const header = mount(t);
    expectLogos(header, '/white.png');
    await header.scroll(24);
    expectLogos(header, '/white.png');
    await header.scroll(25);
    expectLogos(header, '/dark.png');
    assert.match(
        all(header.root, (element) => element.tag === 'header')[0].props.class,
        /bg-\[#fffffe\]/,
    );
    await header.scroll(0);
    expectLogos(header, '/white.png');
});

test('移动菜单变成白底时同步切换标识，关闭后恢复', async (t) => {
    const header = mount(t);
    await header.menu();
    expectLogos(header, '/dark.png');
    await header.menu();
    expectLogos(header, '/white.png');
    await header.scroll(100);
    await header.menu();
    await header.menu();
    expectLogos(header, '/dark.png');
});

test('普通白底页面及恢复在滚动位置的首页使用深色标识', (t) => {
    expectLogos(mount(t, { variant: 'solid' }), '/dark.png');
});

test('首页初始化时读取已有滚动位置', async (t) => {
    const header = mount(t, { scrollY: 100 });
    await Vue.nextTick();
    expectLogos(header, '/dark.png');
});

test('未配置浅色标识时继续兼容已有单标识', async (t) => {
    const header = mount(t, { light: null });
    expectLogos(header, '/dark.png');
    await header.scroll(100);
    expectLogos(header, '/dark.png');
});

test('未配置任何图片时保留默认文字标识', (t) => {
    const header = mount(t, { light: null, dark: null });
    assert.equal(header.logos().length, 0);
    assert.equal(
        all(
            header.root,
            (element) => element.tag === 'span' && element.text.trim() === 'M',
        ).length,
        2,
    );
});
