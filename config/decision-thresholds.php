<?php

return [
    'confidence' => [
        // Minimum confidence to skip human validation.
        'min' => 0.7,
        // Bonus applied when risk level is high.
        'high_bonus' => 0.05,
        // CI margin floor and scaling factor.
        'min_margin' => 0.05,
        'margin_factor' => 0.2,
    ],
    'quality' => [
        // Minimum data quality before triggering validation.
        'min' => 0.6,
        // Force review when critical answers are missing.
        'critical_missing_requires_review' => true,
    ],
    'review' => [
        // Force review when risk level is high.
        'high_risk_requires_review' => true,
    ],
    'suggested_action' => [
        'high' => 'refer_to_specialist',
        'moderate' => 'medical_check',
        'low' => 'self_care',
        'default' => 'self_care',
    ],
];
