<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\NewUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UsersController extends Controller
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
     * @param Request $request
     * @return UserCollection
     */
    public function index(Request $request): UserCollection
    {
        $perPage = $request->get('perPage') ?? 50;
        return new UserCollection(User::withoutBotUser()->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param NewUserRequest $request
     * @return UserResource
     */
    public function store(NewUserRequest $request)
    {
        /** @var User $user */
        $user = User::make($request->all());

        $user->password = Hash::make(Str::random(8));
        $user->save();

        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return UserResource
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateUserRequest $request
     * @param User $user
     * @return Response
     */
    public function update(UpdateUserRequest $request, User $user): Response
    {
        $user->fill($request->all());
        $user->save();

        return response()->noContent(200);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param User $user
     * @return Response
     */
    public function destroy(User $user): Response
    {
        try {
            $user->delete();
            return response()->noContent(200);
        } catch (\Exception $e) {
            Log::error(sprintf('Error deleting user - %s', $e->getMessage()));
            return response('Error deleting user', 500);
        }
    }
}
