<?php

namespace Database\Seeders;

use App\Models\Common\OfferingCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfferingCategorySeeder extends Seeder
{
    private array $data = [
        'scopes' => [
            0 => [
                'name' => 'A. Hacking Works',
                'descriptions' => [
                    0 => [
                        'text' => '1. Supply labour and tool to hack/ dismantle away the following item:',
                        'items' => [
                            0 => [
                                'name' => 'Living & Dining Hall: floor tile & skirting',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => '3 Bedrooms: floor tile/parquet/laminate & skirting',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Kitchen: floor & wall tile, kitchen cabinet, work top & fitting',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Master Bathroom: floor & wall tile, sanitary, vanity, screen & fitting',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Common Bathroom: floor & wall tile, sanitary, vanity, screen & fitting',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            5 => [
                                'name' => 'Utility Bathroom: floor & wall tile, sanitary, vanity, screen & fitting',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            6 => [
                                'name' => 'Balcony: floor tile & skirting',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            7 => [
                                'name' => 'Yard: floor tile & skirting',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            8 => [
                                'name' => 'Kitchen & Bathrooms false ceiling included (condo / private house)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            9 => [
                                'name' => 'Others',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => '2. Other\'s hacking works as following:',
                        'items' => [
                            0 => [
                                'name' => 'Existing ceiling/ cornices work',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Carpentry work, wardrobe',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Others',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => '3. Internal wall demolition: (subject to HDB permit approved w/o request PE)',
                        'items' => [
                            0 => [
                                'name' => 'Wall between Master Bedroom and Bedroom 2',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Wall between Kitchen and Dining',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Wall between Master Room & Room 2',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Creation of wall opening at:',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Opening new entrance at:',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            5 => [
                                'name' => 'Others:',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => '4. Application of hacking permit process',
                        'items' => [],
                    ],
                    4 => [
                        'text' => '5. Professional Engineer Endorsement for wall demolition work',
                        'items' => [],
                    ],
                ],
            ],
            1 => [
                'name' => 'B. Masonry Works',
                'descriptions' => [
                    0 => [
                        'text' => '1. Make good and plaster smooth affected areas',
                        'items' => [
                            0 => [
                                'name' => 'General affected areas',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Wall between Master Bedroom and Bedroom 2',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Construct and make good door way',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Refer to item',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Wall between Master Bedroom and Bedroom 2 (refer to item …)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            5 => [
                                'name' => 'Construct and make good door way for: …',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            6 => [
                                'name' => 'Others: …',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => '2. Construct concrete wall as following:',
                        'items' => [
                            0 => [
                                'name' => 'To seal up existing (Store / ??) entrance using 63mm hollow block for HDB, red bricks for private',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'To erect full height concrete wall between ( ) and ( ) mark on plan',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'To box up shower niche with recess design',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'To box up suspended toilet bowl wall with wall tile finish',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'To construct bathtub support with wall tile finish at ...',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            5 => [
                                'name' => 'Top up floor level using light weight block to level between (Balcony) and (Living)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            6 => [
                                'name' => 'Others: …',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => '3. Apply water-proofing membrane includes \'NS grout, Quick seal 104, Pre-packed 3-in-1 water-proof screed\'',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen with an up-turn of 300mm against wall',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Master Bathroom with an up-turn of 300mm against wall',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Common Bathroom with an up-turn of 300mm against wall',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Utility Bathroom with an up-turn of 300mm against wall',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Yard with an up-turn of 300mm against wall',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            5 => [
                                'name' => 'Balcony with an up-turn of 300mm against wall',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            6 => [
                                'name' => 'Water bonding test',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => '4. Construct H50mm base with tiles finish',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen cabinet',
                                'qty' => 1,
                                'uom' => 'no',
                                'price' => 150,
                            ],
                            1 => [
                                'name' => 'Fridge',
                                'qty' => 1,
                                'uom' => 'no',
                                'price' => 150,
                            ],
                            2 => [
                                'name' => 'Washing machine',
                                'qty' => 1,
                                'uom' => 'no',
                                'price' => 150,
                            ],
                            3 => [
                                'name' => 'Recess base D200mm x H200mm for:',
                                'qty' => 1,
                                'uom' => 'no',
                                'price' => 150,
                            ],
                        ],
                    ],
                    4 => [
                        'text' => '5. To lay/overlay Homogeneous wall tile price (≤$ 3.50/ sqft) up to ceiling height',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'Master Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'Common Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            3 => [
                                'name' => 'Utility Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            4 => [
                                'name' => 'Others: …',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    5 => [
                        'text' => '6. To lay heavy duty non-slip Homogeneous floor tile price (≤$ 3.50/ sqft)',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'Service Balcony',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'Yard',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            3 => [
                                'name' => 'Master Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            4 => [
                                'name' => 'Common Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            5 => [
                                'name' => 'Others: …',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    6 => [
                        'text' => '7. To lay heavy duty polished Homogeneous floor tile price (≤$ 3.50/ sqft) with skirting',
                        'items' => [
                            0 => [
                                'name' => 'Foyer',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'Living',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'Dining',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            3 => [
                                'name' => 'Master Room',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            4 => [
                                'name' => 'Room 2',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            5 => [
                                'name' => 'Room 3',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            6 => [
                                'name' => 'Room 4',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            7 => [
                                'name' => 'Study Room',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            8 => [
                                'name' => 'Store Room',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            9 => [
                                'name' => 'Others: …',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    7 => [
                        'text' => '8. To lay feature\'s tile price (≤$ 4.50/ sqft) at following:',
                        'items' => [
                            0 => [
                                'name' => 'Areas: …',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                ],
            ],
            2 => [
                'descriptions' => [
                    0 => [
                        'text' => 'To plaster whole house wall and ceiling using Ultra-Hard stopping compound include PVC angle-bead for all corners beam, pillars and wall',
                        'items' => [],
                    ],
                    1 => [
                        'text' => 'Supply labour to plaster ( ) (wall / ceiling) using Ultra-Hard stopping compound',
                        'items' => [],
                    ],
                    2 => [
                        'text' => 'Others: …',
                        'items' => [],
                    ],
                ],
                'name' => 'Plastering Works:',
            ],
            3 => [
                'descriptions' => [
                    0 => [
                        'text' => 'To give exact quote after confirmation of drawing/requirement: ',
                        'items' => [],
                    ],
                    1 => [
                        'text' => 'charges base on relocate, new point, existing point or design require after 3D proposed',
                        'items' => [],
                    ],
                ],
                'name' => 'Electrical Works:',
            ],
            4 => [
                'descriptions' => [
                    0 => [
                        'text' => '1. Plumbing Works',
                        'items' => [
                            0 => [
                                'name' => 'License plumber submission for PUB record',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'To engage HDB approval licensed plumber submission for PUB records',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'To discharge and extend existing inlet point due for overlay wall tile',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => '2. Run new conceal copper pipe with BCA require regulation for the following area:',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen (cold water pipe only)',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'Master Bathroom (hot & cold)',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'Common Bathroom (hot & cold)',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            3 => [
                                'name' => 'Utility Bathroom (cold water pipe only)',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => '3. To run new stainless steel pipe (single line piping) for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'Master Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'Common Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            3 => [
                                'name' => 'Utility Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => '4. Top up hot water point for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen sink',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'Master Bath shower, Common Bath shower',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'Master Bath basin, Common Bath basin',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    4 => [
                        'text' => '5. Extend water inlet point for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Water dispenser point (no outlet require)',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'New Kitchen sink location',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'Gas Heater location / New tank heater location',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            3 => [
                                'name' => 'Extra basin at Balcony',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    5 => [
                        'text' => '6. To run new stainless steel pipe (single line piping) for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'Master Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'Common Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    6 => [
                        'text' => '4. Top up hot water point for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen sink',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'Master Bath shower, Common Bath shower',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'Master Bath basin, Common Bath basin',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    7 => [
                        'text' => '5. Extend water inlet point for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Water dispenser point (no outlet require)',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'New Kitchen sink location',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'Gas Heater location / New tank heater location',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            3 => [
                                'name' => 'Extra basin at Balcony',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    8 => [
                        'text' => '6. Labour to conceal embedded bath mixers / top rain shower for:',
                        'items' => [
                            0 => [
                                'name' => 'Master Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'Common Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'To levelling, structure bathtub & run drain pipe at Master Bathroom',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            3 => [
                                'name' => 'To levelling, structure wall hung toilet bowl at …',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    9 => [
                        'text' => '7. Supply "labour only" to install the following: (products provided by owner)',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen sink & tap',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            1 => [
                                'name' => 'Wash Machine tap',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            2 => [
                                'name' => 'Toilet bowl / wall hung toilet bowl',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            3 => [
                                'name' => 'Basin & Tap / Vanity cabinet by owner',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            4 => [
                                'name' => 'Storage heater / Instant heater / Gas heater',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            5 => [
                                'name' => 'Bath\'s mixer & rain shower set / bathtub connection',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            6 => [
                                'name' => 'Bath Accessories',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                            7 => [
                                'name' => 'Bidet sprays & valves',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    10 => [
                        'text' => '8. Convert squat pan to sitting bowl for: ......',
                        'items' => [
                            0 => [
                                'name' => '...',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    11 => [
                        'text' => '9. Run uPVC pipe outlets pipe for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen, washing machine, all Bathrooms',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                    12 => [
                        'text' => 'Replace existing cast-iron sewage pipe to uPVC sewerage pipe for:',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen / 2 Bathrooms',
                                'qty' => 1,
                                'uom' => 'sqft',
                                'price' => 12,
                            ],
                        ],
                    ],
                ],
                'name' => 'Plumbing Works:',
            ],
            5 => [
                'name' => 'Ceiling Works',
                'descriptions' => [
                    0 => [
                        'text' => 'Install proposed design false ceiling with galvanised steel support for:',
                        'items' => [
                            0 => [
                                'name' => 'Foyer= false ceiling /',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Living Hall= false ceiling with light pelmet/L-box /',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Dining Hall= false ceiling with light pelmet/L-box /',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Master Room=',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Room 2=',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            5 => [
                                'name' => 'Room 3=',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Kitchen & 3 Bathrooms= false ceiling with access hole',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen & 3 Bathrooms= false ceiling with access hole',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'Install curtain pelmet/ aircon pelmet for:',
                        'items' => [
                            0 => [
                                'name' => 'Install curtain pelmet/ aircon pelmet for',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'Erect double side gymsum board partition wall (full height) for:',
                        'items' => [
                            0 => [
                                'name' => 'Erect double side gymsum board partition wall (full height) for',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    4 => [
                        'text' => 'Install decorative beading design on:',
                        'items' => [
                            0 => [
                                'name' => 'Install decorative beading design on',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    5 => [
                        'text' => 'To install waterproof "calcium silicate board" to box up sewage pipe for:',
                        'items' => [
                            0 => [
                                'name' => 'To install waterproof "calcium silicate board" to box up sewage pipe for',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    6 => [
                        'text' => 'Seal up recess using gymsum board',
                        'items' => [
                            0 => [
                                'name' => 'Seal up recess using gymsum board',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    7 => [
                        'text' => 'To box up ( area) ….. with light pelmet design',
                        'items' => [
                            0 => [
                                'name' => 'To box up ( area) ….. with light pelmet design',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            6 => [
                'name' => 'Painting Works',
                'descriptions' => [
                    0 => [
                        'text' => 'Paint whole house ceiling (Matex white) and wall (Vinilex 5000) max 5 colour whole house',
                        'items' => [
                            0 => [
                                'name' => 'Paint whole house ceiling (Matex white) and wall (Vinilex 5000) max 5 colour whole house',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Paint all door frames, pipe using Nippon glossy paint',
                        'items' => [
                            0 => [
                                'name' => 'Paint all door frames, pipe using Nippon glossy paint',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'Top up paint door for:',
                        'items' => [
                            0 => [
                                'name' => 'Top up paint door for',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'Top up wall paint work at Kitchen includes oil sealer base',
                        'items' => [
                            0 => [
                                'name' => 'Top up wall paint work at Kitchen includes oil sealer base',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    4 => [
                        'text' => 'Top up Anti mould ceiling paint at Kitchen & Bathrooms',
                        'items' => [
                            0 => [
                                'name' => 'Top up Anti mould ceiling paint at Kitchen & Bathrooms',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    5 => [
                        'text' => 'Apply oil sealer base coating for whole house, due to plastering work',
                        'items' => [
                            0 => [
                                'name' => 'Apply oil sealer base coating for whole house, due to plastering work',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            7 => [
                'name' => 'Aluminium Works',
                'descriptions' => [
                    0 => [
                        'text' => 'Install (NA/BA/white powder-coated) colour framed sliding window c/w (clear/grey/tea/blue/green/black) glass for the following:-',
                        'items' => [
                            0 => [
                                'name' => 'Living Hall (3 way / 2 way)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Dining Hall (3 way / 2 way)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Study Room) (3 way / 2 way)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Kitchen (3 way / 2 way)',
                        'items' => [
                            0 => [
                                'name' => 'Master Bedroom (3 way / 2 way)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Room 2 (3 way / 2 way)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Room 3 (3 way / 2 way)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'Install (NA/BA/white powder-coated) colour framed casement window c/w (clear/grey/tea/blue/green/black) glass for the following:-',
                        'items' => [
                            0 => [
                                'name' => 'Living Hall',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Dining Hall',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Study Room)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'Kitchen',
                        'items' => [
                            0 => [
                                'name' => 'Master Bedroom',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Room 2',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Room 3',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    4 => [
                        'text' => 'Install (NA/BA/white powder-coated) colour framed (adjustable/fix) louver / side',
                        'items' => [
                            0 => [
                                'name' => 'Install (NA/BA/white powder-coated) colour framed (adjustable/fix) louver / side',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    5 => [
                        'text' => 'hung window c/w wire-mess glass for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Master Bathroom',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Common Bathroom',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Others',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    6 => [
                        'text' => 'Install built-in KDK exhaust fan (6” / 8”)',
                        'items' => [
                            0 => [
                                'name' => 'Install built-in KDK exhaust fan (6” / 8”)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    7 => [
                        'text' => 'Top up using laminated glass',
                        'items' => [
                            0 => [
                                'name' => 'Top up using laminated glass',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    8 => [
                        'text' => 'To upgrade window to Double Glazed glass for:',
                        'items' => [
                            0 => [
                                'name' => 'To upgrade window to Double Glazed glass for',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    9 => [
                        'text' => 'To upgrade window lock to Multi lock-set for:',
                        'items' => [
                            0 => [
                                'name' => 'To upgrade window lock to Multi lock-set for',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    10 => [
                        'text' => 'To upgrade window frame to Alpha material size:',
                        'items' => [
                            0 => [
                                'name' => 'To upgrade window frame to Alpha material size',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    11 => [
                        'text' => 'To install heavy duty top hanging track ULTRA SLIM SYNCHRONISED sliding door with soft close & clear tempered glass for:',
                        'items' => [
                            0 => [
                                'name' => 'To install heavy duty top hanging track ULTRA SLIM SYNCHRONISED sliding door with soft close & clear tempered glass for',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    12 => [
                        'text' => 'To install heavy duty top hanging track ULTRA SLIM TELESCOPIC (2 FIXED, 2 MIDDLE SLIDE) sliding door with soft close & clear tempered glass for:',
                        'items' => [
                            0 => [
                                'name' => 'To install heavy duty top hanging track ULTRA SLIM TELESCOPIC (2 FIXED, 2 MIDDLE SLIDE) sliding door with soft close & clear tempered glass for',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    13 => [
                        'text' => 'Others:',
                        'items' => [
                            0 => [
                                'name' => 'Others',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            8 => [
                'name' => 'Glass / Mirror Works',
                'descriptions' => [
                    0 => [
                        'text' => 'Install frameless/matt black framed 10mm tempered glass casement door shower screen includes stainless steel towel bar for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Master Bathroom',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Common Bathroom',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Install frameless/matt black framed 10mm tempered glass sliding door shower screen includes heavy duty stainless steel top hanging track for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Master Bathroom',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Common Bathroom',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'Install frameless/matt black framed 10mm tempered glass casement door with (sand-blasting / laminated / spray paint) c/w stainless steel handle for the door way at:',
                        'items' => [
                            0 => [
                                'name' => 'Master Bathroom',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Common Bathroom',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Others',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'To install Fix panel clear tempered glass screen in frameless/frame design at: …… size: ……',
                        'items' => [
                            0 => [
                                'name' => 'To install Fix panel clear tempered glass screen in frameless/frame design at: …… size: ……',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    4 => [
                        'text' => 'Install 6mm (spray paint) colour tempered glass backing for:',
                        'items' => [
                            0 => [
                                'name' => 'Between Kitchen top and bottom cabinet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            9 => [
                'name' => 'Door’s Works',
                'descriptions' => [
                    0 => [
                        'text' => 'Supply & install new door frame for:',
                        'items' => [
                            0 => [
                                'name' => 'Areas…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Install architrave trims for both side for:',
                        'items' => [
                            0 => [
                                'name' => 'Install architrave trims for both side for',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'Install solid nyatoh door (size:..) c/w lock-set, stopper and lacquer finish for:',
                        'items' => [
                            0 => [
                                'name' => 'Areas…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'Install (hollow/solid core) door in Melamine / Laminate (size:...) finish c/w lock-set, stopper for:',
                        'items' => [
                            0 => [
                                'name' => 'Areas…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    4 => [
                        'text' => 'Install WTP door include (Door frame + Architrave + Door) c/w lock-set, stopper for:',
                        'items' => [
                            0 => [
                                'name' => 'Areas…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    5 => [
                        'text' => 'Install WTP door include lock-set, stopper for:',
                        'items' => [
                            0 => [
                                'name' => 'Areas…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    6 => [
                        'text' => 'Install (non/ 1/2 hour) fire rated door in (Nyatoh/Veneer/Melamine) finish main door c/w press lock-set for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Areas…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    7 => [
                        'text' => 'Supply and install Slide & Swing PD door in ( ) series at:',
                        'items' => [
                            0 => [
                                'name' => 'Supply and install Slide & Swing PD door in ( ) series at',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    8 => [
                        'text' => 'Supply and install aluminium Bi-fold door in ( ) at:',
                        'items' => [
                            0 => [
                                'name' => 'Supply and install aluminium Bi-fold door in ( ) at',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    9 => [
                        'text' => 'Supply and install ….',
                        'items' => [
                            0 => [
                                'name' => 'Supply and install ….',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            10 => [
                'name' => 'Iron’s Works',
                'descriptions' => [
                    0 => [
                        'text' => 'Install solid wrought iron gate c/w lock-set for:',
                        'items' => [
                            0 => [
                                'name' => 'Main entrance Size….',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Install powder coated mild steel design gate c/w lock-set for:',
                        'items' => [
                            0 => [
                                'name' => 'Main entrance Size….',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Others..',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            11 => [
                'name' => 'Timber Flooring Works',
                'descriptions' => [
                    0 => [
                        'text' => 'Supply labour & tools to apply sanding, varnishing existing parquet floor for:',
                        'items' => [
                            0 => [
                                'name' => '3 Bedrooms',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Staircase step (with/ without riser)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Others',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Lay Burmese/Indonesia teak parquet (size: ....) include sanding and varnishing for:',
                        'items' => [
                            0 => [
                                'name' => '3 Bedrooms',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'Supply & install timber wood skirting profile H100mm for above\'s room',
                        'items' => [
                            0 => [
                                'name' => 'Staircase step (with / without riser)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Staircase step using long cut size teak piece',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'Supply material to (repair / replace / make good) existing damage parquet for:',
                        'items' => [
                            0 => [
                                'name' => 'Areas',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    4 => [
                        'text' => 'Lay new parquet joint existing c/w revarnishing the whole areas',
                        'items' => [
                            0 => [
                                'name' => 'Lay new parquet joint existing c/w revarnishing the whole areas',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    5 => [
                        'text' => 'Other’s timber floor works:',
                        'items' => [
                            0 => [
                                'name' => 'Other’s timber floor works',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    6 => [
                        'text' => 'To construct platform at Height (6” / …) (with / without) light pelmet) at:',
                        'items' => [
                            0 => [
                                'name' => 'To construct platform at Height (6” / …) (with / without) light pelmet) at',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    7 => [
                        'text' => 'To lay outdoor water resistance Chengai wood flooring at: Balcony',
                        'items' => [
                            0 => [
                                'name' => 'To lay outdoor water resistance Chengai wood flooring at: Balcony',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    8 => [
                        'text' => 'To lay outdoor water resistance Composite wood flooring at: Balcony',
                        'items' => [
                            0 => [
                                'name' => 'To lay outdoor water resistance Composite wood flooring at: Balcony',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            12 => [
                'name' => 'Vinyl Flooring Works',
                'descriptions' => [
                    0 => [
                        'text' => 'To lay 5mm thick quality water resistant vinyl floorboard include skirting/no skirting for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Foyer, Living, Dining & Bedroom corridors',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => '3 Bedrooms',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Staircase step (with / without riser)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Others',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Other’s floor works:',
                        'items' => [
                            0 => [
                                'name' => 'Other’s floor works',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'To construct platform at Height (6” / …) (with / without) light pelmet) at:',
                        'items' => [
                            0 => [
                                'name' => 'To construct platform at Height (6” / …) (with / without) light pelmet) at',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'Alteration existing door due to overlay',
                        'items' => [
                            0 => [
                                'name' => 'Alteration existing door due to overlay',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            13 => [
                'name' => 'Polishing Floor Works',
                'descriptions' => [
                    0 => [
                        'text' => 'Supply labour & tools to apply diamond polishing on existing Marble floor at:',
                        'items' => [
                            0 => [
                                'name' => 'Foyer, Living, Dining & Bedroom corridors',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Others',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Supply labour & tools to regrouting & polishing existing Homogeneous floor at:',
                        'items' => [
                            0 => [
                                'name' => 'Foyer, Living, Dining & Bedroom corridors',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => '3 Bedrooms',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Others',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            14 => [
                'name' => 'Carpentry Works',
                'descriptions' => [
                    0 => [
                        'text' => 'Custom design, fabricate & install the following items: material guide refer to chart chart unless stated in form:',
                        'items' => [
                            0 => [
                                'name' => 'Custom design, fabricate & install the following items: material guide refer to chart chart unless stated in form',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Location: Foyer Area (how to session in formula we have others specific area can choose?)',
                        'items' => [
                            0 => [
                                'name' => 'Shoe cabinet in half height / full height using AA track',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Box up Home Shelter door with feature wall / cabinet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Settee with storage',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Mirror feature wall with light pelmet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Others…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'Location: Living Area',
                        'items' => [
                            0 => [
                                'name' => 'Tv Feature wall',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Tv Cabinet with open shelve design',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Suspended Tv console',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Display cabinet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Others…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'Location: Dining Area',
                        'items' => [
                            0 => [
                                'name' => 'Mirror feature wall with ……',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Settee cabinet with (laminate backing / cushion back) design',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Pantry cabinet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Display cabinet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Others…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    4 => [
                        'text' => 'Location: Kitchen Area',
                        'items' => [
                            0 => [
                                'name' => 'Top hung cabinet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Bottom cabinet (max 4 nos drawers)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Counter cabinet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Island cabinet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Full height cabinet / Tall unit for Oven / Microwave',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            5 => [
                                'name' => 'Box up piping',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            6 => [
                                'name' => 'Light Switches Box',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            7 => [
                                'name' => 'FOC Stainless steel dishrack x 1',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            8 => [
                                'name' => 'FOC Aluminium glass door x 1',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            9 => [
                                'name' => 'FOC PVC cutlery tray',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            10 => [
                                'name' => 'FOC Blum soft close runner x 4 set',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            11 => [
                                'name' => 'FOC Blum HK soft close lift system x 1 set',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            12 => [
                                'name' => 'Others: …',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    5 => [
                        'text' => 'Location: Master Room Area:',
                        'items' => [
                            0 => [
                                'name' => 'Location: Master Room Area',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    6 => [
                        'text' => '(Casement / 30mm thick sliding / Aluminium frame glass) door wardrobe include of:',
                        'items' => [
                            0 => [
                                'name' => 'FOC Hanging rods / shelves / max 4 set drawers',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => '(Queen/King) size built-in bed-frame using laminate c/w bottom drawers',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Platform bed with storage below',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    7 => [
                        'text' => 'Custom headboard using (laminate / glass / mirror/ pvc / fabric cushion) finish design',
                        'items' => [
                            0 => [
                                'name' => 'Bedside table in (suspended / low) design',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Feature wall design',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Built-in dressing table (with / without) mirror',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    8 => [
                        'text' => 'Location: Room 2 Area:',
                        'items' => [
                            0 => [
                                'name' => 'Location: Room 2 Area',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    9 => [
                        'text' => '(Casement / 30mm thick sliding / Aluminium frame glass) door wardrobe include of:',
                        'items' => [
                            0 => [
                                'name' => 'FOC Hanging rods / shelves / max 4 set drawers',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => '(Queen/King/ SS/ Single) size built-in bed-frame using laminate c/w bottom drawers',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Platform bed with storage below',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    10 => [
                        'text' => 'Custom headboard using (laminate / glass / mirror/ pvc / fabric cushion) finish design',
                        'items' => [
                            0 => [
                                'name' => 'Bedside table in (suspended / low) design',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Feature wall design',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Dresser',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Top hung book shelve',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Suspended study table',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            5 => [
                                'name' => 'Others:…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    11 => [
                        'text' => 'Location: Room 3 Area:',
                        'items' => [
                            0 => [
                                'name' => 'Location: Room 3 Area',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    12 => [
                        'text' => '(Casement / 30mm thick sliding / Aluminium frame glass) door wardrobe include of:',
                        'items' => [
                            0 => [
                                'name' => 'FOC Hanging rods / shelves / max 4 set drawers',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => '(Queen/King/ SS/ Single) size built-in bed-frame using laminate c/w bottom drawers',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Platform bed with storage below',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    13 => [
                        'text' => 'Custom headboard using (laminate / glass / mirror/ pvc / fabric cushion) finish design',
                        'items' => [
                            0 => [
                                'name' => 'Bedside table in (suspended / low) design',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Feature wall design',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Dresser',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Top hung book shelve',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Suspended study table',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            5 => [
                                'name' => 'Others:…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            6 => [
                                'name' => 'If have others areas how to create in system?',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    14 => [
                        'text' => 'Location: Master Bathroom Area:',
                        'items' => [
                            0 => [
                                'name' => 'Suspended built-in vanity cabinet using laminate finish',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Top hung vanity storage cabinet in mirror door',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Box up fix panel mirror above vanity in size: ......... with/without light pelmet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Others:…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    15 => [
                        'text' => 'Location: Common Bathroom Area:',
                        'items' => [
                            0 => [
                                'name' => 'Suspended built-in vanity cabinet using laminate finish',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Top hung vanity storage cabinet in mirror door',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Box up fix panel mirror above vanity in size: ......... with/without light pelmet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Others:…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            15 => [
                'name' => 'Worktop Works',
                'descriptions' => [
                    0 => [
                        'text' => 'Install (promotion series) (Quartz/Sintered/ ..) top c/w back-splash skirting in (12mm/20mm/40mm) profile include opening of holes for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Install (promotion series) (Quartz/Sintered/ ..) top c/w back-splash skirting in (12mm/20mm/40mm) profile include opening of holes for the following',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Kitchen top',
                        'items' => [
                            0 => [
                                'name' => 'Counter max depth700mm / Island',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Panty top',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            2 => [
                                'name' => 'Master Bath vanity',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            3 => [
                                'name' => 'Common Bath vanity',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            4 => [
                                'name' => 'Bay window top',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            5 => [
                                'name' => 'Others:…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'Install EDL Compact top in (6mm / downturn) profile include opening of holes for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Install EDL Compact top in (6mm / downturn) profile include opening of holes for the following',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'Kitchen top',
                        'items' => [
                            0 => [
                                'name' => 'Others:…',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    4 => [
                        'text' => 'Install Backing using (Compact / Sintered stone / Quartz / cerarl panel) for the following:',
                        'items' => [
                            0 => [
                                'name' => 'Between Kitchen top & bottom cabinet',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                            1 => [
                                'name' => 'Feature wall',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            16 => [
                'name' => 'General Works',
                'descriptions' => [
                    0 => [
                        'text' => 'To do chemical washing on 1st stage (before carpentry deliver)',
                        'items' => [
                            0 => [
                                'name' => 'To do chemical washing on 1st stage (before carpentry deliver)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Supply labour and material to do general cleaning upon completion',
                        'items' => [
                            0 => [
                                'name' => 'Supply labour and material to do general cleaning upon completion',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'Haulage & Debris Removal',
                        'items' => [
                            0 => [
                                'name' => 'Haulage & Debris Removal',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'To lay corrugated paper protection for affected area',
                        'items' => [
                            0 => [
                                'name' => 'To lay corrugated paper protection for affected area',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            17 => [
                'name' => 'Miscellaneous / Others',
                'descriptions' => [
                    0 => [
                        'text' => 'Replace new stainless steel rubbish chute',
                        'items' => [
                            0 => [
                                'name' => 'Replace new stainless steel rubbish chute',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Supply scaffolding for work progress',
                        'items' => [
                            0 => [
                                'name' => 'Supply scaffolding for work progress',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'Labour fees top up for carry up materials full piece no joints to storey ( … )',
                        'items' => [
                            0 => [
                                'name' => 'Labour fees top up for carry up materials full piece no joints to storey ( … )',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'Labour fees top up for non-lift level unit',
                        'items' => [
                            0 => [
                                'name' => 'Labour fees top up for non-lift level unit',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            18 => [
                'name' => 'Free Gifts',
                'descriptions' => [
                    0 => [
                        'text' => 'Upgrade soft close drawer’s runner for all carpentry drawers needed area',
                        'items' => [
                            0 => [
                                'name' => 'Carpentry internal shelving upgrade to Stainless steel up & down / Upgrade internal shelve to 30mm thick',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'text' => 'Rinnai 3 burner Gas cooker',
                        'items' => [
                            0 => [
                                'name' => 'Rinnai 3 burner Gas cooker',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    2 => [
                        'text' => 'Rinnai Cooker Hood',
                        'items' => [
                            0 => [
                                'name' => 'Rinnai Cooker Hood',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    3 => [
                        'text' => 'Kitchen Sink & Tap (selected model)',
                        'items' => [
                            0 => [
                                'name' => 'Kitchen Sink & Tap (selected model)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    4 => [
                        'text' => 'Lucky Khoon Bathroom Sanitary Voucher $500',
                        'items' => [
                            0 => [
                                'name' => 'Lucky Khoon Bathroom Sanitary Voucher $500',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    5 => [
                        'text' => 'A&S Lighting Shop Voucher $500',
                        'items' => [
                            0 => [
                                'name' => 'A&S Lighting Shop Voucher $500',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    6 => [
                        'text' => 'Discounted amount ($$$$) confirm by date (…….)',
                        'items' => [
                            0 => [
                                'name' => 'Discounted amount ($$$$) confirm by date (…….)',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    7 => [
                        'text' => 'Whole House colour electrical switch & socket',
                        'items' => [
                            0 => [
                                'name' => 'Whole House colour electrical switch & socket',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                    8 => [
                        'text' => '720 Degree Drawing',
                        'items' => [
                            0 => [
                                'name' => '720 Degree Drawing',
                                'qty' => 1,
                                'uom' => null,
                                'price' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    protected function seedUsers()
    {
        // Create a single admin user and their personal company
        $user = \App\Models\User::factory()
            ->withPersonalCompany(function ($factory) {
                return $factory
                    ->state([
                        'name' => 'ERPSAAS',
                    ]);
                // ->withTransactions(250)
                // ->withOfferings()
                // ->withClients()
                // ->withVendors()
                // ->withInvoices(30)
                // ->withRecurringInvoices()
                // ->withEstimates(30)
                // ->withBills(30)
            })
            ->create([
                'name' => 'Admin',
                'email' => 'admin@erpsaas.com',
                'password' => bcrypt('password'),
                'current_company_id' => 1,  // Assuming this will be the ID of the created company
            ]);
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        if (! \App\Models\User::where('email', 'admin@erpsaas.com')->exists()) {
            $this->seedUsers();
        }

        // Ensure we have a company context
        $companyId = DB::table('companies')->value('id');

        if (! $companyId) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => 'Default',
                'email' => 'default@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return;

        // Force session for the model's save hook
        session(['current_company_id' => $companyId]);

        $scopes = $this->data['scopes'] ?? [];

        foreach ($scopes as $scopeData) {
            $scopeName = $scopeData['name'] ?? 'Unknown Scope';

            // Level 1: Scope
            $scopeNode = OfferingCategory::firstOrCreate(
                [
                    'name' => $scopeName,
                    'company_id' => $companyId,
                ]
            );

            $this->command->info("Scope: {$scopeName}");

            if (isset($scopeData['descriptions']) && is_array($scopeData['descriptions'])) {
                foreach ($scopeData['descriptions'] as $descData) {
                    $descText = $descData['text'] ?? 'Unknown Description';

                    try {
                        // Level 2: Description
                        $descNode = $scopeNode->children()->firstOrCreate(
                            [
                                'name' => $descText,
                                'company_id' => $companyId,
                            ],
                            []
                        );
                    } catch (\Exception $e) {
                        $this->command->error("Failed to create description: {$descText}");

                        continue;
                    }

                    if (isset($descData['items']) && is_array($descData['items'])) {
                        foreach ($descData['items'] as $itemData) {
                            $itemName = $itemData['name'] ?? 'Unknown Item';

                            try {
                                // Level 3: Item -> as Offering
                                /** @var \App\Models\Common\Offering $offering */
                                $offering = \App\Models\Common\Offering::firstOrCreate(
                                    [
                                        'name' => $itemName,
                                        'company_id' => $companyId,
                                    ],
                                    [
                                        'description' => $itemName, // Or empty?
                                        'type' => \App\Enums\Common\OfferingType::Service,
                                        'price' => $itemData['price'] ?? 0,
                                        'unit' => $itemData['uom'] ?? null,
                                        'sellable' => true,
                                        'purchasable' => true,
                                    ]
                                );

                                // Attach to the Description Category (Level 2)
                                $offering->categories()->syncWithoutDetaching([$descNode->id]);

                            } catch (\Exception $e) {
                                $this->command->error("Failed to create offering item: {$itemName} - " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }
}
