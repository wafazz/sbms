<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import EmptyState from '../../components/EmptyState.vue';

const route = useRoute();
const phase = computed(() => route.meta.phase ?? 'a later phase');
</script>

<template>
    <EmptyState
        :title="`${route.meta.title} is coming in ${phase}`"
        description="This module is part of the roadmap. The navigation and access rules are already in place."
    />
</template>
