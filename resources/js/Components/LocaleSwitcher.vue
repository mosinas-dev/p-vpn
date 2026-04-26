<script setup>
import { router } from '@inertiajs/vue3'
import { useT } from '@/composables/useT'

const { t, locale, availableLocales } = useT()

const setLocale = (next) => {
    if (next === locale.value) return
    router.post(route('locale.update'), { locale: next }, {
        preserveScroll: true,
        preserveState: false,
    })
}
</script>

<template>
    <div
        class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900/40 p-0.5"
        role="group"
        :aria-label="t('common.language')"
    >
        <button
            v-for="loc in availableLocales"
            :key="loc"
            type="button"
            class="px-2.5 py-1 text-xs font-semibold uppercase rounded-md transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
            :class="locale === loc
                ? 'bg-slate-700 text-white'
                : 'text-slate-400 hover:text-white'"
            :aria-pressed="locale === loc"
            @click="setLocale(loc)"
        >
            {{ loc }}
        </button>
    </div>
</template>
