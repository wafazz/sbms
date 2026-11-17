<script setup>
import { computed } from 'vue';

/**
 * Line icons for the sidebar. Inline SVG, so no icon font or extra dependency.
 */
const props = defineProps({
    name: { type: String, required: true },
});

// Each icon is a list of SVG elements on a 24x24 grid, drawn with currentColor strokes.
const icons = {
    dashboard: [
        { tag: 'rect', x: 3, y: 3, width: 7, height: 9, rx: 1.5 },
        { tag: 'rect', x: 14, y: 3, width: 7, height: 5, rx: 1.5 },
        { tag: 'rect', x: 14, y: 12, width: 7, height: 9, rx: 1.5 },
        { tag: 'rect', x: 3, y: 16, width: 7, height: 5, rx: 1.5 },
    ],
    projects: [
        { tag: 'path', d: 'M3 7.5A1.5 1.5 0 0 1 4.5 6h4.6l2 2.5h8.4A1.5 1.5 0 0 1 21 10v7.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5z' },
    ],
    clients: [
        { tag: 'circle', cx: 9, cy: 8, r: 3.5 },
        { tag: 'path', d: 'M2.5 20a6.5 6.5 0 0 1 13 0' },
        { tag: 'path', d: 'M16 4.6a3.5 3.5 0 0 1 0 6.8' },
        { tag: 'path', d: 'M18 14.2a6.5 6.5 0 0 1 3.5 5.8' },
    ],
    invoices: [
        { tag: 'path', d: 'M6 3h9l4 4v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z' },
        { tag: 'path', d: 'M14 3v5h5' },
        { tag: 'path', d: 'M8.5 13h7M8.5 16.5h7M8.5 9.5h2.5' },
    ],
    settings: [
        { tag: 'path', d: 'M4 6h10M18 6h2M4 12h2M10 12h10M4 18h12M20 18h0' },
        { tag: 'circle', cx: 16, cy: 6, r: 2 },
        { tag: 'circle', cx: 8, cy: 12, r: 2 },
        { tag: 'circle', cx: 18, cy: 18, r: 2 },
    ],
    users: [
        { tag: 'circle', cx: 12, cy: 8, r: 3.5 },
        { tag: 'path', d: 'M5 20.5a7 7 0 0 1 14 0' },
    ],
};

const elements = computed(() => icons[props.name] ?? []);
</script>

<template>
    <svg
        class="h-5 w-5 shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
    >
        <template v-for="(el, i) in elements" :key="i">
            <rect v-if="el.tag === 'rect'" :x="el.x" :y="el.y" :width="el.width" :height="el.height" :rx="el.rx" />
            <circle v-else-if="el.tag === 'circle'" :cx="el.cx" :cy="el.cy" :r="el.r" />
            <path v-else :d="el.d" />
        </template>
    </svg>
</template>
