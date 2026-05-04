<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->employee->company_id ?? null;

        $articles = Article::where('type', 'Zona Operator Pintar')
            ->where('status', '!=', 0)
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($article) use ($request) {
                $imageUrl = $this->resolveImageUrl($article, $request);
                return [
                    'id'             => $article->id,
                    'title'          => $article->title,
                    'content'        => $article->content,
                    'type'           => $article->type,
                    'category'       => $article->category,
                    'author'         => $this->resolveAuthor($article),
                    'image_url'      => $imageUrl,
                    'status'         => $article->status,
                    'company_id'     => $article->company_id,
                    'published_date' => $article->published_date ?? $article->created_at?->toDateString(),
                    'created_at'     => $article->created_at,
                    'updated_at'     => $article->updated_at,
                ];
            });

        return response()->json([
            'status'  => 'success',
            'data'    => $articles,
            'total'   => $articles->count(),
        ]);
    }

    public function show($id)
    {
        $article = Article::where('status', '!=', 0)->findOrFail($id);
        $imageUrl = $this->resolveImageUrl($article, request());

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'             => $article->id,
                'title'          => $article->title,
                'content'        => $article->content,
                'type'           => $article->type,
                'category'       => $article->category,
                'author'         => $this->resolveAuthor($article),
                'image_url'      => $imageUrl,
                'status'         => $article->status,
                'company_id'     => $article->company_id,
                'published_date' => $article->published_date ?? $article->created_at?->toDateString(),
                'created_at'     => $article->created_at,
                'updated_at'     => $article->updated_at,
            ],
        ]);
    }

    public function image(Request $request, ?string $path = null)
    {
        $pathValue = $path ?? (string) $request->query('path', '');
        $cleanPath = trim(urldecode($pathValue));
        if ($cleanPath === '') {
            abort(404);
        }

        $localPath = $this->resolveLocalImagePath($cleanPath);
        if ($localPath !== null) {
            return response()->file($localPath, [
                'Cache-Control' => 'public, max-age=300',
            ]);
        }

        $sourceUrl = $this->resolveAdminImageSource($cleanPath);
        if ($sourceUrl === null) {
            abort(404);
        }

        $upstream = Http::timeout(15)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
            ])
            ->get($sourceUrl);

        if (! $upstream->successful()) {
            abort($upstream->status() > 0 ? $upstream->status() : 404);
        }

        return response($upstream->body(), 200)
            ->header('Content-Type', (string) $upstream->header('Content-Type', 'application/octet-stream'))
            ->header('Cache-Control', 'public, max-age=300');
    }

    private function resolveImageUrl(Article $article, Request $request): ?string
    {
        $direct = trim((string) $article->image_url);
        if ($direct !== '' && $this->isSafeAbsoluteUrl($direct)) {
            return $direct;
        }

        $path = $this->extractImagePath($article);
        if ($path === null) {
            return null;
        }

        return $this->buildProxyImageUrl($path, $request);
    }

    private function resolveAuthor(Article $article): string
    {
        $author = trim((string) $article->author);
        return $author !== '' ? $author : 'Admin';
    }

    private function extractImagePath(Article $article): ?string
    {
        $image = trim((string) $article->image);
        if ($image !== '') {
            if (Str::startsWith($image, ['http://', 'https://'])) {
                return $this->extractPathFromAbsoluteUrl($image);
            }

            return ltrim($image, '/');
        }

        $imageUrl = trim((string) $article->image_url);
        if ($imageUrl === '') {
            return null;
        }

        if (Str::startsWith($imageUrl, ['http://', 'https://'])) {
            return $this->extractPathFromAbsoluteUrl($imageUrl);
        }

        return ltrim($imageUrl, '/');
    }

    private function extractPathFromAbsoluteUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path === '') {
            return null;
        }

        $marker = '/image/';
        if (str_contains($path, $marker)) {
            $relative = explode($marker, $path, 2)[1] ?? '';
            $relative = trim($relative, '/');

            return $relative !== '' ? $relative : null;
        }

        $trimmed = trim($path, '/');
        return $trimmed !== '' ? $trimmed : null;
    }

    private function buildProxyImageUrl(string $path, Request $request): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/') . '/api/article-image?path=' . rawurlencode(trim($path, '/'));
    }

    private function resolveAdminImageSource(string $path): ?string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $this->isSafeAbsoluteUrl($path) ? $path : null;
        }

        $baseUrl = rtrim((string) env('SAVERA_ADMIN_PROXY_BASE_URL', 'https://savera_admin.ungguldinamika.com'), '/');
        return $baseUrl . '/image/' . ltrim($path, '/');
    }

    private function resolveLocalImagePath(string $path): ?string
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');
        if ($normalized === '' || str_contains($normalized, '..')) {
            return null;
        }

        $candidates = [
            rtrim((string) env('SAVERA_ADMIN_STORAGE_PATH', ''), "\\/"),
            base_path('../../savera-admin/savera-admin/storage/app'),
            base_path('../../savera-admin/savera-admin/storage/app/public'),
            storage_path('app'),
            storage_path('app/public'),
        ];

        foreach ($candidates as $root) {
            if (! is_string($root) || trim($root) === '') {
                continue;
            }

            $fullPath = rtrim($root, "\\/") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
            if (is_file($fullPath)) {
                return realpath($fullPath) ?: $fullPath;
            }
        }

        return null;
    }

    private function isSafeAbsoluteUrl(string $url): bool
    {
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        return ! str_contains($host, '_');
    }
}
