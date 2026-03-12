<script setup lang="ts">
import type { JobOffer, CandidateCV, ScoringResult } from '~/composables/useApi'

const api = useApi()

// ─── Step management ────────────────────────────────────────────────────────
const currentStep = ref(1)

const steps = [
  { id: 1, label: 'Oferta Laboral' },
  { id: 2, label: 'Currículum' },
  { id: 3, label: 'Scoring' },
  { id: 4, label: 'Resultado' },
]

// ─── Step 1: Job Offer ──────────────────────────────────────────────────────
const jobTitle       = ref('')
const jobDescription = ref('')
const jobOffer       = ref<JobOffer | null>(null)
const jobLoading     = ref(false)
const jobError       = ref('')
const jobPolling     = ref<ReturnType<typeof setInterval> | null>(null)

const isJobReady = computed(() => jobOffer.value?.criteria_status === 'completed')
const isJobFailed = computed(() => jobOffer.value?.criteria_status === 'failed')

const handleCreateJob = async () => {
  if (!jobTitle.value.trim() || !jobDescription.value.trim()) return
  jobLoading.value = true
  jobError.value = ''
  try {
    const res = await api.createJob({ title: jobTitle.value, description: jobDescription.value })
    jobOffer.value = res.data
    startJobPolling()
  } catch (e: unknown) {
    const err = e as { data?: { message?: string } }
    jobError.value = err?.data?.message ?? 'Error al conectar con el backend. Verificá que esté corriendo.'
  } finally {
    jobLoading.value = false
  }
}

const startJobPolling = () => {
  jobPolling.value = setInterval(async () => {
    if (!jobOffer.value) return
    try {
      const res = await api.getJob(jobOffer.value.id)
      jobOffer.value = res.data
      if (['completed', 'failed'].includes(res.data.criteria_status)) {
        clearInterval(jobPolling.value!)
        jobPolling.value = null
        if (res.data.criteria_status === 'completed') currentStep.value = 2
      }
    } catch { /* silently retry */ }
  }, 3000)
}

// ─── Step 2: CV ─────────────────────────────────────────────────────────────
const cvText     = ref('')
const cv         = ref<CandidateCV | null>(null)
const cvLoading  = ref(false)
const cvError    = ref('')
const cvPolling  = ref<ReturnType<typeof setInterval> | null>(null)
const cvDeduplicated = ref(false)

const isCVReady   = computed(() => cv.value?.extraction_status === 'completed')
const isCVFailed  = computed(() => cv.value?.extraction_status === 'failed')

const handleSubmitCV = async () => {
  if (!cvText.value.trim()) return
  cvLoading.value = true
  cvError.value = ''
  cvDeduplicated.value = false
  try {
    const res = await api.submitCV({ cv_text: cvText.value })
    cv.value = res.data
    if (res.data.extraction_status === 'completed') {
      cvDeduplicated.value = true
      currentStep.value = 3
    } else {
      startCVPolling()
    }
  } catch (e: unknown) {
    const err = e as { data?: { message?: string } }
    cvError.value = err?.data?.message ?? 'Error al enviar el CV.'
  } finally {
    cvLoading.value = false
  }
}

const startCVPolling = () => {
  cvPolling.value = setInterval(async () => {
    if (!cv.value) return
    try {
      const res = await api.getCV(cv.value.id)
      cv.value = res.data
      if (['completed', 'failed'].includes(res.data.extraction_status)) {
        clearInterval(cvPolling.value!)
        cvPolling.value = null
        if (res.data.extraction_status === 'completed') currentStep.value = 3
      }
    } catch { /* silently retry */ }
  }, 3000)
}

// ─── Step 3: Score ──────────────────────────────────────────────────────────
const scoringResult  = ref<ScoringResult | null>(null)
const scoreLoading   = ref(false)
const scoreError     = ref('')
const scorePolling   = ref<ReturnType<typeof setInterval> | null>(null)

const canScore    = computed(() => isJobReady.value && isCVReady.value)
const isScoreReady = computed(() => scoringResult.value?.status === 'completed')

const handleScore = async () => {
  if (!canScore.value) return
  scoreLoading.value = true
  scoreError.value = ''
  try {
    const res = await api.initiateScore({
      job_offer_id:    jobOffer.value!.id,
      candidate_cv_id: cv.value!.id,
    })
    scoringResult.value = res.data
    if (res.data.status === 'completed') {
      currentStep.value = 4
    } else {
      startScorePolling()
    }
  } catch (e: unknown) {
    const err = e as { data?: { message?: string } }
    scoreError.value = err?.data?.message ?? 'Error al iniciar el scoring.'
  } finally {
    scoreLoading.value = false
  }
}

const startScorePolling = () => {
  scorePolling.value = setInterval(async () => {
    if (!scoringResult.value) return
    try {
      const res = await api.getScore(scoringResult.value.id)
      scoringResult.value = res.data
      if (['completed', 'failed'].includes(res.data.status)) {
        clearInterval(scorePolling.value!)
        scorePolling.value = null
        if (res.data.status === 'completed') currentStep.value = 4
      }
    } catch { /* silently retry */ }
  }, 3000)
}

// ─── Reset ───────────────────────────────────────────────────────────────────
const resetAll = () => {
  if (jobPolling.value)   clearInterval(jobPolling.value)
  if (cvPolling.value)    clearInterval(cvPolling.value)
  if (scorePolling.value) clearInterval(scorePolling.value)
  currentStep.value  = 1
  jobTitle.value     = ''
  jobDescription.value = ''
  jobOffer.value     = null
  jobError.value     = ''
  cvText.value       = ''
  cv.value           = null
  cvError.value      = ''
  scoringResult.value = null
  scoreError.value   = ''
  cvDeduplicated.value = false
}

// Cleanup polling on unmount
onUnmounted(() => {
  if (jobPolling.value)   clearInterval(jobPolling.value)
  if (cvPolling.value)    clearInterval(cvPolling.value)
  if (scorePolling.value) clearInterval(scorePolling.value)
})

// ─── Helpers ─────────────────────────────────────────────────────────────────
const statusBadge = (status: string) => ({
  pending:    { color: 'yellow', label: 'Pendiente' },
  processing: { color: 'blue',   label: 'Procesando...' },
  completed:  { color: 'green',  label: 'Completado' },
  failed:     { color: 'red',    label: 'Error' },
}[status] ?? { color: 'gray', label: status }) as { color: string; label: string }

const matchSummary = computed(() => {
  if (!scoringResult.value?.breakdown) return null
  const b = scoringResult.value.breakdown
  return {
    match:    b.filter(i => i.result === 'match').length,
    no_match: b.filter(i => i.result === 'no_match').length,
    unknown:  b.filter(i => i.result === 'unknown').length,
    total:    b.length,
  }
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-indigo-50">

    <!-- ── Header ─────────────────────────────────────────────────────── -->
    <header class="bg-white/80 backdrop-blur border-b border-gray-200 sticky top-0 z-10">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center shadow-sm">
            <span class="text-white font-black text-sm">CV</span>
          </div>
          <div>
            <h1 class="text-base font-bold text-gray-900 leading-none">CV Scoring System</h1>
            <p class="text-xs text-gray-400 mt-0.5">Evaluación automática de perfiles · Talently</p>
          </div>
        </div>
        <UButton v-if="currentStep > 1" variant="ghost" size="xs" icon="i-heroicons-arrow-path" @click="resetAll">
          Nueva evaluación
        </UButton>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8 space-y-6">

      <!-- ── Step indicator ────────────────────────────────────────────── -->
      <div class="flex items-center justify-center gap-0">
        <template v-for="(step, idx) in steps" :key="step.id">
          <div class="flex flex-col items-center">
            <div
              class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300"
              :class="{
                'bg-indigo-600 text-white shadow-md shadow-indigo-200': currentStep === step.id,
                'bg-green-500 text-white': currentStep > step.id,
                'bg-gray-200 text-gray-400': currentStep < step.id,
              }"
            >
              <span v-if="currentStep > step.id">✓</span>
              <span v-else>{{ step.id }}</span>
            </div>
            <span
              class="text-xs mt-1 font-medium transition-colors duration-300"
              :class="currentStep >= step.id ? 'text-gray-700' : 'text-gray-400'"
            >
              {{ step.label }}
            </span>
          </div>
          <div
            v-if="idx < steps.length - 1"
            class="w-16 h-0.5 mb-5 mx-1 transition-colors duration-300"
            :class="currentStep > step.id ? 'bg-green-400' : 'bg-gray-200'"
          />
        </template>
      </div>

      <!-- ══════════════════════════════════════════════════════════════ -->
      <!-- STEP 1 — Oferta Laboral                                       -->
      <!-- ══════════════════════════════════════════════════════════════ -->
      <section class="space-y-4">
        <div class="flex items-center gap-2">
          <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">1</div>
          <h2 class="text-lg font-bold text-gray-800">Oferta Laboral</h2>
        </div>

        <!-- Form -->
        <UCard v-if="!jobOffer" class="shadow-sm">
          <div class="space-y-4">
            <UFormGroup label="Título del puesto" required>
              <UInput
                v-model="jobTitle"
                placeholder="ej: Fullstack Developer (Laravel / Vue.js / AI)"
                size="lg"
              />
            </UFormGroup>
            <UFormGroup label="Descripción del puesto" required>
              <UTextarea
                v-model="jobDescription"
                placeholder="Pegá aquí la descripción completa del puesto. Cuanto más detallada, mejores serán los criterios generados por IA..."
                :rows="8"
                resize
              />
            </UFormGroup>
            <UAlert v-if="jobError" color="red" icon="i-heroicons-exclamation-circle" :description="jobError" />
            <UButton
              :loading="jobLoading"
              :disabled="!jobTitle.trim() || !jobDescription.trim()"
              icon="i-heroicons-sparkles"
              size="lg"
              block
              @click="handleCreateJob"
            >
              Crear oferta y generar criterios con IA
            </UButton>
          </div>
        </UCard>

        <!-- Status card while processing / after completion -->
        <UCard v-if="jobOffer" class="shadow-sm">
          <div class="space-y-4">
            <!-- Header row -->
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs text-gray-400 uppercase font-semibold tracking-wide mb-0.5">Oferta #{{ jobOffer.id }}</p>
                <h3 class="text-base font-bold text-gray-900">{{ jobOffer.title }}</h3>
              </div>
              <UBadge
                :color="statusBadge(jobOffer.criteria_status).color"
                variant="soft"
                class="shrink-0 mt-0.5"
              >
                {{ statusBadge(jobOffer.criteria_status).label }}
              </UBadge>
            </div>

            <!-- Generating indicator -->
            <div v-if="!isJobReady && !isJobFailed" class="flex items-center gap-3 py-3 px-4 bg-blue-50 rounded-lg">
              <svg class="animate-spin w-5 h-5 text-blue-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <div>
                <p class="text-sm font-semibold text-blue-700">Generando criterios de selección...</p>
                <p class="text-xs text-blue-500">El worker de IA está analizando la descripción del puesto. Esto toma ~5-15 segundos.</p>
              </div>
            </div>

            <!-- Failure -->
            <UAlert v-if="isJobFailed" color="red" icon="i-heroicons-exclamation-circle" description="Error generando criterios. El worker puede estar detenido." />

            <!-- Criteria table -->
            <div v-if="isJobReady && jobOffer.criteria?.length">
              <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-semibold text-gray-700">
                  {{ jobOffer.criteria.length }} criterios generados por IA
                </p>
                <span class="text-xs text-gray-400">Peso total: {{ jobOffer.criteria.reduce((s, c) => s + c.weight, 0) }}</span>
              </div>
              <CriteriaTable :criteria="jobOffer.criteria" />
            </div>
          </div>
        </UCard>
      </section>

      <!-- ══════════════════════════════════════════════════════════════ -->
      <!-- STEP 2 — Currículum                                           -->
      <!-- ══════════════════════════════════════════════════════════════ -->
      <section v-if="currentStep >= 2" class="space-y-4">
        <div class="flex items-center gap-2">
          <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">2</div>
          <h2 class="text-lg font-bold text-gray-800">Currículum del Candidato</h2>
        </div>

        <!-- Form -->
        <UCard v-if="!cv" class="shadow-sm">
          <div class="space-y-4">
            <UFormGroup label="Texto del CV" required>
              <UTextarea
                v-model="cvText"
                placeholder="Pegá aquí el texto completo del CV del candidato (nombre, experiencia, skills, educación...)..."
                :rows="12"
                resize
              />
            </UFormGroup>
            <UAlert
              color="blue"
              icon="i-heroicons-information-circle"
              description="Si enviás el mismo CV dos veces, el sistema lo detecta por hash SHA-256 y reutiliza la extracción existente sin llamar a la IA nuevamente."
            />
            <UAlert v-if="cvError" color="red" icon="i-heroicons-exclamation-circle" :description="cvError" />
            <UButton
              :loading="cvLoading"
              :disabled="!cvText.trim()"
              icon="i-heroicons-document-text"
              size="lg"
              block
              @click="handleSubmitCV"
            >
              Enviar CV y extraer datos estructurados
            </UButton>
          </div>
        </UCard>

        <!-- Status card -->
        <UCard v-if="cv" class="shadow-sm">
          <div class="space-y-4">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs text-gray-400 uppercase font-semibold tracking-wide mb-0.5">CV #{{ cv.id }}</p>
                <p class="text-sm text-gray-600 font-mono truncate max-w-xs">{{ cv.cv_hash.substring(0, 20) }}...</p>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <UBadge v-if="cvDeduplicated" color="purple" variant="soft" icon="i-heroicons-bolt">
                  Deduplicado
                </UBadge>
                <UBadge
                  :color="statusBadge(cv.extraction_status).color"
                  variant="soft"
                >
                  {{ statusBadge(cv.extraction_status).label }}
                </UBadge>
              </div>
            </div>

            <!-- Extracting indicator -->
            <div v-if="!isCVReady && !isCVFailed" class="flex items-center gap-3 py-3 px-4 bg-blue-50 rounded-lg">
              <svg class="animate-spin w-5 h-5 text-blue-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <div>
                <p class="text-sm font-semibold text-blue-700">Extrayendo datos del CV...</p>
                <p class="text-xs text-blue-500">El worker de IA está estructurando la información del CV. Esto toma ~5-15 segundos.</p>
              </div>
            </div>

            <!-- Failure -->
            <UAlert v-if="isCVFailed" color="red" icon="i-heroicons-exclamation-circle" description="Error extrayendo datos del CV. El worker puede estar detenido." />

            <!-- Extracted data summary -->
            <div v-if="isCVReady && cv.structured_data">
              <p class="text-sm font-semibold text-gray-700 mb-3">
                {{ Object.keys(cv.structured_data).length }} campos extraídos del CV
              </p>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                <div
                  v-for="(value, key) in cv.structured_data"
                  :key="key"
                  class="bg-gray-50 rounded-lg px-3 py-2 border border-gray-100"
                >
                  <p class="text-xs text-gray-400 font-mono truncate">{{ key }}</p>
                  <p class="text-sm font-semibold text-gray-800 truncate">
                    {{ typeof value === 'boolean' ? (value ? '✓ Sí' : '✗ No') : value }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </UCard>
      </section>

      <!-- ══════════════════════════════════════════════════════════════ -->
      <!-- STEP 3 — Calcular Score                                       -->
      <!-- ══════════════════════════════════════════════════════════════ -->
      <section v-if="currentStep >= 3" class="space-y-4">
        <div class="flex items-center gap-2">
          <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">3</div>
          <h2 class="text-lg font-bold text-gray-800">Calcular Score</h2>
        </div>

        <UCard v-if="!scoringResult" class="shadow-sm">
          <div class="space-y-4">
            <!-- Summary of what will be scored -->
            <div class="grid grid-cols-2 gap-3">
              <div class="bg-indigo-50 rounded-lg p-3 border border-indigo-100">
                <p class="text-xs text-indigo-400 font-semibold uppercase tracking-wide">Oferta</p>
                <p class="text-sm font-bold text-indigo-800 mt-0.5">{{ jobOffer?.title }}</p>
                <p class="text-xs text-indigo-500 mt-0.5">{{ jobOffer?.criteria?.length ?? 0 }} criterios</p>
              </div>
              <div class="bg-indigo-50 rounded-lg p-3 border border-indigo-100">
                <p class="text-xs text-indigo-400 font-semibold uppercase tracking-wide">Candidato</p>
                <p class="text-sm font-bold text-indigo-800 mt-0.5">CV #{{ cv?.id }}</p>
                <p class="text-xs text-indigo-500 mt-0.5">
                  {{ cv?.structured_data ? Object.keys(cv.structured_data).length : 0 }} campos extraídos
                </p>
              </div>
            </div>
            <UAlert
              color="indigo"
              icon="i-heroicons-cpu-chip"
              description="El scoring es 100% determinístico — no usa IA. Evalúa el JSON estructurado del CV contra cada criterio usando reglas de negocio puras (años, booleanos, enums, escala 1-5)."
            />
            <UAlert v-if="scoreError" color="red" icon="i-heroicons-exclamation-circle" :description="scoreError" />
            <UButton
              :loading="scoreLoading"
              :disabled="!canScore"
              icon="i-heroicons-chart-bar"
              size="lg"
              color="indigo"
              block
              @click="handleScore"
            >
              Calcular score del candidato
            </UButton>
          </div>
        </UCard>

        <!-- Scoring in progress -->
        <UCard v-if="scoringResult && !isScoreReady" class="shadow-sm">
          <div class="flex items-center gap-3 py-2">
            <svg class="animate-spin w-5 h-5 text-indigo-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <div>
              <p class="text-sm font-semibold text-gray-700">Calculando score...</p>
              <p class="text-xs text-gray-400">El worker está ejecutando la evaluación determinística.</p>
            </div>
          </div>
        </UCard>
      </section>

      <!-- ══════════════════════════════════════════════════════════════ -->
      <!-- STEP 4 — Resultado                                            -->
      <!-- ══════════════════════════════════════════════════════════════ -->
      <section v-if="currentStep >= 4 && isScoreReady && scoringResult" class="space-y-4">
        <div class="flex items-center gap-2">
          <div class="w-6 h-6 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs font-bold">4</div>
          <h2 class="text-lg font-bold text-gray-800">Resultado del Scoring</h2>
        </div>

        <!-- Score summary card -->
        <UCard class="shadow-sm">
          <div class="flex flex-col sm:flex-row items-center gap-8 py-2">
            <!-- Gauge -->
            <div class="shrink-0">
              <ScoreGauge :score="scoringResult.score ?? 0" />
            </div>
            <!-- Stats -->
            <div class="flex-1 space-y-4 w-full">
              <div>
                <h3 class="text-base font-bold text-gray-900">{{ jobOffer?.title }}</h3>
                <p class="text-sm text-gray-500">CV #{{ cv?.id }} · Scoring #{{ scoringResult.id }}</p>
              </div>
              <div class="grid grid-cols-3 gap-3" v-if="matchSummary">
                <div class="bg-green-50 rounded-xl p-3 text-center border border-green-100">
                  <p class="text-2xl font-black text-green-600">{{ matchSummary.match }}</p>
                  <p class="text-xs font-semibold text-green-500 mt-0.5">Matches</p>
                </div>
                <div class="bg-red-50 rounded-xl p-3 text-center border border-red-100">
                  <p class="text-2xl font-black text-red-500">{{ matchSummary.no_match }}</p>
                  <p class="text-xs font-semibold text-red-400 mt-0.5">No match</p>
                </div>
                <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-200">
                  <p class="text-2xl font-black text-gray-400">{{ matchSummary.unknown }}</p>
                  <p class="text-xs font-semibold text-gray-400 mt-0.5">Unknown</p>
                </div>
              </div>
              <!-- Formula explanation -->
              <p class="text-xs text-gray-400 font-mono bg-gray-50 px-3 py-2 rounded-lg">
                score = round( Σ puntos / Σ pesos × 100 )
              </p>
            </div>
          </div>
        </UCard>

        <!-- Gaps alert -->
        <UAlert
          v-if="scoringResult.gaps && scoringResult.gaps.length > 0"
          color="red"
          icon="i-heroicons-exclamation-triangle"
          :title="`${scoringResult.gaps.length} brecha(s) detectada(s)`"
          :description="scoringResult.gaps.map(g => g.criterion).join(', ')"
        />

        <!-- Breakdown table -->
        <UCard v-if="scoringResult.breakdown?.length" class="shadow-sm">
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-bold text-gray-800">Desglose por criterio</h3>
              <span class="text-xs text-gray-400">{{ scoringResult.breakdown.length }} criterios evaluados</span>
            </div>
          </template>
          <BreakdownTable :breakdown="scoringResult.breakdown" />
        </UCard>

        <!-- Unknown explanation -->
        <UAlert
          color="gray"
          icon="i-heroicons-information-circle"
          title="¿Qué significa 'Unknown'?"
          description="Unknown indica que el extractor de IA no generó esa clave en el CV estructurado — no necesariamente que el candidato no tenga esa habilidad. Es un trade-off conocido de la extracción job-agnóstica."
        />

        <!-- Reset button -->
        <div class="flex justify-center pt-2">
          <UButton variant="outline" icon="i-heroicons-arrow-path" @click="resetAll">
            Evaluar otro perfil
          </UButton>
        </div>
      </section>

    </main>

    <!-- ── Footer ───────────────────────────────────────────────────── -->
    <footer class="mt-12 py-6 border-t border-gray-200 text-center">
      <p class="text-xs text-gray-400">
        CV Scoring System · Challenge Técnico Talently · Backend Laravel 10 + Groq AI
      </p>
    </footer>

  </div>
</template>
