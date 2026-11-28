<script setup>
/**
 * Lightweight responsive table: columns [{ key, label, align, class }]; cell slots `cell-<key>`.
 * Horizontal scroll on small screens; optional row click.
 */
defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, required: true },
  rowKey: { type: String, default: 'id' },
  clickable: { type: Boolean, default: false },
})
const emit = defineEmits(['row-click'])
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="border-b border-line bg-slate-50 text-left text-xs text-muted">
        <tr>
          <th v-for="col in columns" :key="col.key" scope="col" class="px-4 py-2.5 font-medium whitespace-nowrap" :class="[col.align === 'right' ? 'text-right' : '', col.class]">{{ col.label }}</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-line">
        <tr
          v-for="row in rows"
          :key="row[rowKey]"
          :class="clickable ? 'cursor-pointer hover:bg-slate-50' : ''"
          :tabindex="clickable ? 0 : undefined"
          @click="clickable && emit('row-click', row)"
          @keydown.enter="clickable && emit('row-click', row)"
        >
          <td v-for="col in columns" :key="col.key" class="px-4 py-3 align-middle" :class="[col.align === 'right' ? 'text-right tabular' : '', col.class]">
            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">{{ row[col.key] }}</slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
