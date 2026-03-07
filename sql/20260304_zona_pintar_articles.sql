BEGIN;

CREATE TABLE IF NOT EXISTS public.tbl_t_zona_pintar_article (
    id bigserial PRIMARY KEY,
    company_id integer NOT NULL REFERENCES public.tbl_m_company(id) ON DELETE CASCADE,
    title varchar(240) NOT NULL,
    description text NULL,
    content text NOT NULL,
    category varchar(120) NULL,
    image_url varchar(500) NULL,
    article_link varchar(500) NULL,
    sort_order integer NOT NULL DEFAULT 100,
    is_active boolean NOT NULL DEFAULT true,
    published_at timestamp NULL DEFAULT now(),
    created_by integer NULL REFERENCES public.tbl_m_user(id) ON DELETE SET NULL,
    updated_by integer NULL REFERENCES public.tbl_m_user(id) ON DELETE SET NULL,
    created_at timestamp NOT NULL DEFAULT now(),
    updated_at timestamp NOT NULL DEFAULT now(),
    deleted_at timestamp NULL
);

CREATE INDEX IF NOT EXISTS idx_tbl_t_zona_pintar_article_company_active_sort
    ON public.tbl_t_zona_pintar_article(company_id, is_active, sort_order, published_at DESC)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_tbl_t_zona_pintar_article_company_time
    ON public.tbl_t_zona_pintar_article(company_id, created_at DESC)
    WHERE deleted_at IS NULL;

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_proc WHERE proname = 'fn_set_updated_at') THEN
        IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_set_updated_at_tbl_t_zona_pintar_article') THEN
            CREATE TRIGGER trg_set_updated_at_tbl_t_zona_pintar_article
            BEFORE UPDATE ON public.tbl_t_zona_pintar_article
            FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();
        END IF;
    END IF;
END
$$;

COMMIT;
