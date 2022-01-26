<?php

namespace App\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use OpenDialogAi\Core\ComponentSetting;

class ComponentSettingsPolicy
{
    use HandlesAuthorization;

    public function update(User $user, ComponentSetting $webchatSetting)
    {
        return $webchatSetting->children()->count() == 0;
    }

    public function create(User $user)
    {
        return false;
    }

    public function delete(User $user)
    {
        return false;
    }

    public function view(User $user, ComponentSetting $webchatSetting)
    {
        return $webchatSetting->children()->count() > 0;
    }
}
