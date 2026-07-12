/**
 * MenuPage mount QA.
 */
import { spawnSync } from "node:child_process";
import { dirname, resolve } from "path";
import { fileURLToPath } from "url";

const __self = fileURLToPath(import.meta.url);
if (!process.env.__MENU_QA_VITENODE__) {
    const r = spawnSync("npx", ["vite-node", __self], {
        stdio: "inherit",
        cwd: resolve(dirname(__self), ".."),
        env: { ...process.env, __MENU_QA_VITENODE__: "1" },
    });
    process.exit(r.status ?? 1);
}

import { Window } from "happy-dom";

const happyWindow = new Window({ url: "http://localhost/" });
globalThis.window = happyWindow;
globalThis.document = happyWindow.document;
globalThis.HTMLElement = happyWindow.HTMLElement;
globalThis.customElements = happyWindow.customElements;
globalThis.SVGElement = happyWindow.SVGElement;
globalThis.Element = happyWindow.Element;
globalThis.Node = happyWindow.Node;
globalThis.getComputedStyle = happyWindow.getComputedStyle.bind(happyWindow);

const { mount, config } = await import("@vue/test-utils");
config.global.document = happyWindow.document;

const { default: MenuPage } = await import("../resources/js/max-app/pages/MenuPage.vue");
const { default: MenuHeader } = await import("../resources/js/max-app/components/menu/MenuHeader.vue");
const { default: MenuCategoryTabs } = await import("../resources/js/max-app/components/menu/MenuCategoryTabs.vue");
const { default: MenuDishCard } = await import("../resources/js/max-app/components/menu/MenuDishCard.vue");

