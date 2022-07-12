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

$factory->define(App\Models\Pclint::class, function (Faker $faker) {

    return [
        'job_name' => $faker->title,
        'job_url' => $faker->url,
        'server_ip' => $faker->ipv4,
        'created_at' => $faker->dateTimeThisYear,
        'updated_at' => now(),
    ];
});
