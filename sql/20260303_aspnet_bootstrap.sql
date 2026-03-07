BEGIN;

CREATE TABLE IF NOT EXISTS public.tbl_t_api_token (
    id bigserial PRIMARY KEY,
    token_hash char(64) NOT NULL,
    raw_hint varchar(16) NULL,
    user_id integer NOT NULL REFERENCES public.tbl_m_user(id) ON DELETE CASCADE,
    company_id integer NULL REFERENCES public.tbl_m_company(id) ON DELETE SET NULL,
    device_info varchar(255) NULL,
    ip_address varchar(64) NULL,
    expires_at timestamp NOT NULL,
    last_used_at timestamp NULL,
    created_at timestamp NOT NULL DEFAULT now(),
    revoked_at timestamp NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_tbl_t_api_token_hash ON public.tbl_t_api_token(token_hash);
CREATE INDEX IF NOT EXISTS idx_tbl_t_api_token_user ON public.tbl_t_api_token(user_id, expires_at DESC);
CREATE INDEX IF NOT EXISTS idx_tbl_t_api_token_exp ON public.tbl_t_api_token(expires_at DESC);

CREATE TABLE IF NOT EXISTS public.tbl_t_device_authkey_log (
    id bigserial PRIMARY KEY,
    company_id integer NOT NULL REFERENCES public.tbl_m_company(id),
    device_id integer NOT NULL REFERENCES public.tbl_m_device(id),
    mac_address varchar(64) NOT NULL,
    old_auth_key varchar(255) NULL,
    new_auth_key varchar(255) NOT NULL,
    changed_by_user_id integer NULL REFERENCES public.tbl_m_user(id) ON DELETE SET NULL,
    source varchar(40) NOT NULL DEFAULT 'api',
    note text NULL,
    created_at timestamp NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_tbl_t_device_authkey_log_device_time
    ON public.tbl_t_device_authkey_log(device_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_tbl_t_device_authkey_log_mac_time
    ON public.tbl_t_device_authkey_log(mac_address, created_at DESC);

CREATE TABLE IF NOT EXISTS public.tbl_t_leave (
    id bigserial PRIMARY KEY,
    date date NOT NULL DEFAULT CURRENT_DATE,
    shift varchar(40) NULL,
    code varchar(64) NULL,
    fullname varchar(150) NULL,
    job varchar(120) NULL,
    type varchar(120) NOT NULL,
    phone varchar(64) NOT NULL,
    note text NOT NULL,
    employee_id integer NOT NULL REFERENCES public.tbl_r_employee(id) ON DELETE CASCADE,
    company_id integer NOT NULL REFERENCES public.tbl_m_company(id) ON DELETE CASCADE,
    department_id integer NULL REFERENCES public.tbl_m_department(id) ON DELETE SET NULL,
    created_at timestamp NOT NULL DEFAULT now(),
    updated_at timestamp NOT NULL DEFAULT now(),
    deleted_at timestamp NULL
);

CREATE INDEX IF NOT EXISTS idx_tbl_t_leave_employee_date
    ON public.tbl_t_leave(employee_id, date DESC);
CREATE INDEX IF NOT EXISTS idx_tbl_t_leave_company_date
    ON public.tbl_t_leave(company_id, date DESC);

CREATE TABLE IF NOT EXISTS public.tbl_m_api_route (
    id bigserial PRIMARY KEY,
    company_id integer NOT NULL REFERENCES public.tbl_m_company(id) ON DELETE CASCADE,
    primary_base_url varchar(255) NULL,
    secondary_base_url varchar(255) NULL,
    local_base_url varchar(255) NULL,
    local_ip varchar(64) NULL,
    local_port varchar(16) NULL,
    sleep_rest_bonus_enabled boolean NOT NULL DEFAULT true,
    is_active boolean NOT NULL DEFAULT true,
    created_by integer NULL REFERENCES public.tbl_m_user(id) ON DELETE SET NULL,
    updated_by integer NULL REFERENCES public.tbl_m_user(id) ON DELETE SET NULL,
    created_at timestamp NOT NULL DEFAULT now(),
    updated_at timestamp NOT NULL DEFAULT now(),
    deleted_at timestamp NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_tbl_m_api_route_company
    ON public.tbl_m_api_route(company_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_tbl_m_user_username ON public.tbl_m_user(username);
CREATE INDEX IF NOT EXISTS idx_tbl_m_user_email ON public.tbl_m_user(email);

CREATE OR REPLACE FUNCTION public.fn_set_updated_at()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_set_updated_at_tbl_t_leave') THEN
        CREATE TRIGGER trg_set_updated_at_tbl_t_leave
        BEFORE UPDATE ON public.tbl_t_leave
        FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_set_updated_at_tbl_m_api_route') THEN
        CREATE TRIGGER trg_set_updated_at_tbl_m_api_route
        BEFORE UPDATE ON public.tbl_m_api_route
        FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();
    END IF;
END
$$;

COMMIT;
