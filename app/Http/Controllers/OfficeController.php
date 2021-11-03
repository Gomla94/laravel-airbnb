<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Validators\OfficeValidator;
use App\Notifications\OfficePendingApproval;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfficeController extends Controller
{
    public function index()
    {
        $offices = Office::query()
                ->when(request('user_id') && auth()->user() && request('user_id') == auth()->id(),
                    fn($builder) => $builder,
                    fn($builder) => $builder->where('hidden', false)->where('approval_status', Office::APPROVED_STATUS)
                )
                ->when(request('user_id'), fn($builder) => $builder->whereUserId(request('user_id')))
                ->when(request('visitor_id'), fn(Builder $builder) => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id')))
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

    public function create(Request $request)
    {
        //session authentication the tokenCan methos will always return true, if the incoming authenticated
        //request if from first party spa (session authentication)

        abort_unless(auth()->user()->tokenCan('office.create'), 
            Response::HTTP_FORBIDDEN
        );

        $attributes = (new OfficeValidator())->validate(
            $office = new Office(),
            $request->all()
        );

        $attributes['user_id'] = auth()->id();
        $attributes['approval_status'] = Office::PENDING_STATUS;
        
        DB::transaction(function () use($office, $attributes) {
            $office->fill(Arr::except($attributes, 'tags'))->save();
            if (isset($attributes['tags'])) { 
                $office->tags()->attach($attributes['tags']);
            }
            return $office;
        });
        
        Notification::send(User::where('is_admin', true)->get(), new OfficePendingApproval($office));

        return OfficeResource::make($office);
    }

    public function update(Office $office)
    {
        abort_unless(auth()->user()->tokenCan('office.update'), 
            Response::HTTP_FORBIDDEN
        );

        $this->authorize('update', $office);

        $attributes = (new OfficeValidator())->validate($office, request()->all());

        $attributes['user_id'] = auth()->id();

        $office->fill(Arr::Except($attributes, 'tags'));

        if ($office->isDirty(['lat', 'lng'])) {
            $office->fill(['approval_status' => Office::PENDING_STATUS]);
        }

        DB::transaction(function () use($office, $attributes) {
            $office->save();
            if (isset($attributes['tags'])) {
                $office->tags()->sync($attributes['tags']);
            }
            return $office;
        });
        
        Notification::send(User::where('is_admin', true)->get(), new OfficePendingApproval($office));

        return OfficeResource::make($office);
    }

    public function delete(Office $office)
    {
        abort_unless(auth()->user()->tokenCan('office.delete'), Response::HTTP_FORBIDDEN);

        $this->authorize('delete', $office);

        throw_if(
            $office->reservations()->where('status', Reservation::ACTIVE_STATUS)->exists(),
            ValidationException::withMessages(['office' => 'can not delete office with reservations'])
        );

        $office->delete();
    }
}
