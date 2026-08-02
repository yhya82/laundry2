<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DamageTypesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $types = [
            'Color Bleeding' => 'Dye from one garment transferred onto another during washing',
            'Tear / Rip' => 'Fabric torn during washing, drying, or handling',
            'Shrinkage' => 'Garment shrank beyond expected tolerance',
            'Missing Item' => 'An item from the order could not be located',
            'Stain Not Removed' => 'A stain present at drop-off was not removed by processing',
            'Burn / Iron Mark' => 'Scorch or iron-plate mark left during pressing',
            'Button or Zipper Damage' => 'Fastener broken, melted, or lost during processing',
        ];

        foreach ($types as $name => $description) {
            DB::table('damage_types')->updateOrInsert(
                ['name' => $name],
                ['description' => $description, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }
}
