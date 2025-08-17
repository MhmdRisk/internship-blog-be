<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $admin = Role::create(['name' => 'admin']);
        $author = Role::create(['name' => 'author']);
        // $reader = Role::create(['name' => 'reader']);

        $edit = Permission::create(['name' => 'edit blogs']);
        $delete = Permission::create(['name' => 'delete blogs']);

        // assign permissions to roles
        $admin->permissions()->attach([$edit->id, $delete->id]);
        $author->permissions()->attach($edit->id);

    }
}
