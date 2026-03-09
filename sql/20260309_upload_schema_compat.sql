BEGIN;

ALTER TABLE IF EXISTS public.tbl_t_summary
  ADD COLUMN IF NOT EXISTS request_id varchar(120),
  ADD COLUMN IF NOT EXISTS upload_key varchar(120),
  ADD COLUMN IF NOT EXISTS route_base varchar(255),
  ADD COLUMN IF NOT EXISTS retry_count integer NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS upload_status varchar(20) NOT NULL DEFAULT 'accepted',
  ADD COLUMN IF NOT EXISTS last_error_message text,
  ADD COLUMN IF NOT EXISTS updated_at timestamp NOT NULL DEFAULT now();

ALTER TABLE IF EXISTS public.tbl_t_summary
  ALTER COLUMN app_version TYPE varchar(64);

CREATE UNIQUE INDEX IF NOT EXISTS uq_tbl_t_summary_company_upload_key
ON public.tbl_t_summary(company_id, upload_key)
WHERE deleted_at IS NULL
  AND upload_key IS NOT NULL
  AND upload_key <> '';

ALTER TABLE IF EXISTS public.tbl_t_summary_detail
  ADD COLUMN IF NOT EXISTS summary_id bigint,
  ADD COLUMN IF NOT EXISTS company_id integer,
  ADD COLUMN IF NOT EXISTS department_id integer,
  ADD COLUMN IF NOT EXISTS employee_id integer,
  ADD COLUMN IF NOT EXISTS shift_id integer,
  ADD COLUMN IF NOT EXISTS device_id integer,
  ADD COLUMN IF NOT EXISTS upload_key varchar(120),
  ADD COLUMN IF NOT EXISTS record_date date,
  ADD COLUMN IF NOT EXISTS device_time timestamp NULL,
  ADD COLUMN IF NOT EXISTS mac_address varchar(64),
  ADD COLUMN IF NOT EXISTS app_version varchar(64),
  ADD COLUMN IF NOT EXISTS payload_hash char(64),
  ADD COLUMN IF NOT EXISTS source varchar(40),
  ADD COLUMN IF NOT EXISTS user_activity jsonb,
  ADD COLUMN IF NOT EXISTS user_sleep jsonb,
  ADD COLUMN IF NOT EXISTS user_stress jsonb,
  ADD COLUMN IF NOT EXISTS user_respiratory_rate jsonb,
  ADD COLUMN IF NOT EXISTS user_pai jsonb,
  ADD COLUMN IF NOT EXISTS user_spo2 jsonb,
  ADD COLUMN IF NOT EXISTS user_temperature jsonb,
  ADD COLUMN IF NOT EXISTS user_cycling jsonb,
  ADD COLUMN IF NOT EXISTS user_weight jsonb,
  ADD COLUMN IF NOT EXISTS user_heart_rate_max jsonb,
  ADD COLUMN IF NOT EXISTS user_heart_rate_resting jsonb,
  ADD COLUMN IF NOT EXISTS user_heart_rate_manual jsonb,
  ADD COLUMN IF NOT EXISTS user_hrv_summary jsonb,
  ADD COLUMN IF NOT EXISTS user_hrv_value jsonb,
  ADD COLUMN IF NOT EXISTS user_body_energy jsonb,
  ADD COLUMN IF NOT EXISTS created_at timestamp NOT NULL DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at timestamp NOT NULL DEFAULT now();

ALTER TABLE IF EXISTS public.tbl_t_summary_detail
  ALTER COLUMN app_version TYPE varchar(64);

CREATE UNIQUE INDEX IF NOT EXISTS uq_tbl_t_summary_detail_upload_key
ON public.tbl_t_summary_detail(upload_key);

COMMIT;
