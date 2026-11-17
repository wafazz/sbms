<script setup>
import EmptyState from '../../components/EmptyState.vue';
</script>

<template>
    <EmptyState title="Access denied" description="Your role does not include this page. Ask an administrator if you need access.">
        <RouterLink :to="{ name: 'dashboard' }" class="text-sm font-medium text-brand-600 hover:underline">Back to dashboard</RouterLink>
    </EmptyState>
</template>
