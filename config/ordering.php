<?php

/*
| Facts shown on the How It Works guide, size guide and measurement form.
| Each lead-time can be overridden from settings (keys in brackets) without a deploy.
*/

return [
    'ways' => [
        'standard' => [
            'title' => 'Standard size',
            'summary' => 'Choose your size from our size guide and we build it to that chart.',
            'lead_time' => '10–15 working days', // setting: lead_time_standard
        ],
        'custom_size' => [
            'title' => 'Custom size',
            'summary' => 'The design you see, cut and stitched to your own body measurements.',
            'lead_time' => '3–4 weeks', // setting: lead_time_custom_size
        ],
        'custom_made' => [
            'title' => 'Custom made',
            'summary' => 'Your own design, colours, graphics or changes, made to your measurements.',
            'lead_time' => '4–6 weeks', // setting: lead_time_custom_made
        ],
    ],

    'delivery_time' => '3–10 working days',  // setting: delivery_time
    'free_shipping_over' => '699',             // setting: free_shipping_over (store currency)
    'fit_window_days' => 14,                   // setting: fit_window_days

    /*
    | Measurement form fields. "for" lists the garments that need the field; "women" marks
    | fields only asked for women's garments. Help text feeds the How to Measure page.
    */
    'garments' => ['suit' => 'Race suit (1 or 2 piece)', 'jacket' => 'Jacket', 'pant' => 'Pant'],

    'measurements' => [
        'upper' => [
            'title' => 'Upper body',
            'fields' => [
                'neck' => ['label' => 'Neck circumference', 'for' => ['suit', 'jacket'], 'help' => 'Around the base of the neck, where a collar sits. Keep one finger under the tape.'],
                'chest' => ['label' => 'Chest circumference', 'for' => ['suit', 'jacket'], 'help' => 'Around the fullest part of the chest, under the arms, tape level across the back. Breathe normally.'],
                'under_breast' => ['label' => 'Under-bust circumference', 'for' => ['suit', 'jacket'], 'women' => true, 'help' => 'Directly under the bust, around the rib cage, tape level.'],
                'bust' => ['label' => 'Bust (fullest point)', 'for' => ['suit', 'jacket'], 'women' => true, 'help' => 'Around the fullest point of the bust while wearing a non-padded bra.'],
                'waist' => ['label' => 'Waist circumference', 'for' => ['suit', 'jacket', 'pant'], 'help' => 'Around your natural waist, usually just above the navel. Relax your stomach.'],
                'shoulder_width' => ['label' => 'Shoulder to shoulder', 'for' => ['suit', 'jacket'], 'help' => 'Across the back from the outer edge of one shoulder bone to the other.'],
                'neck_to_shoulder' => ['label' => 'Neck to shoulder', 'for' => ['suit', 'jacket'], 'help' => 'From the side of the neck to the outer edge of the shoulder bone.'],
                'armhole' => ['label' => 'Armhole (sleeve joint)', 'for' => ['suit', 'jacket'], 'help' => 'Around the shoulder joint, passing under the armpit and over the top of the shoulder.'],
                'bicep' => ['label' => 'Bicep circumference', 'for' => ['suit', 'jacket'], 'help' => 'Around the fullest part of the upper arm with the arm relaxed.'],
                'elbow' => ['label' => 'Elbow circumference', 'for' => ['suit', 'jacket'], 'help' => 'Around the elbow with the arm slightly bent.'],
                'forearm' => ['label' => 'Forearm circumference', 'for' => ['suit', 'jacket'], 'help' => 'Around the fullest part of the forearm.'],
                'wrist' => ['label' => 'Wrist circumference', 'for' => ['suit', 'jacket'], 'help' => 'Around the wrist just above the wrist bone.'],
                'shoulder_to_elbow' => ['label' => 'Shoulder to elbow', 'for' => ['suit', 'jacket'], 'help' => 'From the shoulder point down the outside of the arm to the elbow, arm slightly bent.'],
                'elbow_to_wrist' => ['label' => 'Elbow to wrist', 'for' => ['suit', 'jacket'], 'help' => 'From the elbow to the wrist bone, arm slightly bent as if holding handlebars.'],
                'neck_to_waist_front' => ['label' => 'Neck to waist (front)', 'for' => ['suit', 'jacket'], 'help' => 'From the base of the neck at the front straight down to the natural waist.'],
                'neck_to_waist_back' => ['label' => 'Neck to waist (back)', 'for' => ['suit', 'jacket'], 'help' => 'From the bone at the back of the neck straight down the spine to the waist.'],
            ],
        ],
        'lower' => [
            'title' => 'Lower body',
            'fields' => [
                'hip' => ['label' => 'Hip circumference', 'for' => ['suit', 'jacket', 'pant'], 'help' => 'Around the widest part of the hips and seat, feet together.'],
                'thigh' => ['label' => 'Thigh circumference', 'for' => ['suit', 'pant'], 'help' => 'Around the fullest part of one thigh, just below the crotch.'],
                'knee' => ['label' => 'Knee circumference', 'for' => ['suit', 'pant'], 'help' => 'Around the centre of the knee with the leg straight.'],
                'calf' => ['label' => 'Calf circumference', 'for' => ['suit', 'pant'], 'help' => 'Around the fullest part of the calf.'],
                'ankle' => ['label' => 'Ankle circumference', 'for' => ['suit', 'pant'], 'help' => 'Around the ankle just above the ankle bone.'],
                'waist_to_knee' => ['label' => 'Waist to knee', 'for' => ['suit', 'pant'], 'help' => 'Down the outside of the leg from the waist to the centre of the knee.'],
                'knee_to_ankle' => ['label' => 'Knee to ankle', 'for' => ['suit', 'pant'], 'help' => 'From the centre of the knee to the ankle bone.'],
                'crotch_to_knee' => ['label' => 'Crotch to knee', 'for' => ['suit', 'pant'], 'help' => 'Down the inside of the leg from the crotch to the centre of the knee.'],
                'inseam' => ['label' => 'Crotch to ankle (inseam)', 'for' => ['suit', 'pant'], 'help' => 'Down the inside of the leg from the crotch to the ankle bone.'],
                'rise' => ['label' => 'Front waist to back waist (rise)', 'for' => ['suit', 'pant'], 'help' => 'From the front waist, between the legs, up to the back waist.'],
            ],
        ],
        'full' => [
            'title' => 'Full length (race suits)',
            'fields' => [
                'neck_to_knee_front' => ['label' => 'Neck to knee (front)', 'for' => ['suit'], 'help' => 'From the base of the neck at the front down to the centre of the knee.'],
                'neck_to_ankle' => ['label' => 'Neck to ankle', 'for' => ['suit'], 'help' => 'From the base of the neck at the front down to the ankle bone.'],
                'torso_loop' => ['label' => 'Vertical torso loop', 'for' => ['suit'], 'help' => 'From the top of one shoulder, down the front, between the legs and up the back to the same shoulder.'],
            ],
        ],
        'body' => [
            'title' => 'Height and weight',
            'fields' => [
                'height' => ['label' => 'Height', 'for' => ['suit', 'jacket', 'pant'], 'help' => 'Standing straight without shoes.'],
                'weight' => ['label' => 'Weight', 'for' => ['suit', 'jacket', 'pant'], 'help' => 'Your current body weight.'],
            ],
        ],
    ],

    'measurement_photos' => ['max' => 3, 'max_kb' => 8192],
];
