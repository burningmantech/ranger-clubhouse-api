<?php

namespace App\Console\Commands;

use App\Models\Swag;
use Illuminate\Console\Command;

class ClubhouseCreateShirtSwag extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:create-shirt-swag';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add Shirt Swag records';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $tshirts = [
            'Womens V-Neck XS',
            'Womens V-Neck S',
            'Womens V-Neck M',
            'Womens V-Neck L',
            'Womens V-Neck XL',
            'Womens V-Neck 2XL',
            'Mens Crew S',
            'Mens Crew M',
            'Mens Crew L',
            'Mens Crew XL',
            'Mens Crew 2XL',
            'Mens Crew 3XL',
            'Mens Crew 4XL',
            'Mens Crew 5XL'
        ];

        foreach ($tshirts as $s) {
            Swag::create([
                'title' => $s,
                'type' => Swag::TYPE_DEPT_SHIRT,
                'shirt_type' => Swag::SHIRT_T_SHIRT,
                'active' => true,
            ]);
        }

        $ls = [
            'Womens XS',
            'Womens S',
            'Womens M',
            'Womens L',
            'Womens XL',
            'Womens 2XL',
            'Womens 3XL',
            'Mens Regular S',
            'Mens Regular M',
            'Mens Regular L',
            'Mens Regular XL',
            'Mens Regular 2XL',
            'Mens Regular 3XL',
            'Mens Regular 4XL',
            'Mens Tall M',
            'Mens Tall L',
            'Mens Tall XL',
            'Mens Tall 2XL',
            'Mens Tall 3XL',
            'Mens Tall 4XL'
        ];

        foreach ($ls as $s) {
            Swag::create([
                'title' => $s,
                'type' => Swag::TYPE_DEPT_SHIRT,
                'shirt_type' => Swag::SHIRT_LONG_SLEEVE,
                'active' => true,
            ]);
        }

        for ($year = 2018; $year < 2023; $year += 1) {
            if ($year == 2020 || $year == 2021) {
                continue;

            }
            Swag::create([
                'title' => "{$year} Toaster Pin",
                'type' => Swag::TYPE_DEPT_PIN,
                'active' => true,
            ]);
        }

        for ($year = 5; $year <= 40; $year += 5) {
            Swag::create([
                'title' => "{$year}-Year Service Pin",
                'type' => Swag::TYPE_DEPT_PIN,
                'active' => true,
            ]);
            Swag::create([
                'title' => "{$year}-Year Service Patch",
                'type' => Swag::TYPE_DEPT_PATCH,
                'active' => true,
            ]);

        }

        for ($year = 10; $year <= 40; $year += 5) {
            Swag::create([
                'title' => "{$year}-Year B.M. Service Pin",
                'type' => Swag::TYPE_ORG_PIN,
                'active' => true,
            ]);
        }
        return Command::SUCCESS;
    }
}
