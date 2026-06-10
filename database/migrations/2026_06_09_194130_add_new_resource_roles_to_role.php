<?php

use App\Models\Role;
use App\Models\Team;
use App\Models\TeamRole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Role::insert([
            'id' => Role::TEAM_RESOURCE_MANAGEMENT,
            'title' => "Team Resource Doc Mgmt",
            'new_user_eligible' => false,
        ]);

        $teams = Team::whereIn('type', [Team::TYPE_CADRE, Team::TYPE_DELEGATION])
            ->where('active', true)
            ->with('roles')
            ->get();

        foreach ($teams as $team) {
            TeamRole::insert([
                'team_id' => $team->id,
                'role_id' => Role::TEAM_RESOURCE_MANAGEMENT,
            ]);


            foreach ($team->roles as $role) {
                $id = $role->id;

                if (($id & Role::ROLE_BASE_MASK) == Role::ART_INTERFACE_BASE) {
                    $position = ($id & ~Role::ROLE_BASE_MASK);
                    $roleId = Role::TRAINER_RESOURCE_MANAGEMENT_BASE | $position;
                    TeamRole::insert([
                        'team_id' => $team->id,
                        'role_id' => $roleId,
                    ]);
                    Role::createARTRoles($position);
                    break;
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::destroy(Role::TEAM_RESOURCE_MANAGEMENT);
        TeamRole::where('role_id', Role::TEAM_RESOURCE_MANAGEMENT)->delete();

        $teams = Team::whereIn('type', [Team::TYPE_CADRE, Team::TYPE_DELEGATION])
            ->where('active', true)
            ->with('roles')
            ->get();

        foreach ($teams as $team) {
            foreach ($team->roles as $role) {
                $id = $role->id;

                if (($id & Role::ROLE_BASE_MASK) == Role::TRAINER_RESOURCE_MANAGEMENT_BASE) {
                    TeamRole::where('role_id', $id)->delete();
                    Role::destroy($id);
                    break;
                }
            }

        }
    }
};
