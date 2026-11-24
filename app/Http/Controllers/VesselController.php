<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use Illuminate\View\View;

class VesselController extends Controller
{
    public function show(): View
    {
        $vessel = Vessel::withCount('assets')->firstOrFail();
        $vessel->load('assets');

        return view('vessel.show', ['vessel' => $vessel]);
    }
}
