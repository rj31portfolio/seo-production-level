<?php

namespace App\Services;

use App\Models\Agency;
use Illuminate\Support\Facades\Storage;

class AgencyBranding
{
    public const FIELDS = ['tagline', 'address', 'email', 'phone', 'website', 'support_email', 'support_url', 'accent_color', 'report_footer'];

    public function snapshot(Agency $agency): array
    {
        $branding = array_intersect_key($agency->branding ?? [], array_flip(self::FIELDS));
        $branding['name'] = $agency->name;
        $branding['accent_color'] = preg_match('/^#[0-9a-fA-F]{6}$/', $branding['accent_color'] ?? '') ? $branding['accent_color'] : '#ea580c';
        $branding['logo_data'] = $this->logoData($agency);

        return $branding;
    }

    public function logoData(Agency $agency): ?string
    {
        $path = $agency->branding['logo_path'] ?? null;
        if (! is_string($path) || ! preg_match('#^branding/'.preg_quote((string) $agency->id, '#').'/[a-zA-Z0-9]+\.(png|jpg|jpeg)$#', $path)) {
            return null;
        }
        $disk = Storage::disk('local');
        if (! $disk->exists($path) || $disk->size($path) > 1024 * 1024) {
            return null;
        }
        $bytes = $disk->get($path);
        $image = $bytes ? @getimagesizefromstring($bytes) : false;
        if (! $image || ! in_array($image['mime'], ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return 'data:'.$image['mime'].';base64,'.base64_encode($bytes);
    }
}
