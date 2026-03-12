export interface Criterion {
  id: number
  key: string
  label: string
  type: string
  required: boolean
  priority: string
  expected_value: Record<string, unknown>
  weight: number
}

export interface JobOffer {
  id: number
  title: string
  description: string
  criteria_status: string
  criteria_count: number
  criteria: Criterion[]
}

export interface CandidateCV {
  id: number
  cv_hash: string
  extraction_status: string
  structured_data?: Record<string, unknown>
  created_at: string
}

export interface BreakdownItem {
  criterion: string
  key: string
  weight: number
  required: boolean
  result: 'match' | 'no_match' | 'unknown'
  points: number
  evidence: string
  confidence: number
}

export interface ScoringResult {
  id: number
  status: string
  job_offer_id: number
  candidate_cv_id: number
  score?: number
  breakdown?: BreakdownItem[]
  gaps?: BreakdownItem[]
  error_message?: string
  created_at: string
  updated_at: string
}

export const useApi = () => {
  const config = useRuntimeConfig()
  const base = config.public.apiBase as string

  // ngrok-skip-browser-warning bypasses the ngrok interstitial page.
  // Content-Type is set explicitly to ensure Laravel parses the JSON body.
  const defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'ngrok-skip-browser-warning': 'true',
  }

  const createJob = (body: { title: string; description: string }) =>
    $fetch<{ data: JobOffer }>(`${base}/jobs`, { method: 'POST', body, headers: defaultHeaders })

  const getJob = (id: number) =>
    $fetch<{ data: JobOffer }>(`${base}/jobs/${id}`, { headers: defaultHeaders })

  const submitCV = (body: { cv_text: string }) =>
    $fetch<{ data: CandidateCV }>(`${base}/cvs`, { method: 'POST', body, headers: defaultHeaders })

  const getCV = (id: number) =>
    $fetch<{ data: CandidateCV }>(`${base}/cvs/${id}`, { headers: defaultHeaders })

  const initiateScore = (body: { job_offer_id: number; candidate_cv_id: number }) =>
    $fetch<{ data: ScoringResult }>(`${base}/score`, { method: 'POST', body, headers: defaultHeaders })

  const getScore = (id: number) =>
    $fetch<{ data: ScoringResult }>(`${base}/score/${id}`, { headers: defaultHeaders })

  return { createJob, getJob, submitCV, getCV, initiateScore, getScore }
}
