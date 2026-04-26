import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const get = (obj, path) => path.split('.').reduce((acc, k) => (acc != null ? acc[k] : undefined), obj)

const interpolate = (str, replacements) => {
    if (typeof str !== 'string' || !replacements) return str
    return str.replace(/:(\w+)/g, (_, k) => (replacements[k] ?? `:${k}`))
}

/**
 * Reactive translation helper.
 *   const { t, locale } = useT()
 *   t('wallet.balance_label')
 *   t('common.days_left', { n: 5 })
 */
export function useT() {
    const page = usePage()
    const dict = computed(() => page.props.translations || {})
    const locale = computed(() => page.props.locale || 'ru')
    const availableLocales = computed(() => page.props.available_locales || ['ru', 'en'])

    const t = (key, replacements) => {
        const value = get(dict.value, key)
        if (value === undefined || value === null) return key
        return interpolate(value, replacements)
    }

    return { t, locale, availableLocales }
}
