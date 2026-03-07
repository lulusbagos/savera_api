BEGIN;

ALTER TABLE public.tbl_m_user
    ADD COLUMN IF NOT EXISTS app_version varchar(64) NULL;

CREATE INDEX IF NOT EXISTS idx_tbl_m_user_app_version
    ON public.tbl_m_user(app_version);

COMMIT;
