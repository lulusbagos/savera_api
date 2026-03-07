BEGIN;

ALTER TABLE public.tbl_m_user
    ADD COLUMN IF NOT EXISTS last_login_at timestamp NULL;

CREATE INDEX IF NOT EXISTS idx_tbl_m_user_last_login_at
    ON public.tbl_m_user(last_login_at DESC);

COMMIT;
