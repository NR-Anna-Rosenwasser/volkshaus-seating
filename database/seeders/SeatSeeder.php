<?php

namespace Database\Seeders;

use App\Services\SeatImporter;
use Illuminate\Database\Seeder;

class SeatSeeder extends Seeder
{
    public function run(SeatImporter $importer): void
    {
        $count = $importer->import(config('seating.seat_data_path'));

        $this->command?->info("Imported {$count} seats.");
    }
}
