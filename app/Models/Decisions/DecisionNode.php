<?php

namespace App\Models\Decisions;

use Illuminate\Database\Eloquent\Model;

class DecisionNode extends Model
{
    protected $fillable = [
        'decision_path_id', 'node_key', 'dynamic_question_template_id', 'order',
        'branches', 'risk_weight', 'node_type', 'outcome_message', 'specialties'
    ];

    protected $casts = [
        'branches' => 'array',
        'specialties' => 'array',
    ];

    public function path()
    {
        return $this->belongsTo(DecisionPath::class, 'decision_path_id');
    }

    public function question()
    {
        return $this->belongsTo(DynamicQuestionTemplate::class, 'dynamic_question_template_id');
    }

    /**
 * Renvoie un payload complet à partir d'un node du DecisionPath,
 * soit pour une question dynamique, soit pour un node de fin.
 *
 * @return array|null
 */
public function getQuestionPayload()
{
    // Si ce node correspond à une question dynamique
    if ($this->dynamic_question_template_id) {
        $q = $this->question;
        if (!$q) {
            // Cas rare : la question liée a été supprimée, ou id cassé
            return null;
        }

        return [
            'id'          => $q->id,
            'key'         => $q->key,
            'text'        => $q->text,
            'type'        => $q->type,
            'bloc_key'    => $q->bloc_key,
            'answer_target' => $q->answer_target,
            'order'       => $q->order,
            'risk_weight' => $q->risk_weight,
            'min'         => $q->min,
            'max'         => $q->max,
            'media_type'  => $q->media_type,
            'media_url'   => $q->media_url,
            // Décodage JSON (options et visible_if)
            'options'     => $q->options ? json_decode($q->options, true) : null,
            'visible_if'  => $q->visible_if ? json_decode($q->visible_if, true) : null,
            // Pour la compatibilité avec l’API front :
            'question_id' => $q->id,
            'node_id'     => $this->id,
        ];
    }

    // Si c’est un node de fin/recommandation sans question liée
    if ($this->node_type === 'end' || $this->node_type === 'recommendation') {
        return [
            'node_id'        => $this->id,
            'node_type'      => $this->node_type,
            'outcome_message'=> $this->outcome_message,
            'risk_weight'    => $this->risk_weight,
            'specialties'    => $this->specialties ? json_decode($this->specialties, true) : null,
        ];
    }

    // Pour tous les autres types de nodes (au cas où)
    return [
        'node_id'   => $this->id,
        'node_type' => $this->node_type,
    ];
}
}