# Condition Contract (Seeders)

Version: `1.0`

This document defines the official JSON contract for `visible_if` and `routing_rules` in `DynamicQuestionTemplate` seeders.

## 1. Condition Node

A condition can be:

- a scalar: `"yes"`, `true`, `1`
- a boolean: `true` / `false`
- a logical object: `all`, `any`, `not`
- an atomic object: `source`, `key`, `operator`, `value`

### Atomic condition

```json
{
  "source": "question",
  "key": "breast_pain",
  "operator": "=",
  "value": true
}
```

### Logical condition

```json
{
  "all": [
    { "source": "question", "key": "breast_pain", "operator": "=", "value": true },
    {
      "any": [
        { "source": "profile.personal", "key": "age", "operator": ">=", "value": 25 },
        { "not": { "source": "answer", "operator": "=", "value": "no" } }
      ]
    }
  ]
}
```

## 2. Allowed `source`

- `answer`
- `question`
- `profile.medical`
- `profile.personal`
- `session`
- `context`
- `current_question`

Rule:
- `key` is required for every source except `answer`.

## 3. Allowed operators

- `=`
- `!=`
- `>`
- `<`
- `>=`
- `<=`
- `in`
- `not_in`
- `empty`
- `not_empty`
- `age_gte`
- `age_gt`
- `age_lte`
- `age_lt`
- `age_eq`

Rule:
- `value` is required except for `empty` and `not_empty`.

## 4. `visible_if` format

Preferred format:

```json
{
  "all": [
    { "source": "question", "key": "breast_pain", "operator": "=", "value": true }
  ]
}
```

Legacy format supported:

```json
[
  { "source": "question", "key": "breast_pain", "operator": "=", "value": true }
]
```

During seeding, legacy list conditions are normalized automatically to:

```json
{
  "all": [
    { "source": "question", "key": "breast_pain", "operator": "=", "value": true }
  ]
}

```
## 5. `routing_rules` format

```json
[
  {
    "if": { "source": "answer", "operator": "=", "value": "yes" },
    "next": "bloc:symptom_breast"
  },
  {
    "if": { "source": "answer", "operator": "=", "value": "no" },
    "next": "end_session"
  }
]
```

Allowed `next`:
- `bloc:<bloc_key>`
- `question:<question_key>`
- `queue:<question_key[,question_key...]>`
- `level:<level_key>`
- `end_session`

Routing semantics:
- `question:<question_key>`: immediate jump to a single target question.
- `queue:<question_key[,question_key...]>`: enqueue one or more prerequisite questions; they are consumed in order before normal sequencing resumes.

## 6. Seeder enforcement

Use `App\Domains\Evaluation\Services\QuestionTemplateContractValidator` inside every seeder that persists `DynamicQuestionTemplate`.

Recommended call:

```php
$validator->assertValid($questionPayload);
```

If invalid, seeding fails early with a clear validation error.

## 7. Cross-seeder consistency & golden paths

Commands:

```bash
php artisan evaluation:catalog-check
php artisan evaluation:golden-path
php artisan evaluation:golden-path database/fixtures/golden-paths/greeting-consent-yes.json
```

What they do:
- `evaluation:catalog-check`: validates routing targets (`next`) and cross-question references (`visible_if`) against the current catalog in DB.
- `evaluation:golden-path`: replays fixtures from `database/fixtures/golden-paths/*.json` in dry-run mode (transaction rollback) and checks expected outcomes.
