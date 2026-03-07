-- Guard idempotency for summary uploads (non-null upload_key)
CREATE UNIQUE INDEX IF NOT EXISTS uq_tbl_t_summary_company_upload_key
ON public.tbl_t_summary(company_id, upload_key)
WHERE deleted_at IS NULL
  AND upload_key IS NOT NULL
  AND upload_key <> '';
