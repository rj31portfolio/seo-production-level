<?php

namespace App\Models;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class TenantModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('agency', fn (Builder $q) => $q->where($q->getModel()->qualifyColumn('agency_id'), app(TenantContext::class)->id()));
        static::creating(function (Model $m) {
            $id = app(TenantContext::class)->id();
            if ($m->agency_id !== null && (int) $m->agency_id !== $id) {
                throw new LogicException('Cross-agency write denied.');
            } $m->agency_id = $id;
        });
        static::updating(function (Model $m) {
            if ((int) $m->agency_id !== app(TenantContext::class)->id() || $m->isDirty('agency_id')) {
                throw new LogicException('Cross-agency write denied.');
            }
        });
        static::deleting(function (Model $m) {
            if ((int) $m->agency_id !== app(TenantContext::class)->id()) {
                throw new LogicException('Cross-agency delete denied.');
            }
        });
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}
