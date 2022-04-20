<?php
namespace App\Lib;

/**
 * This here is your master list of reserved callsigns.  They come in several
 * flavors for your callsign resticting enjoyment:
 *
 *    Places, camps, and locations
 *    Radio jargon and brevity codes
 *    Other Ranger and Burning Man terms
 *    Callsigns used by VIPs in other departments (from TWII radio directory)
 *    Callsigns that Ranger Council considers reserved, usually because
 *        the person in question was either famous, moved to a different
 *        department but still uses the callsign, or was involved in an
 *        incident of some sort, and thus the callsign has bad juju.
 *
 * This file is used for two different tests:
 *
 *    Literal, in which everything is converted to lower case, spaces removed,
 *    and strings compared for exact match against a new callsign.  So this
 *    allows a callsign like "Safety Phil" to also match "safetyphil".
 *
 *    Phonetic, in which the callsigns are passed on to a phonetic matcher.
 *    This is why some callsigns below are spelled out, e.g., "E S D" instead
 *    of "ESD".
 */
class ReservedCallsigns
{
    /** NATO phonetic alphabet */
    const PHONETIC = array(
        'Alfa',
        'Bravo',
        'Charlie',
        'Delta',
        'Echo',
        'Foxtrot',
        'Golf',
        'Hotel',
        'India',
        'Juliett',
        'Kilo',
        'Lima',
        'Mike',
        'November',
        'Oscar',
        'Papa',
        'Quebec',
        'Romeo',
        'Sierra',
        'Tango',
        'Uniform',
        'Victor',
        'Whiskey',
        'X-ray',
        'Yankee',
        'Zulu'
    );

    /**
     * Places, camps, locations
     */
    const LOCATIONS = array(
        'Arctica',
        'Artery',
        'Berlin',
        'Black Hole',
        'B M I R',
        'Burning Man',
        'Cafe',
        'Center Camp',
        'Center Camp Cafe',
        'Commissary',
        'Depot',
        'Esplanade',
        'First Camp',
        'Gerlach',
        'Ghetto',
        'H G H',
        'H Q',
        'Man',
        'Moscow',
        'Outpost',
        'Playa',
        'Playa Info',
        'Rampart', 'Ramparts',
        'Stick',
        'Temple',
        'The Man',
        'The Stick',
        'Tokyo',
        'V-Spot',
        'Zendo',
    );

    /**
     * Radio terms
     */
    const RADIO_JARGON = array(
        'Affirmative',
        'Affirm',
        'Allcom',
        'Ambulance',
        'Black Rock',
        'Break', 'Break Break', 'Break Break Break',
        'Clear',
        'Com',
        'Copy',
        'Copy That',
        'Delta Victor',
        'D V',
        'Emergency',
        'Fire',
        'Fire response',
        'Fire engine',
        'Fire truck',
        'Found child',
        'Lost child',
        'Mayday',
        'Negative',
        'Open Mike',
        'Out',
        'Over',
        'S A',
        'Sierra Alpha',
    );

    /**
     * Other Burning Man or Ranger terms
     */
    const RANGER_JARGON = array(
        '007',
        'Alpha',
        'BLM',
        'Blue Dot',
        'Burn',
        'Captain Hook',
        'Cheetah',
        'D M V',
        'D P W',
        'Dispatch',
        'D O O D', 'DOOD', 'Dude', 'The Dude',
        'Double Oh Seven',
        'E S D',
        'Echelon', 'Eschelon',
        'Exodus',
        'Gate',
        'Green Dot',
        'Greeters',
        'Intercept',
        'Khaki',
        'L E',
        'Lamplighters',
        'Larry',
        'LEAL',
        'Legal 2000',
        'Leopard',
        'Lighthouse',
        'Medic',
        'Mentee', 'Minty',
        'Mentor',
        'O O D',
        'Open Mic',
        'Participant',
        'Perimeter',
        'Pershing',
        'Police',
        'Puppy',
        'R N R',
        'Radio',
        'Ranger',
        'Red Dot',
        'ROC', 'R O C', 'Rock',
        'Shiny Penny',
        'Washoe', 'Washo',
        'Yellow Shirt',
        'Zebra',
    );

    /**
     * Callsigns from the 2017 TWII Radio Directory.  Remove when adding 2020 TWII handles.
     */
    const TWII_2017 = array(
        'Alipato',
        'Alpha1',
        'Athibat',
        'Audacity',
        'Avi8trix', 'Aviatrix',
        'Ballyhoo Betty',
        'Bee',
        'Bill Murray',
        'Bobalou',
        'Bobcat Queen',
        'Boyfriend',
        'Breedlove',
        'Brody',
        'CameraGirl',
        'Carlos Danger',
        'Carnitas Queen',
        'Carry On',
        'Cat',
        'Centerstage',
        'Chaos',
        'Cheap Tequila',
        'Cherry Cake',
        'Circus Mark',
        'Cobra Commander',
        'Comm Chief',
        'Coyote',
        'Cuervo',
        'Cupcake',
        'DV8',
        'Danger Ranger',
        'Dave X',
        'Dee Zaster',
        'Deviate',
        'Dominique',
        'Doug E Fresh',
        'Dr Scirpus',
        'Doctor Scirpus',
        'Ducky',
        'Elecktra',
        'Eyefleye',
        'Fathom',
        'Fearless Leader',
        'Fireball',
        'Fireclown',
        'Flitterkit',
        'Flying Squirrel',
        'Free Fall',
        'Gerbil',
        'Glitterfly',
        'HR Dispatch',
        'HazMatt',
        'Heady',
        'Hella',
        'HeyHey',
        'Homeboy',
        'HotShot',
        'J Kanizzle',
        'Jack Rabbit',
        'Jalapeña', 'Jalapena',
        'JannyPan',
        'Jeremy',
        'Jocko',
        'Juno',
        'Kai Ocean',
        'Katie Hazard',
        'Kearce',
        'Kez',
        'Kiki',
        'Kim Cook',
        'KitKat',
        'Koko',
        'Liptonite',
        'Logs 1',
        'Lorax',
        'Louder Charlie',
        'Lulu',
        'M O D',
        'Make-Out Queen', 'Makeout Queen',
        'Manatou',
        'Mandy',
        'Marcia',
        'MarklePony',
        'Mary Poppins',
        'Megulate',
        'Mi\'ao',
        'Miss Kelly',
        'Mockingbird',
        'MommaBear',
        'Moonbeam',
        'Morsa Jones',
        'Mr. Blue', 'Mister Blue',
        'Mr. Klean', 'Mister Klean',
        'Mrs. Klean', 'Missus Klean', 'Miss Klean',
        'Muppet',
        'Network Support',
        'Nimbus',
        'Oh My God',
        'Open Mic',
        'Orange',
        'Plans Chief',
        'Playground',
        'Plus One',
        'Porcelain',
        'President',
        'Raspa',
        'Rebel',
        'Reno',
        'Retro',
        'RobO',
        'RonJon',
        'Safety 1',
        'Sassette',
        'Sauce',
        'Savage',
        'Sawdust',
        'Scorch',
        'Serrah',
        'Settle Down',
        'Shadow',
        'Sheriff',
        'Shortcake',
        'Snotto',
        'Spanky',
        'Spark Plug',
        'Sparkle Ann',
        'Sparky',
        'Support Chief',
        'Swordfish',
        'Sylkia',
        'Tabasco',
        'The Magpie',
        'Toi Box',
        'Topless Deb',
        'Traveler',
        'Trippi',
        'Valkyrie',
        'Wanda Power',
        'Winston',
        'Wrangler',
        'Wrench',
        'Wristy',
        'Yvel Q',
        'Ziptie',
    );

    /**
     * Callsigns from the 2018 TWII Radio Directory.  Remove when adding 2021 TWII handles.
     */
    const TWII_2018 = array(
        'Admin 10',
        'Alipato',
        'Alpha1',
        'Anti-M',
        'Athibat',
        'Audacity',
        'Ballyhoo Betty',
        'Bee',
        'Bobzilla',
        'Breedlove',
        'Brody',
        'BxAir', 'B X Air',
        'BxBus', 'B X Bus',
        'CameraGirl',
        'Carlos Danger',
        'Carnitas Queen',
        'Carry On',
        'Cat',
        'Chaos',
        'Charlie',
        'Cheap Tequila',
        'Cherry Cake',
        'Cherub',
        'Cobra Commander',
        'Coyote',
        'Crickets',
        'Cuervo',
        'Cupcake',
        'Danger', 'Danger Ranger',
        'Dave X',
        'D M V',
        'Dominique',
        'Double Agent',
        'Doug E Fresh',
        'DV8', 'Deviate',
        'Elecktra',
        'Emma Weisman',
        'Fearless Leader',
        'Figit',
        'Fireball',
        'Fireclown',
        'Flitterkit',
        'Flying Squirrel',
        'Free Fall',
        'Gerbil',
        'HR Dispatch',
        'HazMatt',
        'Heady',
        'Hella',
        'HeyHey',
        'Hollywood',
        'HotShot',
        'Hotspot',
        'J Kanizzle',
        'Jack Rabbit',
        'Jedi Master',
        'Jeremy',
        'Jocko',
        'Juno',
        'Kaalin',
        'Kai Ocean',
        'Katie Hazard',
        'Kato',
        'Kearce',
        // Khaki in Ranger Jargon
        'Kimba',
        'Koko',
        'Kristy',
        'Liptonite',
        'Lorax',
        'Louder Charlie',
        'Lulu',
        'M3',
        'M O D', 'Manager On Duty',
        'Make-Out Queen', 'Makeout Queen',
        'Manatou',
        'Mango',
        'MarklePony',
        'Megulate',
        'Mi\'ao',
        'Miss Kelly',
        'Mockingbird',
        'MommaBear',
        'Mr. Blue', 'Mister Blue',
        'Mr. Klean', 'Mister Klean',
        'Mrs. Klean', 'Missus Klean', 'Miss Klean',
        'Muppet',
        'Network Support',
        'Nimbus',
        // O O D in Ranger Jargon
        'Oh My God',
        'Pedro',
        // Playa Info in Locations
        'Playground',
        'Plus One',
        'President',
        'Propaniac',
        'Radio 1',
        'Raspa',
        'Rebel',
        'Retro',
        'Roadrunner',
        'RonJon',
        'Safety 1',
        'Sauce',
        'Scorch',
        'Sergio',
        'Settle Down',
        'Showtime',
        'Skull-lee', 'Scully',
        'Snotto',
        'Sonder',
        'Spanky',
        'Spark Plug',
        'Spitfyre',
        'SweetTea',
        'Sylkia',
        'Tabasco',
        'Tech Support',
        'The Magpie',
        'Tinder',
        'Topless Deb',
        'Tranquility',
        'Trippi',
        // V-Spot in Locations
        'Valkyrie',
        'Wanda Power',
        'Weapons Grade',
        'Winston',
        'Wrangler',
        'Wrench',
        'Yardsale',
        'Yvel Q',
        'Ziptie',
    );

    /**
     * VIP handles include the last three years of people mentioned in The Way It Is radio
     * directory.  When adding a new year's TWII handles, remove the array from three years prior.
     * Function rather than a constant because PHP constants can't be based on functions like
     * sort.
     */
    public static function twiiVips() {
        $result = array_merge(ReservedCallsigns::TWII_2018, ReservedCallsigns::TWII_2017);
        sort($result);
        return array_unique($result);
    }

    /**
     * The following callsigns are Rangers who have either moved on to another
     * department (e.g., Gate, ESD) or who were involved in an incident of
     * some sort long ago and thus their handle is bad juju in some way.
     */
    const RESERVED = array(
        'Joshua', // Crow  9/2015: Moved to ESD
        'Golddust', // Gone to Gate
        'Mr. E', 'Mister E', // Crow  9/2015: Bad handle / incident
        'Chaos', 'Kaos', // Crow  9/2015: Bad handle / multiple departments
        'Lightning', // Crow  9/2015: Bad handle / multuple departments
        'Password', // Crow  9/2015: Vintage', 'may return
        'Queen B', 'Queen Bee', // Crow  9/2015: Incident
        'Saratonin', 'Sarah Tonin', // Crow  9/2015: Incident
        'Detour', // Crow  9/2015: Bad handle', 'other depts (DPW)
        'Jet Lag', // Crow  9/2015: Incident
        'Hazmat', // Crow  9/2015: bad handle / multiple departments
        'Fresno', // Crow  9/2015: infamous
        'Medic-4', // Crow  9/2015: ESD
        'Pyro Boy', // Crow  9/2015: infamous
        'Bluecross', // Gate
        'Icehole', 'Ice', // Gate
        'Kristy', // Gate
        'Huckleberry', // Crow 2/2018
    );
}
