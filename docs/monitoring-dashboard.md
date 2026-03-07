# Monitoring Dashboard SQL

Apply view package:

```sql
\i sql/20260303_monitoring_dashboard_views.sql
```

Quick checks:

```sql
SELECT * FROM monitoring.v_upload_success_rate_5m ORDER BY request_type;
SELECT * FROM monitoring.v_upload_status_5m ORDER BY request_type, status_group;
SELECT * FROM monitoring.v_top_errors_1h LIMIT 20;
SELECT * FROM monitoring.v_sideeffect_errors_1h;
SELECT * FROM monitoring.v_queue_backlog;
SELECT * FROM monitoring.v_queue_pending_oldest LIMIT 20;
SELECT * FROM monitoring.v_network_quality_1h;
SELECT * FROM monitoring.v_summary_detail_gap_today;
```

Interpretation:

- `success_rate_pct < 95` on `summary` or `detail`: investigate immediately.
- `v_queue_backlog` pending/failed keeps growing: file writer is lagging or failing.
- `upload_key_gap` positive: detail data is behind summary ingestion.
- `api_slow_total` high + high `p95_latency_ms`: route/network degradation.
