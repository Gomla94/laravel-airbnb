<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    public function index()
    {
        $offices = Office::query()
                ->where('hidden', false)
                ->where('approval_status', Office::APPROVED_STATUS)
                ->when(request('host_id'), fn($builder) => $builder->whereUserId(request('host_id')))
                ->when(request('user_id'), fn(Builder $builder) => $builder->whereRelation('reservation', 'user_id', '=', request('user_id')))
                ->when(request('lat') && request('lng'), 
                    fn($builder) => $builder->nearestTo(request('lat'), request('lng')),
                    fn($builder) => $builder->orderBy('id', 'ASC'))
                ->with(['tags', 'images', 'user'])
                ->withCount(['reservations' => fn($builder) => $builder->where('status', Reservation::ACTIVE_STATUS)])
                ->paginate(20);
        
        return OfficeResource::collection($offices);
    }

    public function show(Office $office)
    {
        $office->loadCount(['reservations' => fn($builder) => $builder->where('status', Reservation::ACTIVE_STATUS)]);
        $office->load(['images', 'user', 'tags']);
        return OfficeResource::make($office);
    }
}
