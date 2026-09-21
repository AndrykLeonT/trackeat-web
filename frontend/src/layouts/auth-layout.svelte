<script>
    import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout.svelte';

    let { children, title, description } = $props();
</script>

<AuthLayoutTemplate {title} {description}>
    {@render children?.()}
</AuthLayoutTemplate>
