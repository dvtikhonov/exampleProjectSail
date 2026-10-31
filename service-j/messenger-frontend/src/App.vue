<script setup>
import backgroundUrl from './assets/cafe-obedov.jpg';
</script>

<template>
  <main
    class="min-h-screen bg-slate-950 bg-cover bg-center bg-no-repeat"
    :style="{ backgroundImage: `url(${backgroundUrl})` }"
  >
    <section class="flex min-h-screen w-full items-center justify-center bg-black/50 px-4 py-12 text-slate-100">
      <div class="w-full max-w-md">
        <div class="flex flex-col gap-4">
        <a
          href="/messenger/go/telegram"
          class="flex items-center gap-4 rounded-2xl border border-slate-700 bg-slate-900/80 px-5 py-4 shadow-xl shadow-black/20 transition hover:border-cyan-400 hover:bg-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
        >
          <svg class="h-12 w-12 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
            <path
              fill="#2AABEE"
              d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"
            />
          </svg>
          <span class="text-lg font-medium text-slate-100">Telegram</span>
        </a>
        <a
          href="/messenger/go/max"
          class="flex items-center gap-4 rounded-2xl border border-slate-700 bg-slate-900/80 px-5 py-4 shadow-xl shadow-black/20 transition hover:border-cyan-400 hover:bg-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400"
        >
          <svg class="h-12 w-12 shrink-0" viewBox="0 0 1000 1000" aria-hidden="true">
            <defs>
              <linearGradient id="max-fade">
                <stop offset="0" stop-color="#00f" />
                <stop offset="1" stop-opacity="0" />
              </linearGradient>
              <linearGradient id="max-brand">
                <stop offset="0" stop-color="#4cf" />
                <stop offset=".662" stop-color="#53e" />
                <stop offset="1" stop-color="#93d" />
              </linearGradient>
              <linearGradient
                id="max-fill"
                x1="117.847"
                x2="1000"
                y1="760.536"
                y2="500"
                gradientUnits="userSpaceOnUse"
                href="#max-brand"
              />
              <radialGradient
                id="max-glow"
                cx="-87.392"
                cy="1166.116"
                r="500"
                fx="-87.392"
                fy="1166.116"
                gradientTransform="rotate(51.356 1551.478 559.3)scale(2.42703433 1)"
                gradientUnits="userSpaceOnUse"
                href="#max-fade"
              />
            </defs>
            <rect width="1000" height="1000" fill="url(#max-fill)" ry="249.681" />
            <rect width="1000" height="1000" fill="url(#max-glow)" ry="249.681" />
            <path
              fill="#fff"
              fill-rule="evenodd"
              d="M508.211 878.328c-75.007 0-109.864-10.95-170.453-54.75-38.325 49.275-159.686 87.783-164.979 21.9 0-49.456-10.95-91.248-23.36-136.873-14.782-56.21-31.572-118.807-31.572-209.508 0-216.626 177.754-379.597 388.357-379.597 210.785 0 375.947 171.001 375.947 381.604.707 207.346-166.595 376.118-373.94 377.224m3.103-571.585c-102.564-5.292-182.499 65.7-200.201 177.024-14.6 92.162 11.315 204.398 33.397 210.238 10.585 2.555 37.23-18.98 53.837-35.587a189.8 189.8 0 0 0 92.71 33.032c106.273 5.112 197.08-75.794 204.215-181.95 4.154-106.382-77.67-196.486-183.958-202.574Z"
              clip-rule="evenodd"
            />
          </svg>
          <span class="text-lg font-medium text-slate-100">MAX</span>
        </a>
        </div>
      </div>
    </section>
  </main>
</template>
