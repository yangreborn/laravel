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

$factory->define(App\Models\LintData::class, function (Faker $faker) {

    return [
        'error' => random_int(10, 100),
        'warning' => random_int(1000, 10000),
        'note' => random_int(1000, 10000),
        'uninitialized' => random_int(100, 1000),
        'overflow' => random_int(100, 1000),
        'unusual_format' => random_int(100, 1000),
        'created_at' => $faker->dateTimeThisYear,
        'updated_at' => now(),
    ];
});
