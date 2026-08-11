<?php

namespace Database\Seeders;

use App\Models\DeliveryRegion;
use Illuminate\Database\Seeder;

class DeliveryRegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            [
                'name' => 'Beirut Central',
                'code' => 'BRT',
                'fee' => 3.00,
                'min' => 1,
                'max' => 2,
                'description' => 'Hamra, Achrafieh, Verdun, Mar Mikhael, Mazraa, Rawche, Jnah, Downtown, Ras Beirut, Gemmayze, Badaro, Dahye.',
            ],
            [
                'name' => 'Metn & Coastal Suburbs',
                'code' => 'MET',
                'fee' => 5.00,
                'min' => 1,
                'max' => 3,
                'description' => 'Zalka, Jal el Dib, Antelias, Kaslik, Tabarja, Baabda, Khalde, Bchamoun, Aramoun, Dbayeh, Naccache, Fanar.',
            ],
            [
                'name' => 'Mountain & Southern Coastal',
                'code' => 'MSC',
                'fee' => 6.00,
                'min' => 2,
                'max' => 4,
                'description' => 'Aley, Saida (Sidon), Ghazieh, Zahle, Jbeil (Byblos), Kfardebian, Mtein, Bickfaya, Dhour El Choueir.',
            ],
            [
                'name' => 'Major Cities North & South',
                'code' => 'MNS',
                'fee' => 7.00,
                'min' => 2,
                'max' => 5,
                'description' => 'Tripoli (Trablos), Koura, El Mina, Chtaura, Sour (Tyre), Hasbaya, Marjeyoun, Jezzine, Batroun.',
            ],
            [
                'name' => 'Remote & Eastern Districts',
                'code' => 'REM',
                'fee' => 8.00,
                'min' => 3,
                'max' => 6,
                'description' => 'Baalbek, Bint Jbeil, Nabatieh, Hermel, Rashaya, Qaa, Deir El Ahmar, Aarsal.',
            ],
        ];

        foreach ($regions as $i => $region) {
            DeliveryRegion::query()->updateOrCreate(
                ['code' => $region['code']],
                [
                    'name' => $region['name'],
                    'fee' => $region['fee'],
                    'description' => $region['description'],
                    'estimated_days_min' => $region['min'],
                    'estimated_days_max' => $region['max'],
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }

        DeliveryRegion::query()
            ->whereNotIn('code', collect($regions)->pluck('code')->all())
            ->update(['is_active' => false]);
    }
}
