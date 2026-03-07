BEGIN;

CREATE TABLE IF NOT EXISTS public.tbl_t_upload_file_queue (
    id bigserial PRIMARY KEY,
    request_type varchar(40) NOT NULL,
    request_key varchar(120) NOT NULL,
    employee_id integer NOT NULL,
    record_date date NOT NULL,
    relative_path text NOT NULL,
    content text NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'pending',
    attempts integer NOT NULL DEFAULT 0,
    max_attempts integer NOT NULL DEFAULT 5,
    next_retry_at timestamp NOT NULL DEFAULT now(),
    last_error text NULL,
    created_at timestamp NOT NULL DEFAULT now(),
    updated_at timestamp NOT NULL DEFAULT now(),
    processed_at timestamp NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_tbl_t_upload_file_queue_reqkey_path
    ON public.tbl_t_upload_file_queue(request_type, request_key, relative_path);

CREATE INDEX IF NOT EXISTS idx_tbl_t_upload_file_queue_status_retry
    ON public.tbl_t_upload_file_queue(status, next_retry_at, id);

CREATE INDEX IF NOT EXISTS idx_tbl_t_upload_file_queue_created_at
    ON public.tbl_t_upload_file_queue(created_at);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_set_updated_at_tbl_t_upload_file_queue') THEN
        CREATE TRIGGER trg_set_updated_at_tbl_t_upload_file_queue
        BEFORE UPDATE ON public.tbl_t_upload_file_queue
        FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();
    END IF;
END
$$;

COMMIT;
