<script setup>
import backgroundUrl from './assets/cafe-obed.png';
import { reachMessengerGoal } from './metrika';

const offerFacts = [
    {
        title: 'Куда',
        text: 'Домой и в офис по Новокузнецку.',
    },
    {
        title: 'Когда',
        text: 'С понедельника по четверг заказ до 10:00 привозим на следующий день утром. В пятницу принимаем заказ на понедельник.',
    },
    {
        title: 'Доставка',
        text: '100 ₽. От 1 000 ₽ — бесплатно.',
    },
];
</script>

<template>
  <main
    class="min-h-screen bg-cover bg-center bg-no-repeat bg-fixed text-stone-900"
    :style="{ backgroundImage: `url(${backgroundUrl})` }"
  >
    <article class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-8">
      <header class="rounded-[28px] bg-white px-5 py-5 shadow-xl shadow-stone-900/15">
          <p class="text-sm font-medium text-stone-500">Новокузнецк, ул. Кутузова, 39</p>
          <h1 class="mt-1 text-[28px] font-bold leading-8">Кафе «Обедов»</h1>
          <p class="mt-4 text-lg font-bold leading-6">Завтраки и обеды с доставкой</p>
          <p class="mt-2 text-[15px] leading-6 text-stone-600">
            Меню и цены на день пришлём в чат — состав каждый день разный.
          </p>

          <dl class="mt-4 space-y-3">
            <div
              v-for="fact in offerFacts"
              :key="fact.title"
              class="rounded-2xl bg-stone-50 px-4 py-3"
            >
              <dt class="text-xs font-bold uppercase tracking-wide text-amber-800">{{ fact.title }}</dt>
              <dd class="mt-1 text-[15px] leading-6 text-stone-800">{{ fact.text }}</dd>
            </div>
          </dl>

          <div class="mt-5 flex flex-col gap-2.5">
            <a
              href="/messenger/go/telegram"
              class="flex h-14 w-full items-center justify-center gap-3 rounded-2xl bg-[#2AABEE] px-4 text-base font-bold text-white shadow-lg shadow-sky-500/30 transition hover:bg-[#229ed9] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2AABEE]"
              @click="reachMessengerGoal('telegram')"
            >
              <svg class="h-7 w-7 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  fill="#fff"
                  d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"
                />
              </svg>
              Написать в Telegram
            </a>
            <a
              href="/messenger/go/max"
              class="flex h-14 w-full items-center justify-center gap-3 rounded-2xl bg-gradient-to-r from-[#4cf] via-[#53e] to-[#93d] px-4 text-base font-bold text-white shadow-lg shadow-violet-500/30 transition hover:brightness-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#53e]"
              @click="reachMessengerGoal('max')"
            >
              <svg class="h-7 w-7 shrink-0" viewBox="0 0 1000 1000" aria-hidden="true">
                <path
                  fill="#fff"
                  fill-rule="evenodd"
                  d="M508.211 878.328c-75.007 0-109.864-10.95-170.453-54.75-38.325 49.275-159.686 87.783-164.979 21.9 0-49.456-10.95-91.248-23.36-136.873-14.782-56.21-31.572-118.807-31.572-209.508 0-216.626 177.754-379.597 388.357-379.597 210.785 0 375.947 171.001 375.947 381.604.707 207.346-166.595 376.118-373.94 377.224m3.103-571.585c-102.564-5.292-182.499 65.7-200.201 177.024-14.6 92.162 11.315 204.398 33.397 210.238 10.585 2.555 37.23-18.98 53.837-35.587a189.8 189.8 0 0 0 92.71 33.032c106.273 5.112 197.08-75.794 204.215-181.95 4.154-106.382-77.67-196.486-183.958-202.574Z"
                  clip-rule="evenodd"
                />
              </svg>
              Написать в MAX
            </a>
          </div>
      </header>
    </article>
  </main>
</template>
