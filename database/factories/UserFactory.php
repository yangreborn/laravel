<?php

use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(App\Models\User::class, function (Faker $faker) {
    static $password;

    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => $password ?: $password = bcrypt('secret'),
        'remember_token' => str_random(10),
        'introduction' => $faker->text,
        'telephone' => $faker->phoneNumber,
        'mobile' => $faker->phoneNumber,
        'is_admin' => $faker->numberBetween(0,2),
        'password_expired' => $faker->dateTimeBetween('now', '+2 months'),
        'status' => 1,
    ];
});
