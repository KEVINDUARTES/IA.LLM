<script setup lang="ts">
import type { BreakdownItem } from '~/composables/useApi'

defineProps<{ breakdown: BreakdownItem[] }>()

const resultConfig = {
  match:    { label: 'Match',    icon: '✓', bg: 'bg-green-50',  text: 'text-green-700',  badge: 'text-green-600 bg-green-100' },
  no_match: { label: 'No match', icon: '✗', bg: 'bg-red-50',    text: 'text-red-700',    badge: 'text-red-600 bg-red-100' },
  unknown:  { label: 'Unknown',  icon: '?', bg: 'bg-gray-50',   text: 'text-gray-500',   badge: 'text-gray-500 bg-gray-100' },
}

const getConfig = (result: string) => resultConfig[result as keyof typeof resultConfig] ?? resultConfig.unknown
</script>

<template>
  <div class="overflow-x-auto rounded-lg border border-gray-200">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
        <tr>
          <th class="px-4 py-3 text-left font-semibold">Criterio</th>
          <th class="px-4 py-3 text-center font-semibold">Resultado</th>
          <th class="px-4 py-3 text-center font-semibold">Puntos</th>
          <th class="px-4 py-3 text-center font-semibold">Peso</th>
          <th class="px-4 py-3 text-left font-semibold">Evidencia</th>
          <th class="px-4 py-3 text-center font-semibold">Confianza</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 bg-white">
        <tr
          v-for="item in breakdown"
          :key="item.key"
          :class="getConfig(item.result).bg"
          class="transition-colors"
        >
          <td class="px-4 py-3">
            <div class="font-medium text-gray-900">{{ item.criterion }}</div>
            <div class="text-xs text-gray-400 font-mono mt-0.5">{{ item.key }}</div>
            <span v-if="item.required" class="text-xs text-indigo-500 font-semibold">Requerido</span>
          </td>
          <td class="px-4 py-3 text-center">
            <span
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold"
              :class="getConfig(item.result).badge"
            >
              {{ getConfig(item.result).icon }} {{ getConfig(item.result).label }}
            </span>
          </td>
          <td class="px-4 py-3 text-center font-bold" :class="getConfig(item.result).text">
            {{ item.points }}
          </td>
          <td class="px-4 py-3 text-center text-gray-500">{{ item.weight }}</td>
          <td class="px-4 py-3 text-gray-600 text-xs">{{ item.evidence }}</td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center justify-center gap-1">
              <div class="w-12 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                <div
                  class="h-full bg-indigo-500 rounded-full"
                  :style="{ width: `${item.confidence * 100}%` }"
                />
              </div>
              <span class="text-xs text-gray-400">{{ Math.round(item.confidence * 100) }}%</span>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
