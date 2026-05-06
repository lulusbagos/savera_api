<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\MobileNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MobileNotificationController extends Controller
{
    public function index(Request $request)
    {
        [$company, $employee] = $this->resolveContext($request);
        if (! $company || ! $employee) {
            return $this->missingContextResponse($company, $employee);
        }

        $query = MobileNotification::query()
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->where('username', $request->user()->name)
            ->where(function ($inner) {
                $inner->whereNull('published_at')
                    ->orWhere('published_at', '<=', Carbon::now());
            })
            ->where(function ($inner) {
                $windowStart = Carbon::now()->subDays(30);
                $inner
                    ->where(function ($q) use ($windowStart) {
                        $q->whereNotNull('published_at')
                            ->where('published_at', '>=', $windowStart);
                    })
                    ->orWhere(function ($q) use ($windowStart) {
                        $q->whereNull('published_at')
                            ->where('created_at', '>=', $windowStart);
                    });
            })
            ->orderByRaw('CASE WHEN status = 0 THEN 0 ELSE 1 END')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $rows = $query->limit(30)->get()->map(function (MobileNotification $row) {
            $payload = is_array($row->payload_json) ? $row->payload_json : [];
            $content = $this->buildNotificationContent($row, $payload);

            return [
                'id' => $row->id,
                'title' => $row->title,
                'message_html' => $content['html'],
                'message_html_full' => $this->wrapNotificationHtml($content['html'], $content['theme']),
                'message_css' => $this->notificationCss($content['theme']),
                'message' => $content['plain'],
                'content_format' => 'html_css',
                'rendering' => [
                    'mode' => 'webview',
                    'supports_css' => true,
                    'css_scope' => 'savera-notification-v1',
                ],
                'source_type' => $row->source_type,
                'status' => (int) $row->status,
                'published_at' => optional($row->published_at ?? $row->created_at)->toISOString(),
                'read_at' => optional($row->read_at)->toISOString(),
            ];
        })->values();

        return response([
            'message' => 'ok',
            'data' => $rows->all(),
            'meta' => [
                'unread_count' => $rows->where('status', 0)->count(),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{html: string, plain: string, theme: string}
     */
    private function buildNotificationContent(MobileNotification $row, array $payload): array
    {
        if (($row->source_type ?? '') === 'attendance_inout' && ! empty($payload)) {
            return $this->buildAttendanceNotificationContent($payload);
        }

        $messageHtml = $this->sanitizeNotificationHtml((string) ($row->message_html ?? ''));
        if ($messageHtml === '') {
            $messageHtml = '<p>-</p>';
        }

        $html = '<div class="savera-notification savera-notification--manual">'
            . '<div class="sn-card sn-card--manual">'
            . '<div class="sn-rich">' . $messageHtml . '</div>'
            . '</div>'
            . '</div>';

        return [
            'html' => $html,
            'plain' => trim(strip_tags($messageHtml)),
            'theme' => 'manual',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{html: string, plain: string, theme: string}
     */
    private function buildAttendanceNotificationContent(array $payload): array
    {
        $nik = trim((string) ($payload['nik'] ?? ''));
        $jamIn = trim((string) ($payload['jam_in'] ?? ''));
        $jamOut = trim((string) ($payload['jam_out'] ?? ''));
        $ipIn = trim((string) ($payload['ip_in'] ?? ''));
        $ipOut = trim((string) ($payload['ip_out'] ?? ''));
        $printerIn = trim((string) ($payload['nama_printer_in'] ?? ''));
        $printerOut = trim((string) ($payload['nama_printer_out'] ?? ''));

        $tanggalRaw = trim((string) ($payload['tanggal'] ?? ''));
        $tanggal = '-';
        if ($tanggalRaw !== '') {
            try {
                $tanggal = Carbon::parse($tanggalRaw)->format('d/m/Y');
            } catch (\Throwable) {
                $tanggal = $tanggalRaw;
            }
        }

        $safeNik = e($nik !== '' ? $nik : '-');
        $safeTanggal = e($tanggal);
        $safeJamIn = e($jamIn !== '' ? $jamIn : '-');
        $safeJamOut = e($jamOut !== '' ? $jamOut : '-');
        $safeIpIn = e($ipIn !== '' ? $ipIn : '-');
        $safeIpOut = e($ipOut !== '' ? $ipOut : '-');
        $safePrinterIn = e($printerIn !== '' ? $printerIn : '-');
        $safePrinterOut = e($printerOut !== '' ? $printerOut : '-');

        $html = <<<HTML
<div class="savera-notification savera-notification--attendance">
    <div class="sn-card">
        <div class="sn-topline">
            <span class="sn-badge">ABSENSI</span>
            <span class="sn-status-dot">Harian</span>
        </div>
        <div class="sn-title">Ringkasan In/Out Karyawan</div>
        <div class="sn-subtitle">Data absensi 30 hari terakhir dari sistem attendance.</div>
        <div class="sn-grid">
            <div class="sn-field"><span>NIK</span><strong>{$safeNik}</strong></div>
            <div class="sn-field"><span>Tanggal</span><strong>{$safeTanggal}</strong></div>
            <div class="sn-field sn-in"><span>Jam In</span><strong>{$safeJamIn}</strong></div>
            <div class="sn-field sn-out"><span>Jam Out</span><strong>{$safeJamOut}</strong></div>
        </div>
        <div class="sn-route">
            <div><span>IP In</span><strong>{$safeIpIn}</strong></div>
            <div><span>IP Out</span><strong>{$safeIpOut}</strong></div>
        </div>
        <div class="sn-machine">
            <span>Lokasi Mesin</span>
            <strong>{$safePrinterIn} / {$safePrinterOut}</strong>
        </div>
    </div>
</div>
HTML;

        $plain = implode("\n", [
            'Ringkasan In/Out Karyawan',
            'NIK      : ' . ($nik !== '' ? $nik : '-'),
            'Tanggal  : ' . $tanggal,
            'Jam In   : ' . ($jamIn !== '' ? $jamIn : '-'),
            'Jam Out  : ' . ($jamOut !== '' ? $jamOut : '-'),
            'IP       : ' . ($ipIn !== '' ? $ipIn : '-') . ' / ' . ($ipOut !== '' ? $ipOut : '-'),
            'Mesin    : ' . ($printerIn !== '' ? $printerIn : '-') . ' / ' . ($printerOut !== '' ? $printerOut : '-'),
        ]);

        return [
            'html' => trim($html),
            'plain' => $plain,
            'theme' => 'attendance',
        ];
    }

    private function wrapNotificationHtml(string $bodyHtml, string $theme = 'manual'): string
    {
        $css = $this->notificationCss($theme);

        return <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <style>{$css}</style>
</head>
<body>
{$bodyHtml}
</body>
</html>
HTML;
    }

    private function notificationCss(string $theme = 'manual'): string
    {
        $accent = $theme === 'attendance' ? '#16a34a' : '#0ea5e9';
        $accentDark = $theme === 'attendance' ? '#15803d' : '#0369a1';
        $soft = $theme === 'attendance' ? '#ecfdf5' : '#eff6ff';
        $line = $theme === 'attendance' ? '#bbf7d0' : '#bfdbfe';

        return <<<CSS
*{box-sizing:border-box}
html,body{margin:0;padding:0;background:transparent;color:#0f172a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}
body{font-size:13px;line-height:1.45}
.savera-notification{width:100%;padding:0}
.sn-card{position:relative;overflow:hidden;border:1px solid {$line};border-radius:18px;padding:14px;background:linear-gradient(135deg,#ffffff 0%,{$soft} 100%);box-shadow:0 14px 28px rgba(15,23,42,.08)}
.sn-card:before{content:"";position:absolute;right:-34px;top:-42px;width:118px;height:118px;border-radius:999px;background:radial-gradient(circle,rgba(14,165,233,.16),transparent 62%);pointer-events:none}
.savera-notification--attendance .sn-card:before{background:radial-gradient(circle,rgba(22,163,74,.18),transparent 62%)}
.sn-topline{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;position:relative;z-index:1}
.sn-badge{display:inline-flex;align-items:center;border-radius:999px;background:{$accent};color:#fff;padding:5px 10px;font-size:10px;font-weight:800;letter-spacing:.08em}
.sn-status-dot{display:inline-flex;align-items:center;border-radius:999px;background:#fff;color:{$accentDark};border:1px solid {$line};padding:4px 9px;font-size:10px;font-weight:800}
.sn-title{font-size:15px;font-weight:900;color:#0f172a;margin-bottom:3px;position:relative;z-index:1}
.sn-subtitle{font-size:11px;color:#64748b;margin-bottom:12px;position:relative;z-index:1}
.sn-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-bottom:10px;position:relative;z-index:1}
.sn-field,.sn-route div,.sn-machine{border:1px solid rgba(148,163,184,.28);border-radius:14px;background:rgba(255,255,255,.78);padding:10px}
.sn-field span,.sn-route span,.sn-machine span{display:block;color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;margin-bottom:3px}
.sn-field strong,.sn-route strong,.sn-machine strong{display:block;color:#0f172a;font-size:13px;font-weight:900;word-break:break-word}
.sn-in strong{color:#15803d}
.sn-out strong{color:#b45309}
.sn-route{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-bottom:10px;position:relative;z-index:1}
.sn-machine{position:relative;z-index:1}
.sn-rich{position:relative;z-index:1;color:#172033;font-size:13px}
.sn-rich p{margin:0 0 10px}
.sn-rich h1,.sn-rich h2,.sn-rich h3{margin:0 0 10px;line-height:1.18;color:#0f172a}
.sn-rich a{color:#0369a1;font-weight:700;text-decoration:none}
.sn-rich table{width:100%;border-collapse:collapse;margin:8px 0;border-radius:12px;overflow:hidden}
.sn-rich th,.sn-rich td{border:1px solid #e2e8f0;padding:8px;text-align:left}
.sn-rich img{max-width:100%;height:auto;border-radius:12px}
@media(max-width:360px){.sn-grid,.sn-route{grid-template-columns:1fr}.sn-card{padding:12px;border-radius:16px}}
CSS;
    }

    private function sanitizeNotificationHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('/<(script|iframe|object|embed|form|input|button|meta|link|base)[^>]*>[\s\S]*?<\/\1>/i', '', $html) ?? '';
        $html = preg_replace('/<(script|iframe|object|embed|form|input|button|meta|link|base)\b[^>]*\/?>/i', '', $html) ?? '';
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/javascript\s*:/i', '#', $html) ?? '';
        $html = preg_replace('/expression\s*\(/i', 'blocked(', $html) ?? '';
        $html = preg_replace('/url\s*\(\s*[\'"]?\s*javascript\s*:/i', 'url(#', $html) ?? '';

        return trim($html);
    }

    public function read(Request $request, int $id)
    {
        [$company, $employee] = $this->resolveContext($request);
        if (! $company || ! $employee) {
            return $this->missingContextResponse($company, $employee);
        }

        $notification = MobileNotification::query()
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->where('username', $request->user()->name)
            ->whereKey($id)
            ->first();

        if (! $notification) {
            return response([
                'message' => 'Notification not found.',
            ], 404);
        }

        if ((int) $notification->status === 0) {
            $notification->status = 1;
            $notification->read_at = Carbon::now();
            $notification->save();
        }

        return response([
            'message' => 'Successfully updated',
            'data' => [
                'id' => $notification->id,
                'status' => (int) $notification->status,
                'read_at' => optional($notification->read_at)->toISOString(),
            ],
        ]);
    }

    /**
     * @return array{0: Company|null, 1: Employee|null}
     */
    private function resolveContext(Request $request): array
    {
        $userCompanyId = Employee::query()
            ->where('user_id', $request->user()->id)
            ->value('company_id');

        $company = null;
        $companyCode = strtoupper(trim((string) $request->header('company', '')));
        if ($companyCode !== '') {
            $companies = Company::query()
                ->select(['id', 'code'])
                ->whereRaw('UPPER(code) = ?', [$companyCode])
                ->orderBy('id')
                ->get();

            if ($companies->isNotEmpty()) {
                if ($userCompanyId) {
                    $company = $companies->firstWhere('id', (int) $userCompanyId);
                }
                if (! $company) {
                    $company = $companies->first();
                }
            }
        }

        if (! $company && $userCompanyId) {
            $company = Company::query()
                ->select(['id', 'code'])
                ->whereKey($userCompanyId)
                ->first();
        }

        if (! $company) {
            $company = Company::query()
                ->select(['id', 'code'])
                ->where('status', 1)
                ->orderBy('id')
                ->first();
        }

        if (! $company) {
            return [null, null];
        }

        $employee = Employee::query()
            ->select(['id', 'company_id', 'user_id'])
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return [$company, $employee];
    }

    private function missingContextResponse(?Company $company, ?Employee $employee)
    {
        if (! $company) {
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        if (! $employee) {
            return response([
                'message' => 'Employee not found.',
            ], 404);
        }

        return response([
            'message' => 'Context not found.',
        ], 404);
    }
}
