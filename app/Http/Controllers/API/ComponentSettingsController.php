<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ComponentSettingsResourceCollection;
use OpenDialogAi\Core\ComponentSetting;

class ComponentSettingsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return ComponentSettingsResourceCollection
     */
    public function index(string $component_id): ComponentSettingsResourceCollection
    {
        return new ComponentSettingsResourceCollection(
            ComponentSetting::where('component_id', $component_id)->get()
        );
    }
}
