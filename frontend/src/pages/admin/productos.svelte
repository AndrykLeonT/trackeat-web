<script>
    import CatalogList from '@/components/catalogo-admin-list.svelte';
    let { productos } = $props();
</script>

<CatalogList rows={productos} kind="productos" />
