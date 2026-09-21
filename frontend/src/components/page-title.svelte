<script>
    const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

    let { title } = $props();
</script>

<svelte:head>
    <title>{title} - {appName}</title>
</svelte:head>
