<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'approval_status' => 'integer',
        'hidden' => 'bool',
        'price_per_day' => 'integer',
        'monthly_discount' => 'integer'
    ];

    const APPROVED_STATUS = 1;
    const PENDING_STATUS = 2;
    const REJECTED_STATUS = 3;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'offices_tags');
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'resource');
    }

    public function scopeNearestTo(Builder $builder, $lat, $lng)
    {
        //without specifing the select() the selectRaw will override the default which is by default
        //select all and will return only the distance with empty arrays of the other data.
        return $builder->select()
                ->orderByRaw(
                'SQRT(POW(69.1 * (lat - ?), 2) + POW(69.1 * (? - lng) * COS(lat / 57.3), 2))',
                [$lat, $lng]
               
        );
    }
}
