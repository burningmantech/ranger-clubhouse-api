<?php
namespace App\Helpers;

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
    public static $PHONETIC = array(
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
        'X-ray',
        'Yankee',
        'Zulu'
    );

    /**
     * Places, camps, locations
     */
    public static $LOCATIONS = array(
        'Airport',
        'Arctica',
        'Artery',
        'Berlin',
        'Black Hole',
        'B M I R',
        'Burning Man',
        'Cafe',
        'Camp',
        'Census',
        'Center Camp',
        'Center Camp Cafe',
        'Commissary',
        'Depot',
        'D M Z', 'Dance Music Zone',
        'Esplanade',
        'First Camp',
        'Gate',
        'Gerlach',
        'Ghetto',
        'Greeters',
        'H Q',
        'Hat Rack',
        'Heat',
        'Heavy Equipment',
        'Hell Station',
        'Ice',
        'Keyhole',
        'Man',
        'Media Mecca',
        'Moscow',
        'Outpost',
        'Playa',
        'Playa Info',
        'Plaza',
        'Post Office',
        'Rampart', 'Ramparts',
        'Sanctuary',
        'Shack',
        'Station',
        'Stick',
        'Temple',
        'The Man',
        'The Stick',
        'Tokyo',
        'V Spot',
        'Village',
        'Wet Spot',
        'Zendo',
    );

    /**
     * Radio terms
     */
    public static $RADIO_JARGON = array(
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
        'Delta Unit',
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
        'Medical',
        'Negative',
        'Open Mike',
        'Out',
        'Over',
        'Repeat',
        'S A',
        'Sierra Alpha',
        'Ten',
        'Transmission',
    );

    /**
     * Other Burning Man or Ranger terms
     */
    public static $RANGER_JARGON = array(
        '007',
        'Admin',
        'Alpha',
        'Art Car',
        'BLM',
        'Blue Dot',
        'B M I T',
        'Burn',
        'Burner Express',
        'BxAir',
        'BxBus',
        'Cadre',
        'Captain Hook',
        'Cheetah',
        'Clubhouse',
        'Control One',
        'Control Two',
        'D P W',
        'Dispatch',
        'Dirt',
        'D O O D', 'DOOD', 'Dude', 'The Dude',
        'Double Oh Seven',
        'E S D',
        'Echelon', 'Eschelon',
        'Eviction',
        'Exodous',
        'Green Dot',
        'Greeters',
        'Hot Springs Patrol',
        'I C',
        'I M S',
        'Intercept',
        'Kasbah',
        'Khaki',
        'Kitchen Sink',
        'L E',
        'Lamplighters',
        'Lead',
        'LEAL',
        'Legal 2000',
        'Leopard',
        'Lighthouse',
        'Local',
        'Lockout',
        'Logan',
        'Logistics',
        'Logs',
        'Medic',
        'Mentee', 'Minty',
        'Mentor',
        'Network Support',
        'O O D',
        'Operator',
        'Orange Dot',
        'Participant',
        'Perimeter',
        'Pershing',
        'Personnel',
        'Placement',
        'Police',
        'Quad Lead',
        'Quadrant',
        'Quartermaster',
        'R N R',
        'Radio',
        'Ranger',
        'Red Dot',
        'R S C I', 'Risky',
        'ROC', 'R O C', 'Rock',
        'Sandman',
        'Secret Clubhouse',
        'Shiny Penny',
        'Site',
        'Tac',
        'Talk',
        'Tech Team',
        'The Bridge',
        'Tow Truck',
        'Triple A',
        'Troubleshooter',
        'Vehicle',
        'Washoe', 'Washo',
        'Wrangler',
        'Yellow Shirt',
        'Zebra',
    );

    /**
     * Callsigns from the 2017 TWII Radio Directory
     */
    public static $VIPS = array(
        'Alipato',
        'Audacity',
        'Ballyhoo Betty',
        'Barbarino',
        'Bee',
        'Bettie June',
        'Big Bear',
        'Bobalou',
        'Bobzilla',
        'Brody',
        'CameraGirl',
        'Carlos Danger',
        'Carry On',
        'Cat',
        'Centerstage',
        'Chaos',
        'Cheap Tequila',
        'Cherry Cake',
        'Cobra Commander',
        'Comm Chief',
        'Coyote',
        'Crimson',
        'Crimson Rose',
        'Cupcake',
        'Danger Ranger',
        'Dave X',
        'Deputy',
        'Doug E Fresh',
        'Dougie Fresh',
        'Ducky',
        'Elecktra',
        'Fearless Leader',
        'Fireball',
        'Fireclown',
        'Fitterkit',
        'Flying Squirrel',
        'Free Fall',
        'HazMatt',
        'Heady',
        'HeyHey',
        'Hotshot',
        'J Kanizzle',
        'Jack Rabbit',
        'JannyPan',
        'Jeremy',
        'Jocko',
        'Joe the Builder',
        'Juno',
        'Kai Ocean',
        'Kat',
        'Kearce',
        'Kez',
        'Kimba',
        'Liptonite',
        'Lorax',
        'Louder Charlie',
        'Lulu',
        'M O D',
        'Make-Out Queen', 'Makeout Queen',
        'Mandy',
        'Marcia',
        'Marsha',
        'Markle',
        'MarklePony',
        'Mary Poppins',
        'Megulate',
        'Michael Wolf',
        'Miss Kelly',
        'Mockingbird',
        'Mr. Blue', 'Mister Blue',
        'Mr. Klean', 'Mister Klean',
        'Mrs. Klean', 'Missus Klean', 'Miss Klean',
        'Nimbus',
        'Nondas',
        'Oh My God',
        'Pedro',
        'Plans Chief',
        'Playground',
        'Plus One',
        'Pornstar',
        'Raspa',
        'Rebel',
        'Retro',
        'Roadrunner',
        'RonJon',
        'Safety 1',
        'Scorch',
        'Settle Down',
        'Snotto',
        'Spanky',
        'Spark Plug',
        'Sparky',
        'Support Chief',
        'SweetTea',
        'Swordfish',
        'Sylkia',
        'System',
        'Tabasco',
        'Taz',
        'Topless Deb',
        'Trippi',
        'Valkyrie',
        'Wanda Power',
        'Weapons Grade',
        'Will Chase',
        'Winston',
        'Wrangler',
        'Wrench',
        'Yardsale',
        'Yvel Q',
        'Ziptie',
    );

    /**
     * The following callsigns are Rangers who have either moved on to another
     * department (e.g., Gate, ESD) or who were involved in an incident of
     * some sort long ago and thus their handle is bad juju in some way.
     */
    public static $RESERVED = array(
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
