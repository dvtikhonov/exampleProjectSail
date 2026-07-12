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


const sampleMenu = {
    restaurant_name: 'QA Bistro',
    categories: [
        {
            id: 1,
            name: 'Пицца',
            is_combo_available: true,
            dishes: [
                { id: 101, name: 'Маргарита', price: '450.00', is_available: true, image_url: null },
                { id: 102, name: 'Пепперони', price: '520.00', is_available: true, image_url: null },
            ],
        },
        {
            id: 2,
            name: 'Напитки',
            is_combo_available: false,
            dishes: [
                { id: 201, name: 'Кола', price: '120.00', is_available: true, image_url: null },
            ],
        },
    ],
};

const results = [];

function pass(name) {
    results.push({ name, ok: true });
    console.log(`PASS: ${name}`);
}

function fail(name, detail) {
    results.push({ name, ok: false, detail });
    console.log(`FAIL: ${name} — ${detail}`);
}

function assert(name, condition, detail = 'assertion failed') {
    if (condition) {
        pass(name);
    } else {
        fail(name, detail);
    }
}

// MenuHeader
const header = mount(MenuHeader, {
    props: { deliveryAddress: '', restaurantName: 'QA Bistro', ordersUnreadCount: 3 },
});

assert(
    'address placeholder when empty',
    header.text().includes('Укажите адрес доставки'),
);

await header.find('button').trigger('click');
assert('address click emits open-cart', header.emitted('open-cart')?.length === 1);

await header.find('button[type="button"].rounded-full').trigger('click');
assert('orders button emits open-orders', header.emitted('open-orders')?.length === 1);

assert('orders unread badge visible', header.text().includes('3'));

// MenuCategoryTabs
const tabs = mount(MenuCategoryTabs, {
    props: {
        categoryTabs: [
            { id: null, name: 'Все' },
            { id: 1, name: 'Пицца' },
            { id: 2, name: 'Напитки' },
        ],
        activeCategoryId: null,
        searchQuery: '',
    },
});

await tabs.find('button[aria-label="Поиск"]').trigger('click');
const searchInput = tabs.find('input[type="search"]');
await searchInput.setValue('кола');
assert('search input updates query', tabs.emitted('update:searchQuery')?.some((e) => e[0] === 'кола'));

const pizzaTab = tabs.findAll('button').find((b) => b.text() === 'Пицца');
await pizzaTab.trigger('click');
assert('category tab emits active id', tabs.emitted('update:activeCategoryId')?.some((e) => e[0] === 1));

// MenuDishCard
const card = mount(MenuDishCard, {
    props: {
        dish: {
            id: 101,
            name: 'Маргарита',
            price: '450.00',
            is_combo_available: true,
            category_id: 1,
        },
        addingDishId: null,
        addingComboRef: null,
        comboBuilderOpen: false,
        comboFirstDish: null,
        comboSecondDish: null,
    },
});

assert('price shows от prefix for combo category', card.text().includes('от 450.00'));

const priceButton = card.findAll('button').find((b) => b.text().includes('450.00'));
await priceButton.trigger('click');
assert('price click emits add-to-cart', card.emitted('add-to-cart')?.[0]?.[0]?.id === 101);

const comboLink = card.findAll('button').find((b) => b.text().includes('собрать комбо'));
await comboLink.trigger('click');
assert('combo link emits start-combo', card.emitted('start-combo')?.[0]?.[0]?.id === 101);

// MenuPage integration
const page = mount(MenuPage, {
    props: {
        menu: sampleMenu,
        deliveryAddress: 'ул. Тестовая, 1',
        loading: false,
        error: '',
        addingDishId: null,
        addingComboRef: null,
        cartItemCount: 2,
        cartTotal: '570.00',
        ordersUnreadCount: 1,
    },
});

assert('grid 2 columns in page', page.html().includes('grid-cols-2'));
assert('category tab Все present', page.text().includes('Все'));
assert('cart bottom panel visible', page.text().includes('Корзина · 2 позиций'));
assert('safe-area-top on header', page.html().includes('safe-area-top'));
assert('safe-area-bottom on cart', page.html().includes('safe-area-bottom'));

await page.findAll('button').find((b) => b.text().includes('собрать комбо')).trigger('click');
assert('combo sheet opens', page.text().includes('Собрать комбо'));

const failed = results.filter((r) => !r.ok);
console.log('---');
console.log(`Menu mount QA: ${results.length - failed.length} passed, ${failed.length} failed`);

if (failed.length > 0) {
    process.exit(1);
}
