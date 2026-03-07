ALTER TABLE public.tbl_m_user
ADD COLUMN IF NOT EXISTS dcrip character varying(32) DEFAULT 'auto';

UPDATE public.tbl_m_user
SET dcrip = 'auto'
WHERE dcrip IS NULL OR btrim(dcrip) = '';

COMMENT ON COLUMN public.tbl_m_user.dcrip IS
'Password mode: auto|bcrypt|sha256|plain|aes';
