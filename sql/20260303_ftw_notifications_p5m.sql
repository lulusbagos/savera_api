BEGIN;

CREATE TABLE IF NOT EXISTS public.tbl_t_user_notification (
    id bigserial PRIMARY KEY,
    company_id integer NOT NULL REFERENCES public.tbl_m_company(id) ON DELETE CASCADE,
    username varchar(120) NOT NULL,
    title varchar(200) NOT NULL,
    message text NOT NULL,
    kind varchar(40) NOT NULL DEFAULT 'info',
    status smallint NOT NULL DEFAULT 0,
    payload jsonb NULL,
    created_by integer NULL REFERENCES public.tbl_m_user(id) ON DELETE SET NULL,
    read_at timestamp NULL,
    created_at timestamp NOT NULL DEFAULT now(),
    updated_at timestamp NOT NULL DEFAULT now(),
    deleted_at timestamp NULL,
    CONSTRAINT ck_tbl_t_user_notification_status CHECK (status IN (0, 1))
);

CREATE INDEX IF NOT EXISTS idx_tbl_t_user_notification_company_user_time
    ON public.tbl_t_user_notification(company_id, username, created_at DESC)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_tbl_t_user_notification_unread
    ON public.tbl_t_user_notification(company_id, username, status, created_at DESC)
    WHERE deleted_at IS NULL;

CREATE TABLE IF NOT EXISTS public.tbl_m_ftw_manual_access (
    id bigserial PRIMARY KEY,
    company_id integer NOT NULL REFERENCES public.tbl_m_company(id) ON DELETE CASCADE,
    username varchar(120) NOT NULL,
    employee_id integer NULL REFERENCES public.tbl_r_employee(id) ON DELETE SET NULL,
    nik varchar(64) NULL,
    require_p5m boolean NOT NULL DEFAULT true,
    is_active boolean NOT NULL DEFAULT true,
    note text NULL,
    created_by integer NULL REFERENCES public.tbl_m_user(id) ON DELETE SET NULL,
    updated_by integer NULL REFERENCES public.tbl_m_user(id) ON DELETE SET NULL,
    created_at timestamp NOT NULL DEFAULT now(),
    updated_at timestamp NOT NULL DEFAULT now(),
    deleted_at timestamp NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_tbl_m_ftw_manual_access_company_username
    ON public.tbl_m_ftw_manual_access(company_id, lower(username))
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_tbl_m_ftw_manual_access_company_active
    ON public.tbl_m_ftw_manual_access(company_id, is_active);

CREATE TABLE IF NOT EXISTS public.tbl_t_p5m_checkpoint (
    id bigserial PRIMARY KEY,
    company_id integer NOT NULL REFERENCES public.tbl_m_company(id) ON DELETE CASCADE,
    employee_id integer NULL REFERENCES public.tbl_r_employee(id) ON DELETE SET NULL,
    username varchar(120) NOT NULL,
    nik varchar(64) NULL,
    record_date date NOT NULL,
    score integer NULL,
    max_score integer NULL,
    percentage integer NULL,
    source varchar(40) NOT NULL DEFAULT 'mobile_quiz',
    payload jsonb NULL,
    submitted_at timestamp NOT NULL DEFAULT now(),
    created_at timestamp NOT NULL DEFAULT now(),
    updated_at timestamp NOT NULL DEFAULT now(),
    deleted_at timestamp NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_tbl_t_p5m_checkpoint_company_user_date
    ON public.tbl_t_p5m_checkpoint(company_id, lower(username), record_date)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_tbl_t_p5m_checkpoint_company_date
    ON public.tbl_t_p5m_checkpoint(company_id, record_date DESC)
    WHERE deleted_at IS NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_set_updated_at_tbl_t_user_notification') THEN
        CREATE TRIGGER trg_set_updated_at_tbl_t_user_notification
        BEFORE UPDATE ON public.tbl_t_user_notification
        FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_set_updated_at_tbl_m_ftw_manual_access') THEN
        CREATE TRIGGER trg_set_updated_at_tbl_m_ftw_manual_access
        BEFORE UPDATE ON public.tbl_m_ftw_manual_access
        FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_set_updated_at_tbl_t_p5m_checkpoint') THEN
        CREATE TRIGGER trg_set_updated_at_tbl_t_p5m_checkpoint
        BEFORE UPDATE ON public.tbl_t_p5m_checkpoint
        FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();
    END IF;
END
$$;

COMMIT;
