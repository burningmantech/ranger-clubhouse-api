<?php

use Illuminate\Database\Seeder;

class HelpTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('help')->delete();
        
        \DB::table('help')->insert(array (
            0 => 
            array (
                'id' => 1,
                'slug' => 'hq-site-checkin-contact-info',
                'title' => 'Help with Camp Location and Contact Information.',
                'body' => 'Camp Location:
Please enter the location on playa where you will be camping.  eg: 7:30 & C. 
Include the Camp Name (if any) and descriptive features: eg: Camp Hammock Hangout (lots of hammocks)

Emergency Contact Information:
Please enter the emergency contact information for a person or persons you would like to have notified should there be an emergency.  Include as much information as appropriate:  You may include:
* Contact Name(s)
* Phone Number(s)
* Email Address(es)
* Postal addresses
and any other information that will help us help you.',
                'tags' => 'Contact Info, Camp Location',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'slug' => 'hq-site-checkin-agreements',
                'title' => 'Radio and Motor pool Agreements',
            'body' => 'The Radio Agreement document must be read, agreed-to, and digitally signed (by accepting the agreement), which acknowledges that you understand the terms by which Rangers issue you a radio as well as the responsibilities that go along with accepting a radio.  Radios will NOT be issued to anyone before this agreement is signed.

The Motor pool agreement details responsibilities that you accept for the privilege of driving any of the vehicle types listed in the policy.  You may NOT drive on playa without signing this agreement.',
                'tags' => NULL,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'slug' => 'hq-site-checkin-radios',
                'title' => 'Event Radios',
                'body' => 'Event radios are issued to certain Rangers based on several factors.  Some Rangers with  operational need for an event radio, like members of Khaki, Cadre Leads, and other critical positions are issued Event radios which may be used on and off shift by those issued them.  They do not need to be returned at the end of shift.

Other Rangers may receive event radios based on an availability and criterion.   Historically, the top hour workers from the previous year are offered an event radio, which they may but are not required to accept.  The number of event radios available to this pool of rangers varies from year to year and is somewhere around 150-200.  For example, if there are 200 radios available, and 1000 on-playa rangers, then the hours that those rangers who worked the previous year are calculated and the top 200 hour workers are offered event radios, which they may keep the entire event week.

View the Ranger Radio Allocation Policy on the google drive here: <a href="https://docs.google.com/document/d/1TPcAvfi_0fkPmwJFvtkoDls-xYCpJPOxFBDzut8nZAQ/edit?usp=sharing">Policy</a>',
                'tags' => NULL,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'slug' => 'hq-sidebar',
                'title' => 'Sidebar Summary Help',
                'body' => 'This sidebar offers a quick status summary for the following:

<B>Earned Credits:</b>
This equals the number of credits earned so far for compete shifts worked and if applicable, the part of the current shift being worked.

<B>Est. Total Credits:</b>
This equals the number of credits earned so far for compete shifts worked and if applicable, the part of the current shift being worked, plus any remaining un-worked full or partial shifts.  NOTE: IF UNWORKED SHIFTS ARE NOT WORKED FOR THE FULL DURATION, THE CREDITS WILL BE LESS THAN THIS ESTIMATE.  ALWAYS PLAN ON WORKING A FEW EXTRA HOURS SO THAT YOUR CREDIT TARGET AMOUNT IS GENEROUSLY EXCEEDED.  

<B>Time worked:</b>
This equals the number of hours worked so far for compete shifts and if applicable, the part of the current shift being worked.

<b>Reduced price ticket</B>
You will need 19 credits for a reduced price ticket for 2020

<b> Staff Credential</B>
You will need 38 credits for a Staff Credential in 2020

<B>Meals:</b>

<B>Showers:</b>
You earn one Shower Pog for every 40 hours worked

<B>Motorpool:</b>
Indicates your permission to drive golf carts & UTVs on playa for Ranger business.

<B>Org Vehicle Insurance:</b>
Indicates your authorization to drive cars and trucks (including personal vehicles) on playa for Ranger business.  Requires previous authorization as well as an driving record check for insurance purposes.


See google doc for <a href="https://docs.google.com/document/d/1k68TxyCRB8A5sq4tYUgX00kvfUDEvGmYiME1ocHLW4k/edit?usp=sharing">Tickets and Credits Policy</a>',
                'tags' => 'sidebar, hours, credits',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            4 => 
            array (
                'id' => 5,
                'slug' => 'hq-timesheet-verification',
                'title' => 'Timesheet Help',
                'body' => 'Timesheet help coming soon...',
                'tags' => 'Timesheet',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            5 => 
            array (
                'id' => 6,
                'slug' => 'hq-shift-management',
                'title' => 'Shift Management',
                'body' => '<b>To start a shift:</b>
Select shift a left.  Press "start shift" button

<b>To finish a shift</b>
Click "End Shift" button

<b>NOTE</b>
Rangers may NOT sign in more that 15 minutes before scheduled start of shift.
Don\'t forget to offer meal POG if ranger does NOT have a BMID with eats on it, and the Ranger has worked over 6 hours (or 3 hours for qualifying perimeter shift for 1/2 meal pog)',
                'tags' => NULL,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            6 => 
            array (
                'id' => 7,
                'slug' => 'hq-assets',
                'title' => 'Radios and Assets',
            'body' => 'To check out a Ranger radio and with it a radio accessory there must first be a signed (yes on paper) <a href="https://drive.google.com/open?id=1p4EerIDN6ZRxcWkI7tDFGuMf6SgL1LTQ" target="_blank">Rangers Radio Checkout Form</a>.',
                'tags' => 'radio, asset',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
        
        
    }
}