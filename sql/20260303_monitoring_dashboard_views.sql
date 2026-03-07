BEGIN;

CREATE SCHEMA IF NOT EXISTS monitoring;

CREATE OR REPLACE VIEW monitoring.v_upload_status_5m AS
SELECT
    request_type,
    CASE
        WHEN status_code BETWEEN 200 AND 299 THEN 'success'
        WHEN status_code = 499 THEN 'client_cancel'
        WHEN status_code BETWEEN 400 AND 499 THEN 'client_error'
        WHEN status_code >= 500 THEN 'server_error'
        ELSE 'unknown'
    END AS status_group,
    COUNT(*) AS total,
    COALESCE(ROUND(AVG(duration_ms)::numeric, 2), 0) AS avg_duration_ms,
    MAX(created_at) AS last_event_at
FROM public.tbl_t_upload_log
WHERE created_at >= now() - interval '5 minutes'
GROUP BY request_type,
         CASE
             WHEN status_code BETWEEN 200 AND 299 THEN 'success'
             WHEN status_code = 499 THEN 'client_cancel'
             WHEN status_code BETWEEN 400 AND 499 THEN 'client_error'
             WHEN status_code >= 500 THEN 'server_error'
             ELSE 'unknown'
         END;

CREATE OR REPLACE VIEW monitoring.v_upload_success_rate_5m AS
SELECT
    request_type,
    COUNT(*) AS total,
    COUNT(*) FILTER (WHERE status_code BETWEEN 200 AND 299) AS success_total,
    COALESCE(
        ROUND(
            (COUNT(*) FILTER (WHERE status_code BETWEEN 200 AND 299)::numeric * 100.0)
            / NULLIF(COUNT(*), 0),
            2
        ),
        0
    ) AS success_rate_pct,
    COALESCE(ROUND(percentile_disc(0.95) WITHIN GROUP (ORDER BY duration_ms)::numeric, 2), 0) AS p95_duration_ms
FROM public.tbl_t_upload_log
WHERE created_at >= now() - interval '5 minutes'
GROUP BY request_type;

CREATE OR REPLACE VIEW monitoring.v_top_errors_1h AS
SELECT
    request_type,
    COALESCE(error_type, 'none') AS error_type,
    LEFT(COALESCE(error_message, 'none'), 180) AS error_message_short,
    COUNT(*) AS total,
    MAX(created_at) AS last_seen_at
FROM public.tbl_t_upload_log
WHERE created_at >= now() - interval '1 hour'
  AND status_code >= 400
GROUP BY request_type, COALESCE(error_type, 'none'), LEFT(COALESCE(error_message, 'none'), 180)
ORDER BY total DESC, last_seen_at DESC;

CREATE OR REPLACE VIEW monitoring.v_sideeffect_errors_1h AS
SELECT
    request_type,
    COUNT(*) AS total,
    MAX(created_at) AS last_seen_at
FROM public.tbl_t_upload_log
WHERE created_at >= now() - interval '1 hour'
  AND request_type LIKE '%_sideeffect_%'
  AND status_code >= 400
GROUP BY request_type
ORDER BY total DESC;

CREATE OR REPLACE VIEW monitoring.v_queue_backlog AS
SELECT
    status,
    COUNT(*) AS total,
    MIN(created_at) AS oldest_created_at,
    MIN(next_retry_at) AS oldest_next_retry_at,
    MAX(updated_at) AS newest_updated_at
FROM public.tbl_t_upload_file_queue
GROUP BY status
ORDER BY status;

CREATE OR REPLACE VIEW monitoring.v_queue_pending_oldest AS
SELECT
    id,
    request_type,
    request_key,
    employee_id,
    record_date,
    attempts,
    max_attempts,
    next_retry_at,
    created_at,
    updated_at,
    LEFT(COALESCE(last_error, ''), 200) AS last_error_short
FROM public.tbl_t_upload_file_queue
WHERE status IN ('pending', 'failed')
ORDER BY created_at
LIMIT 100;

CREATE OR REPLACE VIEW monitoring.v_network_quality_1h AS
SELECT
    COALESCE(network_transport, 'unknown') AS network_transport,
    COUNT(*) AS total,
    COUNT(*) FILTER (WHERE is_api_reachable = true) AS api_reachable_total,
    COUNT(*) FILTER (WHERE is_api_slow = true) AS api_slow_total,
    COALESCE(ROUND(AVG(latency_ms)::numeric, 2), 0) AS avg_latency_ms,
    COALESCE(ROUND(percentile_disc(0.95) WITHIN GROUP (ORDER BY latency_ms)::numeric, 2), 0) AS p95_latency_ms,
    MAX(created_at) AS last_seen_at
FROM public.tbl_t_network_probe
WHERE created_at >= now() - interval '1 hour'
GROUP BY COALESCE(network_transport, 'unknown')
ORDER BY total DESC;

CREATE OR REPLACE VIEW monitoring.v_summary_detail_gap_today AS
WITH s AS (
    SELECT
        company_id,
        send_date,
        COUNT(*) AS summary_total,
        COUNT(DISTINCT upload_key) FILTER (WHERE upload_key IS NOT NULL AND upload_key <> '') AS summary_upload_key_total
    FROM public.tbl_t_summary
    WHERE deleted_at IS NULL
      AND send_date = CURRENT_DATE
    GROUP BY company_id, send_date
),
d AS (
    SELECT
        company_id,
        record_date,
        COUNT(*) AS detail_total,
        COUNT(DISTINCT upload_key) FILTER (WHERE upload_key IS NOT NULL AND upload_key <> '') AS detail_upload_key_total
    FROM public.tbl_t_summary_detail
    WHERE deleted_at IS NULL
      AND record_date = CURRENT_DATE
    GROUP BY company_id, record_date
)
SELECT
    COALESCE(s.company_id, d.company_id) AS company_id,
    COALESCE(s.send_date, d.record_date) AS record_date,
    COALESCE(s.summary_total, 0) AS summary_total,
    COALESCE(d.detail_total, 0) AS detail_total,
    COALESCE(s.summary_upload_key_total, 0) AS summary_upload_key_total,
    COALESCE(d.detail_upload_key_total, 0) AS detail_upload_key_total,
    COALESCE(s.summary_upload_key_total, 0) - COALESCE(d.detail_upload_key_total, 0) AS upload_key_gap
FROM s
FULL OUTER JOIN d
  ON s.company_id = d.company_id
 AND s.send_date = d.record_date;

COMMIT;
