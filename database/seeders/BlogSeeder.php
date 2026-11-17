<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'title' => 'How to Measure for a Custom Made-to-Measure Leather Jacket',
                'slug' => 'how-to-measure-custom-leather-jacket',
                'excerpt' => 'A complete master craftsman sizing guide: exact instructions on measuring your chest, shoulders, sleeve length, and waist for a flawless bespoke leather fit.',
                'meta_title' => 'How to Measure for a Custom Leather Jacket | Sizing Guide | RJ Motorbike',
                'meta_description' => 'Learn how to accurately measure your chest, shoulders, sleeve length and torso for a custom made-to-measure leather jacket. Step-by-step master craftsman guide.',
                'meta_keywords' => 'how to measure leather jacket, custom leather jacket sizing, made to measure motorcycle jacket, chest measurement jacket, bespoke leather fit',
                'featured_image' => 'theme-assets/rjleather.theme-storefront/85b69bcc544e78bb81afb64432822a833687da17549bd110202b9b584f76dfe9/dist/images/hero.jpg',
                'content' => '
                    <p>A true leather jacket should feel like an extension of your own skin. Unlike mass-manufactured off-the-rack outerwear, a <strong>made-to-measure leather motorcycle jacket</strong> contours to your unique posture, arm reach, and chest profile. However, the secret to a bespoke fit lies entirely in accurate measurements.</p>
                    
                    <h2>What You Need Before Starting</h2>
                    <ul>
                        <li>A flexible fabric tailor tape measure (do not use a rigid metal builder tape).</li>
                        <li>Wear the type of undergarment or layer you plan to wear underneath your jacket (e.g. a t-shirt or riding hoodie).</li>
                        <li>Stand naturally upright with your arms relaxed at your sides. Having a friend assist you guarantees pinpoint precision.</li>
                    </ul>

                    <h2>1. Chest Circumference (Primary Metric)</h2>
                    <p>Wrap the tape measure under your armpits, across the fullest part of your chest and shoulder blades. Keep the tape parallel to the floor and breathe normally. Do not puff out your chest or hold your breath.</p>

                    <h2>2. Shoulder Width</h2>
                    <p>Measure across the natural curve of your upper back from the tip of your left shoulder bone across to the tip of your right shoulder bone. The seam of a custom jacket should sit precisely atop this bone.</p>

                    <h2>3. Sleeve Length</h2>
                    <p>Place one end of the tape at the outer point of the shoulder bone and run it down the outside of your arm, past your elbow, down to just past your wrist bone where your thumb begins. If you ride, bend your elbow at a 45-degree angle to account for motorcycle handlebar reach.</p>

                    <h2>4. Waist & Hip Circumference</h2>
                    <p>Measure around your natural waistline (just above your navel or where you wear your belt). For longer cuts like car coats or duster coats, also measure around the widest part of your hips.</p>

                    <h2>5. Back Length (Collar to Hem)</h2>
                    <p>Measure from the base of the back of your neck (the prominent vertebra) straight down to where you want the jacket hem to fall. Traditional biker jackets sit right at the belt line, while cafe racer jackets often sit slightly lower.</p>

                    <h2>The RJ Motorbike Tailoring Promise</h2>
                    <p>When you select <em>Custom Size (+ $95.00)</em> on any RJ Motorbike jacket, our master tailors draft an individual pattern tailored to your exact measurements. With premium full-grain hides and reinforced seams, your jacket is built to protect and last a lifetime.</p>
                ',
                'is_published' => true,
                'published_at' => now()->subDays(10),
            ],
            [
                'title' => 'Biker vs Bomber vs Cafe Racer: Which Motorcycle Leather Jacket Fits Your Style?',
                'slug' => 'biker-vs-bomber-vs-cafe-racer-leather-jacket-guide',
                'excerpt' => 'Compare the three quintessential leather silhouettes: the asymmetric double-rider biker jacket, the military MA-1 bomber, and the streamlined cafe racer.',
                'meta_title' => 'Biker vs Bomber vs Cafe Racer Leather Jackets | RJ Motorbike',
                'meta_description' => 'Explore the differences between biker, bomber, and cafe racer leather jackets. Find the right silhouette, protection level, and leather finish for your lifestyle.',
                'meta_keywords' => 'biker vs bomber leather jacket, cafe racer motorcycle jacket, double rider leather jacket, mens leather jacket styles, motorcycle outerwear comparison',
                'featured_image' => 'theme-assets/rjleather.theme-storefront/85b69bcc544e78bb81afb64432822a833687da17549bd110202b9b584f76dfe9/dist/images/hero.jpg',
                'content' => '
                    <p>The leather jacket has defined rebellion, aviation, and motorcycle culture for over a century. Yet when deciding on a handcrafted investment piece, understanding the differences in silhouette, functional design, and wind protection is crucial.</p>

                    <h2>1. The Classic Double-Rider Biker Jacket</h2>
                    <p>Pioneered in 1928, the classic motorcycle biker jacket features an <strong>asymmetrical front zipper</strong>, wide lapels with snap buttons, zippered cuffs, and often an integrated waist belt. The offset zipper was designed to prevent wind from cutting through when leaning forward over a motorcycle tank.</p>
                    <p><strong>Best For:</strong> Cruisers, rock-and-roll aesthetics, and riders demanding maximum chest windproofing.</p>

                    <h2>2. The Aviation Bomber Jacket (A-2 & MA-1 Heritage)</h2>
                    <p>Originally engineered for open-cockpit fighter pilots in World War II, the leather bomber is characterized by <strong>ribbed knit cuffs and hem</strong>, a central heavy-duty zip, and a roomier shoulder cut. Bombers excel in casual comfort, trapping insulating warmth close to the torso.</p>
                    <p><strong>Best For:</strong> Everyday streetwear, cooler weather commutes, and pairing with denim.</p>

                    <h2>3. The Minimalist Cafe Racer</h2>
                    <p>Born in post-war 1960s London among riders who modified their motorbikes to race between roadside transport cafes, the Cafe Racer is pure minimalism. Featuring a <strong>clean mandarin snap collar</strong>, centered zipper, and streamlined vertical pockets, it eliminates excess fabric for optimal aerodynamic speed.</p>
                    <p><strong>Best For:</strong> Sport bikes, modern classics, minimalist wardrobes, and riders who prefer subtle, understated elegance.</p>

                    <h2>Handcrafted in Full-Grain Hides</h2>
                    <p>At RJ Motorbike, every silhouette is hand-cut from hand-selected cowhide, buffalo, or sheepskin hides. Choose your lining, customize your hardware, and select your armor pocket configurations for a personalized finish.</p>
                ',
                'is_published' => true,
                'published_at' => now()->subDays(7),
            ],
            [
                'title' => 'Full-Grain vs Top-Grain Cowhide: The Rider\'s Guide to Leather Durability and Care',
                'slug' => 'full-grain-vs-top-grain-leather-durability-guide',
                'excerpt' => 'Understand leather grades, abrasion resistance, natural patina development, and the essential maintenance steps that keep your motorcycle hide supple for decades.',
                'meta_title' => 'Full-Grain vs Top-Grain Leather Guide | RJ Motorbike',
                'meta_description' => 'Discover why full-grain cowhide provides superior motorcycle abrasion resistance compared to top-grain or genuine leather. Master craftsman leather care guide.',
                'meta_keywords' => 'full grain vs top grain leather, motorcycle leather durability, cowhide leather abrasion resistance, how to clean leather motorcycle jacket',
                'featured_image' => 'theme-assets/rjleather.theme-storefront/85b69bcc544e78bb81afb64432822a833687da17549bd110202b9b584f76dfe9/dist/images/hero.jpg',
                'content' => '
                    <p>Not all leather is created equal. When a jacket represents both your personal aesthetic and your primary defense against asphalt abrasion, knowing how leather grades differ can make all the difference on the open road.</p>

                    <h2>The Anatomy of an Animal Hide</h2>
                    <p>A raw hide consists of several layers: the dense grain layer on the surface, the fibrous junction, and the lower corium. The surface grain contains the highest concentration of interlocking collagen fibers, making it the strongest natural barrier known to apparel manufacturing.</p>

                    <h2>Full-Grain Leather (The Gold Standard)</h2>
                    <p><strong>Full-grain leather</strong> uses the complete outermost surface of the hide without sanding or buffing away natural imperfections. Because the grain remains entirely intact:</p>
                    <ul>
                        <li>It delivers the highest tensile strength and tear resistance.</li>
                        <li>It resists moisture and wind penetration naturally.</li>
                        <li>Rather than deteriorating with age, it develops a deep, lustrous <em>patina</em> that tells the story of every ride.</li>
                    </ul>

                    <h2>Top-Grain & Corrected Leather</h2>
                    <p>Top-grain leather has had the very top layer lightly sanded to remove scars or insect bites, followed by an embossed synthetic grain. While uniform in appearance, the sanding process reduces overall abrasion resistance by up to 25% compared to untouched full-grain.</p>

                    <h2>How to Care for Your Motorcycle Leather</h2>
                    <ol>
                        <li><strong>Wipe Down After Rides:</strong> Use a damp microfiber cloth to remove bugs, road salt, and road grime before they dry out the pores.</li>
                        <li><strong>Condition Seasonally:</strong> Apply a natural beeswax or lanolin-based leather balm twice a year to maintain hide hydration and prevent stiffness.</li>
                        <li><strong>Never Use Direct Heat:</strong> If caught in a rainstorm, always hang your jacket on a wide, contoured wooden hanger at room temperature. Never place it next to a radiator.</li>
                    </ol>
                ',
                'is_published' => true,
                'published_at' => now()->subDays(5),
            ],
            [
                'title' => 'MotoGP Race Suits Explained: One-Piece vs Two-Piece, Armor Levels and Track Standards',
                'slug' => 'motogp-race-suits-one-piece-vs-two-piece-track-guide',
                'excerpt' => 'A breakdown of high-performance track protection: CE Level 2 armor, aerodynamic speed humps, titanium shoulder sliders, and 1-piece vs 2-piece leather configurations.',
                'meta_title' => 'MotoGP Race Suits: 1-Piece vs 2-Piece Protection Guide | RJ Motorbike',
                'meta_description' => 'Learn track day safety requirements, CE Level 2 armor, aerodynamic speed humps, and the differences between 1-piece and 2-piece motorcycle leather race suits.',
                'meta_keywords' => 'motogp leather suit, 1 piece vs 2 piece motorcycle race suit, track day leather suit, ce level 2 motorcycle armor, custom race suit made to measure',
                'featured_image' => 'theme-assets/rjleather.theme-storefront/85b69bcc544e78bb81afb64432822a833687da17549bd110202b9b584f76dfe9/dist/images/hero.jpg',
                'content' => '
                    <p>When riding at high speed on track day circuits, proper equipment is non-negotiable. Whether you are aiming for weekend track days or competitive club racing, choosing between a <strong>1-piece</strong> and <strong>2-piece race suit</strong> dictates your protection, flexibility, and track compliance.</p>

                    <h2>1-Piece Leather Race Suits (Pure Track Focus)</h2>
                    <p>The 1-piece race suit is the premier choice for circuit racing and track days. Cut in a pre-curved, forward-tuck riding posture, a 1-piece suit eliminates the waist zipper seam, ensuring that the suit cannot separate during a high-speed slide across tarmac.</p>
                    <p><strong>Key Advantages:</strong> Maximum structural integrity, zero waist pinch points, aerodynamic race hump integration, and universal track day sanctioning.</p>

                    <h2>2-Piece Race Suits (Versatility for the Road & Canyon)</h2>
                    <p>A 2-piece suit connects a dedicated racing jacket to leather race pants via a full-circumference 360-degree heavy-duty zipper. This allows you to unzip and remove the jacket during lunch stops or wear the jacket independently on casual road rides.</p>
                    <p><strong>Key Advantages:</strong> High versatility, ease of putting on and taking off, and excellent road touring protection.</p>

                    <h2>Critical Safety Features in RJ Motorbike Suits</h2>
                    <ul>
                        <li><strong>1.2mm – 1.4mm Full-Grain Cowhide or Kangaroo:</strong> Maximum abrasion resistance with low weight and high tear threshold.</li>
                        <li><strong>CE Level 2 Certified Armor:</strong> Internal protection across shoulders, elbows, hips, and knees.</li>
                        <li><strong>External TPU & Titanium Sliders:</strong> Deflect impact energy and enable smooth sliding on track surfaces.</li>
                        <li><strong>Kevlar Stretch Panels:</strong> Strategic accordion leather and Schoeller-Keprotec stretch fabric in the crotch, calves, and underarms for full rider mobility.</li>
                    </ul>
                ',
                'is_published' => true,
                'published_at' => now()->subDays(3),
            ],
            [
                'title' => 'Iconic Movie Leather Jackets: The History, Style and How to Wear Them Today',
                'slug' => 'iconic-movie-leather-jackets-history-and-styling-guide',
                'excerpt' => 'From Top Gun\'s G-1 flight jacket to Tyler Durden\'s crimson Fight Club coat and Wolverine\'s brown cafe racer, explore the cinema history of legendary leather outerwear.',
                'meta_title' => 'Iconic Movie Leather Jackets: Cinema History & Style | RJ Motorbike',
                'meta_description' => 'Explore the story and craftsmanship behind cinema\'s most famous leather jackets: Top Gun G-1, Fight Club red coat, Wolverine cafe racer, and Terminator.',
                'meta_keywords' => 'movie leather jackets, top gun maverick leather jacket, fight club tyler durden red jacket, wolverine brown leather jacket, film jackets handmade',
                'featured_image' => 'theme-assets/rjleather.theme-storefront/85b69bcc544e78bb81afb64432822a833687da17549bd110202b9b584f76dfe9/dist/images/hero.jpg',
                'content' => '
                    <p>Nothing establishes a movie character\'s grit, charisma, and independence faster than a legendary leather jacket. Over decades of cinema, specific leather designs have transcended the silver screen to become timeless wardrobe centerpieces.</p>

                    <h2>1. The Top Gun Maverick G-1 Flight Jacket</h2>
                    <p>Worn by Tom Cruise as Pete "Maverick" Mitchell, the naval G-1 aviator jacket features genuine mouton shearling collar, bi-swing back for arm reach, knit cuffs, and authentic military squadron patches. Originally issued to US Navy pilots in the 1930s, this jacket remains the definitive symbol of aerial bravado.</p>

                    <h2>2. Tyler Durden\'s Red Leather Jacket in Fight Club</h2>
                    <p>Brad Pitt\'s portrayal of Tyler Durden disrupted 90s fashion with an unapologetic, 1970s-inspired red leather coat with contrast white stitching and exaggerated notch lapels. At RJ Motorbike, our craftsmen reproduce this piece with supple, wax-finished sheepskin for authentic drape and mobility.</p>

                    <h2>3. Wolverine\'s Brown Cafe Racer (X-Men Origins)</h2>
                    <p>Hugh Jackman\'s Wolverine jacket pairs rugged brown cowhide with tan racing stripes down the sleeves. Featuring a snap tab collar and gusseted shoulders, it embodies utilitarian durability tailored for action.</p>

                    <h2>4. Terminator 2: Judgment Day Biker</h2>
                    <p>Arnold Schwarzenegger\'s arrival in Terminator 2 immortalized the heavy black cowhide asymmetrical biker with silver snaps and an industrial waist belt. It remains the gold standard of mechanical menace and motorcycling heritage.</p>

                    <h2>Screen Accuracy Meets Made-to-Measure Comfort</h2>
                    <p>At RJ Motorbike, our film jackets are not synthetic Halloween costumes. Each piece is constructed from real full-grain cowhide or lambskin, tailored to your exact measurements so you can wear it with pride on your bike or in the city.</p>
                ',
                'is_published' => true,
                'published_at' => now()->subDay(),
            ],
        ];

        foreach ($posts as $postData) {
            Post::updateOrCreate(
                ['slug' => $postData['slug']],
                $postData
            );
        }
    }
}

