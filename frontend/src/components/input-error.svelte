<script>
    import { cn } from '@/lib/utils';

    let { message, class: className = '', ...rest } = $props();
</script>

{#if message}
    <p class={cn('text-sm text-red-600 dark:text-red-400', className)} {...rest}>
        {message}
    </p>
{/if}
