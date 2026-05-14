-- Run once on existing databases so workflow/billing values match application code.
USE petmate;

ALTER TABLE treatment_plans
  MODIFY COLUMN workflow_status ENUM(
    'in_prep',
    'forwarded',
    'administered',
    'discharged',
    'pending_billing',
    'awaiting_payment',
    'paid'
  ) DEFAULT 'in_prep';

ALTER TABLE pet_records
  MODIFY COLUMN status ENUM(
    'pending',
    'validated',
    'assessed',
    'completed',
    'rejected',
    'pending_billing',
    'awaiting_payment'
  ) DEFAULT 'pending';
