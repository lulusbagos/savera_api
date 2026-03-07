# Load Test Mobile Upload

## Quick start

```powershell
cd "D:\4. PROJECT\6. Android\API\saveralul-api\scripts"
python .\loadtest_mobile_upload.py `
  --base-urls "http://192.168.179.6:5001,http://saveraapi.ungguldinamika.com,http://saveraapi.idcapps.net" `
  --company SAVERA `
  --token "ISI_TOKEN_BEARER" `
  --users 1000 `
  --concurrency 100 `
  --mode both `
  --employee-id 1 `
  --department-id 1 `
  --shift-id 0 `
  --mac-address "AA:BB:CC:DD:EE:FF"
```

## Notes

- `--mode both` = kirim `summary` lalu `detail`.
- Endpoint fallback: urutan sesuai `--base-urls` (kiri ke kanan).
- Jika `--token` kosong, isi `--email` dan `--password` agar script login otomatis.
