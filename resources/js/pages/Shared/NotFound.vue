<script setup>
import EmptyState from '../../components/EmptyState.vue';
</script>

<template>
    <EmptyState title="Page not found" description="The page you are looking for does not exist.">
        <RouterLink to="/" class="text-sm font-medium text-brand-600 hover:underline">Go to dashboard</RouterLink>
    </EmptyState>
</template>
