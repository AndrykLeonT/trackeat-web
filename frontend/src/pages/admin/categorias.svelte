<script>
    import CatalogList from '@/components/catalogo-admin-list.svelte';
    let { categorias } = $props();
</script>

<CatalogList rows={categorias} kind="categorias" />
