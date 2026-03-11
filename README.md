# CV Scoring System

Servicio backend construido en **Laravel 10** que evalúa automáticamente un CV contra una oferta laboral mediante un pipeline de scoring estructurado asistido por IA.

---

## Tabla de Contenidos

- [Arquitectura del Sistema](#arquitectura-del-sistema)
- [Decisiones de Diseño y Trade-offs](#decisiones-de-diseño-y-trade-offs)
- [Cómo se Generan los Criterios](#cómo-se-generan-los-criterios)
- [Cómo se Evita el Reprocesamiento](#cómo-se-evita-el-reprocesamiento)
- [Cómo Funciona el Scoring](#cómo-funciona-el-scoring)
- [Referencia de la API](#referencia-de-la-api)
- [Cómo Ejecutar el Proyecto](#cómo-ejecutar-el-proyecto)
- [Resultado ejecutanto mi propio cv](#ejemplo-real-de-ejecución)

---

## Arquitectura del Sistema

```
┌──────────────────────────────────────────────────────────────┐
│                        Capa HTTP                             │
│  JobOfferController  CandidateCVController  ScoringController│
│       (delgados — delegan todo a los servicios)              │
└──────────────────────┬───────────────────────────────────────┘
                       │
┌──────────────────────▼───────────────────────────────────────┐
│                    Capa de Servicios                          │
│  JobOfferService  CVService  ScoreOrchestrationService        │
│  CriteriaGenerationService  CVExtractionService  ScoringService│
└──────┬────────────────────────────┬────────────────────────┬─┘
       │                            │                        │
┌──────▼──────┐           ┌─────────▼──────────┐   ┌────────▼────────┐
│  Background │           │   Capa de Servicio  │   │   Eloquent ORM  │
│    Jobs     │           │        de IA        │   │    (Modelos)    │
│             │           │   (OpenAIService)   │   │                 │
│ GenerateCriteria        │  AIServiceInterface │   │ JobOffer        │
│ ExtractCVData           └─────────────────────┘   │ Criterion       │
│ ScoreCandidate                                     │ CandidateCV     │
└─────────────┘                                     │ ScoringResult   │
                                                    └─────────────────┘
```

### Separación de responsabilidades

| Capa | Responsabilidad |
|------|----------------|
| **Controllers** | Validar input HTTP, delegar a servicios, retornar Resources |
| **Services** | Orquestar lógica de negocio, sin lógica HTTP ni queries directas |
| **Jobs** | Encapsular tareas largas, manejar reintentos y estados de fallo |
| **AI Service** | Abstracción única sobre OpenAI — fácilmente reemplazable |
| **ScoringService** | 100% determinístico — nunca llama a ninguna IA |
| **Models** | Datos + relaciones únicamente, sin lógica de negocio |

---

## Decisiones de Diseño y Trade-offs

### 1. La IA solo se usa para I/O estructurado — nunca para el scoring

La IA genera criterios a partir de la descripción del job y extrae datos estructurados del CV. Ambas salidas se **persisten como JSON estructurado**. El motor de scoring opera sobre ese JSON de forma determinística, haciendo los resultados auditables, reproducibles y rápidos.

> **Por qué importa:** Un scoring determinístico significa que el mismo CV contra el mismo job siempre produce el mismo puntaje. También elimina la varianza causada por temperatura, actualizaciones del modelo o cortes del proveedor.

### 2. La extracción del CV es independiente del job

El `CandidateCV.structured_data` se extrae **una sola vez** y se reutiliza en todas las ofertas laborales. El prompt de extracción usa una convención de nombres de claves estandarizada (ej: `laravel_experience_years`, `english_level`, `has_remote_experience`) que coincide con las claves que el prompt de generación de criterios también está instruido a usar.

> **Trade-off:** Una extracción genérica puede no capturar criterios muy específicos de un job particular. La alternativa —extracción por job por CV— haría imposible la deduplicación por hash. La convención de nombres estandarizada resuelve esta tensión.

### 3. Hash SHA-256 para deduplicación

Antes de crear un nuevo registro `CandidateCV`, `CVService` computa `hash('sha256', trim($cvText))` y consulta por ese hash. Si ya existe un registro completado, la llamada a la IA se omite completamente y se devuelve el registro existente con HTTP 200.

### 4. Los criterios se generan una vez por oferta y se persisten

Los registros `Criterion` están vinculados a un `JobOffer`. Si el `GenerateCriteriaJob` necesita re-ejecutarse (ej: tras un fallo), el servicio elimina los criterios existentes antes de regenerar — asegurando que la DB nunca tenga conjuntos parciales.

### 5. Jobs con máquinas de estado explícitas

Cada proceso asíncrono (`job_offer.criteria_status`, `candidate_cv.extraction_status`, `scoring_result.status`) sigue una máquina de estados clara:

```
pending → processing → completed
                    └→ failed
```

`ScoreCandidateJob` verifica los prerrequisitos antes de puntuar. Si los criterios o la extracción del CV aún no están listos, llama a `$this->release(60)` — devolviendo el job a la cola para reintentarlo en 60 segundos — en lugar de fallar.

### 6. Crédito parcial en el scoring

Para criterios de tipo `years` y `score_1_5`, se otorgan puntos parciales de forma proporcional en lugar de binario 0/completo. Esto produce un score más matizado (ej: un candidato con 2 años cuando se requieren 4 obtiene el 50% del peso del criterio en vez de 0).

### 7. Derivación del peso desde la prioridad

Si la IA no sugiere explícitamente un peso para un criterio, se deriva de su prioridad: `alta=30`, `media=20`, `baja=10`. Esto asegura que los pesos siempre tengan un valor significativo y el campo `priority` de la IA nunca se ignore.

---

## Cómo se Generan los Criterios

1. `POST /api/v1/jobs` crea un registro `JobOffer` con `criteria_status = pending`.
2. `JobOfferService` despacha `GenerateCriteriaJob` a la cola.
3. El job llama a `CriteriaGenerationService::generateAndPersist()`:
   - Envía un prompt estructurado a OpenAI solicitando un array JSON de criterios.
   - El prompt impone el esquema: `key`, `label`, `type`, `required`, `priority`, `expected_value`, `weight`.
   - Instruye explícitamente al modelo a usar nombres de claves estandarizados que coincidan con las claves de extracción del CV.
4. El array retornado se valida y persiste como registros `Criterion`.
5. `criteria_status` se establece en `completed`.

**Estrategia del prompt — temperatura = 0:** El system prompt instruye a retornar solo un objeto JSON. La temperatura se establece en 0 para output determinístico y compatible con el esquema. También se pasa `response_format: json_object` a la API de OpenAI para garantizar una respuesta parseable.

---

## Cómo se Evita el Reprocesamiento

### Deduplicación a nivel CV (hash)

```
POST /api/v1/cvs  { "cv_text": "..." }
                          │
              Computa SHA-256 del texto normalizado
                          │
         ┌────────────────▼────────────────────┐
         │  SELECT * FROM candidate_cvs        │
         │  WHERE cv_hash = ?                  │
         └────────────────┬────────────────────┘
                          │
      ¿existe + completado?  ──SÍ──► retorna registro existente (HTTP 200)
                          │
                          NO
                          │
                  crea nuevo registro
                  despacha ExtractCVDataJob
```

### Deduplicación a nivel scoring

`ScoringResult` tiene una restricción `UNIQUE(job_offer_id, candidate_cv_id)`. `ScoreOrchestrationService` usa `firstOrCreate` — solo puede existir un registro de scoring por par. Si ya está completado, se retorna inmediatamente sin re-encolar.

---

## Cómo Funciona el Scoring

```
ScoringService::score(JobOffer, CandidateCV)
       │
       ├── Carga todos los Criterion de la oferta (eager-loaded, sin N+1)
       │
       └── Por cada Criterion:
               │
               ├── Busca  cvData[$criterion->key]
               │
               ├── ¿null?  → resultado: unknown, puntos: 0
               │
               └── Evalúa según tipo:
                     boolean   → actual == expected.value
                     years     → actual >= expected.min  (crédito parcial si es menor)
                     enum      → compara contra orden CEFR / educación, o lista accepted
                     score_1_5 → actual >= expected.min  (crédito parcial si es menor)
                           │
                           └── Produce: { result, points, evidence, confidence }
       │
       ├── score = round( suma(puntos) / suma(pesos) × 100 )
       │
       └── gaps = criterios donde result == 'no_match'
```

### Fórmula del Score

```
score = REDONDEAR( Σ(puntos_obtenidos_por_criterio) / Σ(peso_por_criterio) × 100 )
```

Esto normaliza el score a 0–100 sin importar cuántos criterios existan ni qué peso total tengan.

---

## Referencia de la API

URL base: `http://localhost:8000/api/v1`

### POST /jobs

Crea una oferta laboral y dispara la generación de criterios.

**Request**
```json
{
  "title": "Senior Backend Developer",
  "description": "Buscamos un experto en Laravel con 4+ años de experiencia..."
}
```

**Response 201**
```json
{
  "data": {
    "id": 1,
    "title": "Senior Backend Developer",
    "criteria_status": "pending",
    "created_at": "2024-03-11T10:00:00+00:00"
  }
}
```

---

### GET /jobs/{id}

Recupera una oferta laboral con sus criterios generados.

**Response 200**
```json
{
  "data": {
    "id": 1,
    "title": "Senior Backend Developer",
    "criteria_status": "completed",
    "criteria_count": 8,
    "criteria": [
      {
        "key": "laravel_experience_years",
        "label": "Experiencia en Laravel",
        "type": "years",
        "required": true,
        "priority": "high",
        "expected_value": { "min": 4 },
        "weight": 30
      }
    ]
  }
}
```

---

### POST /cvs

Envía un CV para extracción de datos estructurados.

**Request**
```json
{
  "cv_text": "Juan Pérez\nDesarrollador Backend con 5 años de experiencia en Laravel..."
}
```

**Response 201** (nuevo) o **200** (ya procesado — deduplicación por hash)
```json
{
  "data": {
    "id": 1,
    "cv_hash": "a3f2...",
    "extraction_status": "pending",
    "created_at": "2024-03-11T10:01:00+00:00"
  }
}
```

---

### POST /score

Inicia el scoring para un par (job, cv).

**Request**
```json
{
  "job_offer_id": 1,
  "candidate_cv_id": 1
}
```

**Response 202** (procesando) o **200** (ya completado)
```json
{
  "data": {
    "id": 1,
    "status": "pending",
    "job_offer_id": 1,
    "candidate_cv_id": 1
  }
}
```

---

### GET /score/{id}

Recupera un resultado de scoring.

**Response 200**
```json
{
  "data": {
    "id": 1,
    "status": "completed",
    "score": 82,
    "breakdown": [
      {
        "criterion": "Experiencia en Laravel",
        "key": "laravel_experience_years",
        "weight": 30,
        "required": true,
        "result": "match",
        "points": 30,
        "evidence": "5 año(s) encontrado(s) (requerido: 4+)",
        "confidence": 0.9
      },
      {
        "criterion": "Nivel de Inglés",
        "key": "english_level",
        "weight": 20,
        "required": false,
        "result": "match",
        "points": 20,
        "evidence": "Nivel 'b2' encontrado (requerido: 'b2')",
        "confidence": 0.85
      }
    ],
    "gaps": []
  }
}
```

---

## Cómo Ejecutar el Proyecto

### Prerrequisitos

- PHP 8.1+
- Composer
- MySQL 8+
- Worker de colas (o usar `QUEUE_CONNECTION=sync` para testing local)
- API key de OpenAI

### Setup

```bash
# 1. Instalar dependencias PHP
composer install

# 2. Copiar archivo de entorno
cp .env.example .env

# 3. Generar application key
php artisan key:generate

# 4. Configurar el .env:
#    - DB_DATABASE, DB_USERNAME, DB_PASSWORD
#    - OPENAI_API_KEY

# 5. Crear la base de datos (en MySQL):
mysql -u root -e "CREATE DATABASE cv_scoring CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 6. Correr migraciones
php artisan migrate

# 7. Levantar el servidor de desarrollo
php artisan serve

# 8. Levantar el worker de colas (en otra terminal)
php artisan queue:work --tries=3 --timeout=120
```

> **Testing rápido sin worker:** configurá `QUEUE_CONNECTION=sync` en el `.env` — los jobs se ejecutan sincrónicamente en el mismo request.

---

## Ejemplo Real de Ejecución

Este es el resultado de ejecutar el sistema usando **mi propio CV** evaluado contra la oferta de **Talently Backend Engineer**.

### Paso 1 — Crear la oferta laboral

```bash
curl -X POST http://localhost:8000/api/v1/jobs \
  -H "Content-Type: application/json" \
  -d @requests/1_create_job.json
```

**Response:**
```json
{ "data": { "id": 1, "title": "Fullstack Developer (Laravel / Vue.js / AI) - Talently", "criteria_status": "pending" } }
```

Luego de que el worker procesa `GenerateCriteriaJob`, los criterios generados son:

```
laravel_experience_years | years  | {"min": 3}
vue_js_experience_years  | years  | {"min": 2}
ai_integration_experience| boolean| {"value": true}
php_version              | enum   | {"level": "PHP 8+", "accepted": ["PHP 8", "PHP 9"]}
docker_experience        | boolean| {"value": true}
cloud_experience         | enum   | {"level": "Basic", "accepted": ["AWS", "GCP"]}
python_scripting_experience | boolean | {"value": true}
webhook_experience       | boolean| {"value": true}
```

### Paso 2 — Enviar el CV

```bash
curl -X POST http://localhost:8000/api/v1/cvs \
  -H "Content-Type: application/json" \
  -d @requests/2_submit_cv.json
```

**Response:** `{ "data": { "id": 1, "extraction_status": "pending" } }`

Luego de que `ExtractCVDataJob` procesa el CV, el `structured_data` extraído es:

```json
{
  "current_role": "Desarrollador Full Stack",
  "english_level": "B1",
  "education_level": "bachelor",
  "total_years_experience": 8,
  "laravel_experience_years": 6,
  "php_experience_years": 6,
  "aws_experience_years": 6,
  "docker_experience_years": 4,
  "nodejs_experience_years": 8,
  "react_experience_years": 8,
  "javascript_experience_years": 8,
  "mysql_experience_years": 6,
  "postgresql_experience_years": 6,
  "graphql_experience_years": 4,
  "has_remote_experience": true,
  "has_team_lead_experience": true,
  "has_api_design_experience": true,
  "has_leadership_experience": true
}
```

### Paso 3 — Solicitar el scoring

```bash
curl -X POST http://localhost:8000/api/v1/score \
  -H "Content-Type: application/json" \
  -d @requests/3_score.json
```

### Paso 4 — Resultado final

```bash
curl http://localhost:8000/api/v1/score/1
```

**Score: 55/100**

```json
{
  "score": 55,
  "breakdown": [
    {
      "criterion": "Laravel Experience Years",
      "key": "laravel_experience_years",
      "weight": 28, "required": true,
      "result": "match",
      "points": 28,
      "evidence": "6 year(s) found (required: 3+)",
      "confidence": 0.9
    },
    {
      "criterion": "PHP Version",
      "key": "php_version",
      "weight": 18, "required": true,
      "result": "match",
      "points": 18,
      "evidence": "Level 'PHP 8' found (required: 'php 8+')",
      "confidence": 0.85
    },
    {
      "criterion": "Cloud Experience (AWS/GCP)",
      "key": "cloud_experience",
      "weight": 15, "required": false,
      "result": "match",
      "points": 15,
      "evidence": "Level 'AWS' found (required: 'basic')",
      "confidence": 0.85
    },
    {
      "criterion": "Docker Experience",
      "key": "docker_experience",
      "weight": 8, "required": false,
      "result": "match",
      "points": 8,
      "evidence": "Present in CV",
      "confidence": 1.0
    },
    {
      "criterion": "Webhook Experience",
      "key": "webhook_experience",
      "weight": 5, "required": false,
      "result": "match",
      "points": 5,
      "evidence": "Present in CV",
      "confidence": 1.0
    },
    {
      "criterion": "Vue.js Experience Years",
      "key": "vue_js_experience_years",
      "weight": 25, "required": true,
      "result": "unknown",
      "points": 0,
      "evidence": "Not found in CV",
      "confidence": 0
    },
    {
      "criterion": "AI Integration Experience",
      "key": "ai_integration_experience",
      "weight": 30, "required": true,
      "result": "unknown",
      "points": 0,
      "evidence": "Not found in CV",
      "confidence": 0
    },
    {
      "criterion": "Python Scripting Experience",
      "key": "python_scripting_experience",
      "weight": 5, "required": false,
      "result": "unknown",
      "points": 0,
      "evidence": "Not found in CV",
      "confidence": 0
    }
  ],
  "gaps": []
}
```

### Análisis del resultado

**Score: 55/100** — Perfil backend sólido con brechas en las habilidades frontend e IA del puesto.

| Fortaleza | Evidencia |
|-----------|-----------|
| Laravel 6 años (requisito: 3+) | match completo |
| PHP 8 confirmado | match completo |
| AWS/Cloud 6 años | match completo |
| Docker 4 años | match completo |

| Brecha | Motivo |
|--------|--------|
| Vue.js (requerido, peso 25) | El CV usa React, no Vue.js |
| AI Integration (requerido, peso 30) | La extracción no capturó integración de LLMs explícitamente aunque el CV lo menciona |
| Python (opcional, peso 5) | No presente en el CV |

**Nota sobre `unknown` vs `no_match`:** Los criterios marcados como `unknown` indican que la extracción estructurada del CV no produjo una clave coincidente, no necesariamente que el candidato no tenga esa habilidad. La integración de IA es un ejemplo claro: el CV menciona explícitamente "integración de servicios de IA mediante APIs (LLMs)" pero el extractor no generó una clave `has_ai_integration_experience`. Esto es un trade-off conocido de la extracción job-agnóstica.

---

## Estructura del Proyecto

```
app/
├── Enums/
│   ├── CriterionPriority.php   # high / medium / low + defaultWeight()
│   ├── CriterionType.php       # boolean / years / enum / score_1_5
│   ├── MatchResult.php         # match / no_match / unknown
│   └── ProcessingStatus.php    # pending / processing / completed / failed
├── Exceptions/
│   └── AIProviderException.php # Wrappea errores de OpenAI con isRetryable()
├── Http/
│   ├── Controllers/API/        # Controllers delgados
│   ├── Requests/               # Validación de input
│   └── Resources/              # Formato del output de la API
├── Jobs/
│   ├── GenerateCriteriaJob.php # Despacha generación de criterios con IA
│   ├── ExtractCVDataJob.php    # Despacha extracción estructurada del CV
│   └── ScoreCandidateJob.php   # Ejecuta el scoring determinístico
├── Models/
│   ├── JobOffer.php
│   ├── Criterion.php
│   ├── CandidateCV.php
│   └── ScoringResult.php
├── Providers/
│   └── AppServiceProvider.php  # Vincula AIServiceInterface → OpenAIService
└── Services/
    ├── AI/
    │   ├── AIServiceInterface.php
    │   └── OpenAIService.php
    ├── CriteriaGenerationService.php
    ├── CVExtractionService.php
    ├── ScoringService.php              # Puro y determinístico — sin IA
    ├── JobOfferService.php
    ├── CVService.php                   # Lógica de deduplicación
    └── ScoreOrchestrationService.php
```
"# Challenge-Talently" 
