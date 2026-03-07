BEGIN;

ALTER TABLE public.tbl_m_api_route
ADD COLUMN IF NOT EXISTS sleep_rest_bonus_enabled boolean NOT NULL DEFAULT true;

UPDATE public.tbl_m_api_route
SET sleep_rest_bonus_enabled = true
WHERE sleep_rest_bonus_enabled IS NULL;

COMMIT;
