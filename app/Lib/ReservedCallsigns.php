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
        'H Q',
        'Man',
        'Moscow',
        'Outpost',
        'Playa',
        'Playa Info',
        'Rampart', 'Ramparts',
        'ROC', 'R O C', 'Rock',
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
        'Dead', 'Death',
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
        'Open Mike', 'Open Mic',
        'Out',
        'Over',
        'Roger',
        'S A',
        'Sierra Alpha',
    );

    /**
     * Other Burning Man or Ranger terms
     */
    const RANGER_JARGON = array(
        '007', 'Double Oh Seven', // Ranger role
        'Alpha', // Callsign prefix during mentor shifts
        'BLM', // law enforcement
        'Blue Dot', // Ranger role (former)
        'Burn', // frequent tactical callsign prefix
        'Captain Hook', // tactical callsign
        'Cheetah', // Ranger role
        'D M V', // BRC department
        'D P W', // BRC department
        'Dispatch', // ESD tactical callsign
        'D O O D', 'DOOD', 'Dude', 'The Dude', // tactical callsign
        'E S D', // BRC department
        'Echelon', 'Eschelon', // tactical callsign
        'Exodus', // BRC department
        'Gate', // BRC department
        'Green Dot', // Ranger role
        'Greeters', // BRC department
        'High Rock', // Security tactical callsign
        'HQ Window', // tactical callsign
        'I C', 'IC', 'Icy', 'Incident Commander', // tactical callsign
        'Intercept', // tactical callsign
        'Khaki', // tactical callsign
        'L E', 'LE', // law enforcement
        'Lamplighters', // BRC department
        'Larry', // BRC founder
        'LEAL', // Ranger role
        'Legal 2000', // mental health evaluation
        'LEO', 'Leo', // law enforcement
        'Lighthouse', // Perimeter tactical callsign
        'Logan', // tactical callsign
        'Medic', // calls for emergency
        'Mentee', 'Minty', // Ranger role
        'Mentor', // Ranger role
        'O O D', 'OOD', 'Ood', 'Oud', // tactical callsign
        'Participant', // frequent subject of Ranger radio calls
        'Perimeter', // BRC department
        'Pershing', // law enforcement
        'Police', // law enforcement
        'R N R', // Ranger role
        'Radio', // frequent topic of conversation
        'Ranger', // Ranger role
        'Red Dot', // Ranger role (former)
        'Ringleader', // BPSG tactical callsign
        'Security', // tactical callsign
        'Shiny Penny', // Ranger role
        'Washoe', 'Washo', // law enforcement
        'W E S L', 'WESL', 'Weasel', // tactical callsign
        'Yellow Shirt', // ESD jargon
        'Zebra',
    );

    /**
     * Callsigns from the 2018 TWII Radio Directory.  Remove when adding 2023 TWII handles.
     */
    const TWII_2018 = array(
        'Admin 10',
        'Alipato',
        'Alpha 1',
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
        'DMV',
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
        'Louder, Charlie',
        'Lulu',
        'M3',
        'M O D', 'Manager on Duty',
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
        'OhMyGod',
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
        'the Magpie',
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
     * Callsigns from the 2019 TWII Radio Directory.  Remove when adding 2024 TWII handles.
     */
    const TWII_2019 = array(
        'AbiNormal',
        'Admin 10',
        'Ain Frog',
        'Alipato',
        'Alpha 1',
        'Anti-M',
        'Athibat',
        'Audacity',
        'Bee',
        'Bliss',
        'Breedlove',
        'Brody',
        'BxAir',
        'BxBus',
        'Cailen',
        'CameraGirl',
        'Carlos Danger',
        'Carnitas Queen',
        'Cat',
        'ChAos',
        'Cheap Tequila',
        'Cherub',
        'Cliff',
        'Cobra Commander',
        'Coyote',
        'Cuervo',
        'Cujo',
        'Cupcake',
        'DMV',
        'DV8',
        'Danger Ranger',
        'Dave X',
        'Dick Tracy',
        'Dominique',
        'Double Agent',
        'Doug E Fresh',
        'Dr Scirpus',
        'Dustin',
        'Elecktra',
        'Fearless Leader',
        'Figit',
        'Fireball',
        'Fireclown',
        'Flitterkit',
        'Flying Squirrel',
        'Free Fall',
        'Gadget',
        'Great Question',
        'HR Dispatch',
        'HazMatt',
        'Heady',
        'HotShot',
        'Hotspot',
        'Jack Rabbit',
        'Jedi Master',
        'Jeremy',
        'Jocko',
        'Juno',
        'Kaalin',
        'Kai Ocean',
        'Kanizzle',
        'Katie Hazard',
        'Kato',
        'Kearce',
        'Kimba',
        'Koko',
        'Kristy',
        'Level',
        'Liptonite',
        'Lorax',
        'Louder, Charlie',
        'Lulu',
        'M3',
        'MOD',
        'Manager on Duty',
        'Mango',
        'Marcia',
        'MarklePony',
        'Megulate',
        'Mi\'ao',
        'Miss Kelly',
        'Mockingbird',
        'Motorbike Matt',
        'mr. blue',
        'Mr. Klean',
        'MrBill',
        'Mrs. Klean',
        'Muppet',
        'Murphy',
        'Natalie',
        'Natasha',
        'Network Support',
        'Nimbus',
        'Other Barry',
        'Pedro',
        'Pirate Queen',
        'Playa Info',
        'Playground',
        'Plus One',
        'President',
        'Quarantine',
        'Radio 1',
        'Raspa',
        'Rebel',
        'Retro',
        'Rikki Thompson',
        'Safety 1',
        'Sauce',
        'Sawdust',
        'Scorch',
        'Secret Mission',
        'Sergio',
        'Shanwow',
        'Skull-lee',
        'Snotto',
        'Sonder',
        'Spanky',
        'Spark Plug',
        'Spitfyre',
        'Strong Arm',
        'SweetTea',
        'Sylkia',
        'Tabasco',
        'Tech Support',
        'the Magpie',
        'Tinder',
        'Tony Dollars',
        'Trippi',
        'V-Spot',
        'Valkyrie',
        'Vegas Queen',
        'Vortex',
        'Wanda Power',
        'Weapons Grade',
        'Winston',
        'Wrangler',
        'Wrench',
        'Yando',
        'Yvel Q',
    );

    /**
     * Callsigns from the 2022 TWII Radio Directory.  Remove when adding 2025 TWII handles.
     */
    const TWII_2022 = array(
        'ASL Librarian',
        'Admin 10',
        'Alipato',
        'Alpha 1',
        'Athibat',
        'Audacity',
        'BMID Office',
        'Barack Obama',
        'Be-Rad',
        'Bliss',
        'Box Office',
        'Brady',
        'Breedlove',
        'Brody',
        'BxAir',
        'BxBus',
        'Cailen',
        'Carlos Danger',
        'Cat',
        'ChAos',
        'Chef',
        'Chef Juke',
        'Cherub',
        'Cliff',
        'Coyote',
        'Cuervo',
        'DMV',
        'Danger Ranger',
        'Danny Boy',
        'Dave X',
        'Doug E Fresh',
        'Dr Scirpus',
        'Dustin',
        'Elecktra',
        'Figit',
        'Fireclown',
        'Flitterkit',
        'Flying Squirrel',
        'Free Fall',
        'Goatt',
        'Goldie Hawn',
        'Great Question',
        'HR Dispatch',
        'HazMatt',
        'Heady',
        'Hotspot',
        'Jack Rabbit',
        'Jedi Master',
        'Jim Reed',
        'Kaalin',
        'Kanizzle',
        'Katie Hazard',
        'Katie Hoffman',
        'Kato',
        'Leeway',
        'Leslie Moyer',
        'Level',
        'Lily',
        'Lorax',
        'Louder, Charlie',
        'Lulu',
        'MOD',
        'Manager on Duty',
        'Mango',
        'Marcato',
        'Marie',
        'MarinLove',
        'Media Mecca',
        'Mi\'ao',
        'Miss Kelly',
        'Mitzvah',
        'Monster Mash',
        'Motorbike Matt',
        'mr. blue',
        'Mr. Klean',
        'Mrs. Klean',
        'Muppet',
        'My Ex Girlfriend',
        'Natalie',
        'Network Support',
        'Nimbus',
        'OhMyGod',
        'On It',
        'Pedro',
        'Peter',
        'Playa Info',
        'Playground',
        'President',
        'Purrahna',
        'Quarantine',
        'Radio 1',
        'Rally Point',
        'Ramsey',
        'Razberry',
        'Ready!',
        'Rebel',
        'Retro',
        'Rex',
        'Safety 1',
        'Sawdust',
        'Scorch',
        'Shananagins',
        'Shanwow',
        'Snotto',
        'So On It',
        'Sonder',
        'Spanky',
        'Spitfyre',
        'Steven',
        'Tabasco',
        'Tech Support',
        'the Magpie',
        'Tony Dollars',
        'Top Shelf',
        'Trash Dad',
        'Trinka',
        'V-Spot',
        'Vegas Queen',
        'Vertigo',
        'Vivibaby',
        'Vortex',
        'Wanda Power',
        'Weapons Grade',
        'Weldboy',
        'Wrangler',
        'Wrennegade',
        'Yando',
        'Yoyo',
    );

    /**
     * VIP handles include the last three years of people mentioned in The Way It Is radio
     * directory.  When adding a new year's TWII handles, remove the array from three years prior.
     * Function rather than a constant because PHP constants can't be based on functions like
     * sort.
     */
    public static function twiiVips() {
        $result = array_merge(
            ReservedCallsigns::TWII_2022,
            ReservedCallsigns::TWII_2019,
            ReservedCallsigns::TWII_2018,
        );
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
