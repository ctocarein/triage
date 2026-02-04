update dynamic_question_templates
set routing_rules='[{"if":{"value":"yes"},"next":"first_name"},{"if":{"value":"no"},"next":"end_session"}]'
where `key`='explicit_consent';