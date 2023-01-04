<?php

namespace App\Console\Commands;

use App\Models\Swag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClubhouseConvertShirtsToSwag extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:convert-shirts-to-swag';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
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

        foreach ($tshirts as $t) {
            $s = Swag::where('title', $t)->where('type', Swag::TYPE_DEPT_SHIRT)->firstOrFail();
            DB::table('person')
                ->where('teeshirt_size_style', $t)
                ->update([ 'tshirt_swag_id' => $s->id]);
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

        foreach ($ls as $t) {
            $s = Swag::where('title', $t)->where('type', Swag::TYPE_DEPT_SHIRT)->firstOrFail();
            DB::table('person')
                ->where('longsleeveshirt_size_style', $t)
                ->update([ 'long_sleeve_swag_ig' => $s->id]);
        }

        $rows = DB::table('person')
                ->whereRaw('teeshirt_size_style is not null and teeshirt_size_style != "" and teeshirt_size_style != "unknown"')
                ->whereNull('tshirt_swag_id')
                ->get();

        foreach ($rows as $row) {
            $this->error("{$row->id} {$row->callsign} t-shirt {$row->teeshirt_size_style}");
        }

        $rows = DB::table('person')
            ->whereRaw('longsleeveshirt_size_style is not null and longsleeveshirt_size_style != "" and longsleeveshirt_size_style != "unknown"')
            ->whereNull('long_sleeve_swag_ig')
            ->get();

        foreach ($rows as $row) {
            $this->error("{$row->id} {$row->callsign} long sleeve {$row->teeshirt_size_style}");
        }

        return Command::SUCCESS;
    }
}
