BEGIN;

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

CREATE INDEX IF NOT EXISTS idx_tbl_m_ftw_manual_access_company_active_username
    ON public.tbl_m_ftw_manual_access(company_id, lower(username))
    WHERE deleted_at IS NULL
      AND is_active = true;

CREATE INDEX IF NOT EXISTS idx_tbl_m_ftw_manual_access_company_active_employee
    ON public.tbl_m_ftw_manual_access(company_id, employee_id)
    WHERE deleted_at IS NULL
      AND is_active = true
      AND employee_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_tbl_m_ftw_manual_access_company_active_nik
    ON public.tbl_m_ftw_manual_access(company_id, lower(nik))
    WHERE deleted_at IS NULL
      AND is_active = true
      AND nik IS NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_set_updated_at_tbl_m_ftw_manual_access') THEN
        CREATE TRIGGER trg_set_updated_at_tbl_m_ftw_manual_access
        BEFORE UPDATE ON public.tbl_m_ftw_manual_access
        FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();
    END IF;
END
$$;

COMMIT;
