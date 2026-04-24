<?php

namespace App\Http\Controllers;

use App\Support\MobileIngestRuntime;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;

class GoogleController extends Controller
{
    public function spreadSheets(Request $request)
    {
        $spreadSheetId = $request->input('id', '17ErygVpQVS1H6Ze5XwdO5BQrHBHFFJq_WPj5gHhnOE4');
        $companyId = $request->input('company', 2);
        $day = $request->input('day', date('j'));
        $date = date('Y-m-') . sprintf('%02d', $day);

        $data = Cache::store(MobileIngestRuntime::cacheStore('file'))->remember('spreadsheets-' . $companyId . '-' . $date, now()->addMinutes(15), function () use ($spreadSheetId, $companyId, $day, $date) {
            $client = new \Google_Client();
            $client->setApplicationName('Google Sheets API');
            $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
            $client->setAccessType('offline');
            $client->setAuthConfig('E:/apps/savera-api/storage/my-spreadsheet.json');

            $service = new \Google_Service_Sheets($client);
            $response = $service->spreadsheets_values->get($spreadSheetId, $day);
            $rows = $response->getValues();
            $headers = array_shift($rows);
            $keys = [
                'no',
                strtolower(str_replace(' ', '_', $headers[0] ?? '')),
                strtolower(str_replace(' ', '_', $headers[1] ?? '')),
                strtolower(str_replace(' ', '_', $headers[2] ?? '')),
                strtolower(str_replace(' ', '_', $headers[3] ?? '')),
                strtolower(str_replace(' ', '_', $headers[4] ?? '')),
                strtolower(str_replace(' ', '_', $headers[5] ?? '')),
                strtolower(str_replace(' ', '_', $headers[6] ?? '')),
                strtolower(str_replace(' ', '_', $headers[7] ?? '')),
                strtolower(str_replace(' ', '_', $headers[8] ?? '')),
                strtolower(str_replace(' ', '_', $headers[9] ?? '')),
                strtolower(str_replace(' ', '_', $headers[10] ?? '')),
                strtolower(str_replace(' ', '_', $headers[11] ?? '')),
                strtolower(str_replace(' ', '_', $headers[12] ?? '')),
                strtolower(str_replace(' ', '_', $headers[13] ?? '')),
                'company_id',
                'updated_at',
            ];

            $updatedAt = Carbon::now();
            $arr = [];
            foreach ($rows as $key => $row) {
                $vals = [
                    $key + 1,
                    $date,
                    $row[1] ?? '',
                    $row[2] ?? '',
                    $row[3] ?? '',
                    $row[4] ?? '',
                    $row[5] ?? '',
                    $row[6] ?? '',
                    $row[7] ?? '',
                    $row[8] ?? '',
                    $row[9] ?? '',
                    $row[10] ?? '',
                    $row[11] ?? '',
                    $row[12] ?? '',
                    $row[13] ?? '',
                    $companyId,
                    $updatedAt,
                ];
                if ($vals[2] != '' && $vals[3] != '' && $vals[4] != '') {
                    $item = array_combine($keys, $vals);
                    // Buang key kosong/underscore agar tidak memicu error kolom tidak dikenal.
                    $item = Arr::except($item, ['', '_']);
                    // Pastikan kolom wajib tidak null untuk hindari constraint error.
                    $item['tanggal'] = $date;
                    $item['company_id'] = $companyId;
                    $item['updated_at'] = $updatedAt;
                    $arr[] = $item;
                }
            }

            if (count($arr) > 0) {
                DB::table('lineup_operator')->where('tanggal', $date)->delete();
                DB::table('lineup_operator')->insert($arr);
            }

            return $arr;
        });

        return response($data);
    }
}
