<script setup lang="ts">
import type { Criterion } from '~/composables/useApi'

defineProps<{ criteria: Criterion[] }>()

const typeLabel: Record<string, string> = {
  boolean: 'Sí/No',
  years: 'Años',
  enum: 'Nivel',
  score_1_5: 'Puntaje 1-5',
}

const priorityColor: Record<string, string> = {
  high: 'red',
  medium: 'amber',
  low: 'gray',
}

const priorityLabel: Record<string, string> = {
  high: 'Alta',
  medium: 'Media',
  low: 'Baja',
}

const formatExpected = (criterion: Criterion): string => {
  const ev = criterion.expected_value
  if (ev.min !== undefined) return `≥ ${ev.min} año(s)`
  if (ev.value !== undefined) return ev.value ? 'Requerido' : 'No requerido'
  if (ev.accepted) return (ev.accepted as string[]).join(' / ')
  if (ev.level) return String(ev.level)
  if (ev.min_score) return `≥ ${ev.min_score}/5`
  return JSON.stringify(ev)
}
</script>

<template>
  <div class="overflow-x-auto rounded-lg border border-gray-200">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
        <tr>
          <th class="px-4 py-3 text-left font-semibold">Criterio</th>
          <th class="px-4 py-3 text-left font-semibold">Tipo</th>
          <th class="px-4 py-3 text-left font-semibold">Esperado</th>
          <th class="px-4 py-3 text-center font-semibold">Prioridad</th>
          <th class="px-4 py-3 text-center font-semibold">Peso</th>
          <th class="px-4 py-3 text-center font-semibold">Req.</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 bg-white">
        <tr v-for="c in criteria" :key="c.key" class="hover:bg-gray-50 transition-colors">
          <td class="px-4 py-3">
            <div class="font-medium text-gray-900">{{ c.label }}</div>
            <div class="text-xs text-gray-400 font-mono mt-0.5">{{ c.key }}</div>
          </td>
          <td class="px-4 py-3 text-gray-600">{{ typeLabel[c.type] ?? c.type }}</td>
          <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ formatExpected(c) }}</td>
          <td class="px-4 py-3 text-center">
            <UBadge :color="priorityColor[c.priority] ?? 'gray'" variant="soft" size="xs">
              {{ priorityLabel[c.priority] ?? c.priority }}
            </UBadge>
          </td>
          <td class="px-4 py-3 text-center font-bold text-indigo-600">{{ c.weight }}</td>
          <td class="px-4 py-3 text-center">
            <span v-if="c.required" class="text-green-600 font-bold">✓</span>
            <span v-else class="text-gray-300">—</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
